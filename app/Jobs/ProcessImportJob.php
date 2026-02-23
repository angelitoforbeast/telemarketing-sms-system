<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\ShipmentStatusLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use OpenSpout\Reader\XLSX\Options as XLSXOptions;
use OpenSpout\Reader\CSV\Reader as CSVReader;
use OpenSpout\Reader\CSV\Options as CSVOptions;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries   = 1;

    private $importJobId;

    const CHUNK_SIZE = 500;

    private $errors    = [];
    private $processed = 0;
    private $inserted  = 0;
    private $updated   = 0;
    private $skipped   = 0;
    private $failed    = 0;

    private ?array $statusCache = null;

    public function __construct($importJobId)
    {
        $this->importJobId = (int) $importJobId;
    }

    public function handle()
    {
        /** @var ImportJob $log */
        $log = ImportJob::findOrFail($this->importJobId);

        $log->update([
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        $filePath = Storage::disk('local')->path($log->storage_path);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        try {
            $this->processSingleFile($filePath, $ext, $log);

            $log->update([
                'status'                 => 'completed',
                'completed_at'           => now(),
                'total_rows'             => $this->processed,
                'processed_rows'         => $this->processed,
                'new_shipments_count'    => $this->inserted,
                'updated_shipments_count'=> $this->updated,
                'skipped_count'          => $this->skipped,
                'failed_rows_count'      => $this->failed,
                'error_summary'          => !empty($this->errors) ? $this->errors : null,
            ]);

            // ── Post-Import: Process status changes and auto-assign ──
            try {
                $telemarketingService = app(\App\Services\Telemarketing\TelemarketingService::class);

                // 1. Process status transitions (auto-reassign mismatched shipments)
                $transitionSummary = $telemarketingService->processStatusChangesAfterImport($log->company_id);
                Log::info("Post-import transition processing for import #{$log->id}: " . json_encode($transitionSummary));

                // 2. Run auto-assign to pick up newly unassigned + new shipments
                $assignSummary = $telemarketingService->autoAssignByRules($log->company_id);
                $totalAssigned = collect($assignSummary)->sum('assigned');
                Log::info("Post-import auto-assign for import #{$log->id}: {$totalAssigned} assigned.");
            } catch (\Throwable $e) {
                Log::warning("Post-import telemarketing processing failed for import #{$log->id}: {$e->getMessage()}");
            }

        } catch (\Throwable $e) {
            Log::error("Import job #{$log->id} failed: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            $log->update([
                'status'        => 'failed',
                'completed_at'  => now(),
                'error_summary' => ['message' => $e->getMessage()],
            ]);
        }
    }

    // ─── File Reading ───

    private function processSingleFile(string $absPath, string $ext, ImportJob $log)
    {
        $reader = null;

        if ($ext === 'xlsx') {
            $reader = new XLSXReader();
        } elseif ($ext === 'csv') {
            $csvOptions = new CSVOptions();
            $csvOptions->FIELD_DELIMITER = ',';
            $csvOptions->FIELD_ENCLOSURE = '"';
            $csvOptions->ENCODING = 'UTF-8';
            $reader = new CSVReader($csvOptions);
        }

        if (!$reader) {
            throw new \RuntimeException('Unsupported file type: ' . $ext);
        }

        $reader->open($absPath);

        $buffer    = [];
        $headerMap = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->toArray();

                // First row = headers
                if ($headerMap === null) {
                    $headerMap = $this->buildHeaderMap($cells, $log->courier);
                    continue;
                }

                $normalized = $this->normalizeRow($cells, $headerMap, $log->courier);

                if (empty($normalized['waybill_no'])) {
                    $this->failed++;
                    $this->errors[] = ['row' => $this->processed + 1, 'error' => 'Missing waybill number'];
                    continue;
                }

                // Deduplicate inside this batch by waybill
                $buffer[$normalized['waybill_no']] = $normalized;
                $this->processed++;

                if (count($buffer) >= self::CHUNK_SIZE) {
                    $this->persistChunk(array_values($buffer), $log);
                    $buffer = [];
                    $this->touchProgress($log);
                }
            }
        }

        if (!empty($buffer)) {
            $this->persistChunk(array_values($buffer), $log);
            $this->touchProgress($log);
        }

        $reader->close();
    }

    // ─── Header Mapping ───

    private function buildHeaderMap(array $headers, string $courier): array
    {
        $norm = fn($s) => trim(mb_strtolower((string) $s));

        if ($courier === 'jnt') {
            $aliases = [
                'waybill_no'       => ['waybill number', 'waybill', 'awb', 'tracking number'],
                'status'           => ['order status', 'status'],
                'consignee_name'   => ['receiver', 'consignee'],
                'consignee_phone'  => ['receiver cellphone', 'receiver phone', 'consignee phone', 'phone'],
                'address'          => ['address'],
                'province'         => ['province'],
                'city'             => ['city'],
                'barangay'         => ['barangay', 'brgy'],
                'sender_name'      => ['sender name', 'sender'],
                'sender_phone'     => ['sender cellphone', 'sender phone'],
                'cod'              => ['cod', 'cod amount'],
                'item_name'        => ['item name', 'product', 'product name'],
                'item_quantity'    => ['number of items', 'quantity'],
                'item_weight'      => ['item weight', 'settlement weight', 'weight'],
                'shipping_cost'    => ['total shipping cost', 'shipping cost'],
                'valuation_fee'    => ['valuation fee', 'insurance fee', 'insurance'],
                'payment_method'   => ['payment method'],
                'rts_reason'       => ['rts reason', 'return reason'],
                'remarks'          => ['remarks', 'remark'],
                'submission_time'  => ['submission time', 'created time'],
                'signing_time'     => ['signingtime', 'signing time', 'delivered time'],
            ];
        } else {
            $aliases = [
                'waybill_no'       => ['tracking no.', 'tracking no', 'tracking number', 'waybill'],
                'status'           => ['status'],
                'consignee_name'   => ['consignee', 'receiver'],
                'consignee_phone'  => ['consignee phone', 'receiver phone', 'phone'],
                'consignee_phone2' => ['consignee phone2', 'phone2'],
                'address'          => ['consignee address', 'address'],
                'sender_name'      => ['sender'],
                'sender_phone'     => ['sender phone'],
                'cod'              => ['cod amt', 'cod amount', 'cod'],
                'weight'           => ['weight'],
                'shipping_cost'    => ['total charge', 'shipping cost'],
                'valuation_fee'    => ['valuation fee', 'insurance fee', 'insurance'],
                'remark1'          => ['remark1', 'remark'],
                'remark2'          => ['remark2'],
                'remark3'          => ['remark3'],
                'submission_time'  => ['pu time', 'pickup time'],
                'signing_time'     => ['delivery time', 'delivered time'],
            ];
        }

        $map = [];

        foreach ($headers as $idx => $label) {
            $h = $norm($label);
            foreach ($aliases as $canon => $candidates) {
                if (isset($map[$canon])) continue;
                foreach ($candidates as $cand) {
                    if ($h === $norm($cand)) {
                        $map[$canon] = $idx;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    // ─── Row Normalization ───

    private function normalizeRow(array $cells, array $map, string $courier): array
    {
        $get = function ($key) use ($cells, $map) {
            if (!isset($map[$key])) return '';
            $val = $cells[$map[$key]] ?? '';
            $val = is_scalar($val) ? (string) $val : '';
            return trim(preg_replace('/\s+/u', ' ', $val));
        };

        if ($courier === 'jnt') {
            return [
                'courier'            => 'jnt',
                'waybill_no'         => $get('waybill_no'),
                'source_status_text' => $get('status'),
                'consignee_name'     => $get('consignee_name'),
                'consignee_phone_1'  => $this->cleanPhone($get('consignee_phone')),
                'consignee_phone_2'  => null,
                'consignee_address'  => $get('address'),
                'consignee_province' => $get('province'),
                'consignee_city'     => $get('city'),
                'consignee_barangay' => $get('barangay'),
                'sender_name'        => $get('sender_name'),
                'sender_phone'       => $this->cleanPhone($get('sender_phone')),
                'cod_amount'         => $this->parseMoney($get('cod')),
                'item_description'   => $get('item_name'),
                'item_quantity'      => $get('item_quantity') !== '' ? (int) $get('item_quantity') : null,
                'item_weight'        => $this->parseMoney($get('item_weight')),
                'shipping_cost'      => $this->parseMoney($get('shipping_cost')),
                'valuation_fee'      => $this->parseMoney($get('valuation_fee')),
                'payment_method'     => $get('payment_method'),
                'rts_reason'         => $get('rts_reason'),
                'remarks'            => $get('remarks'),
                'submission_time'    => $this->parseDate($get('submission_time')),
                'signing_time'       => $this->parseDate($get('signing_time')),
            ];
        } else {
            return [
                'courier'            => 'flash',
                'waybill_no'         => $get('waybill_no'),
                'source_status_text' => $get('status'),
                'consignee_name'     => $get('consignee_name'),
                'consignee_phone_1'  => $this->cleanPhone($get('consignee_phone')),
                'consignee_phone_2'  => $this->cleanPhone($get('consignee_phone2')),
                'consignee_address'  => $get('address'),
                'consignee_province' => null,
                'consignee_city'     => null,
                'consignee_barangay' => null,
                'sender_name'        => $get('sender_name'),
                'sender_phone'       => $this->cleanPhone($get('sender_phone')),
                'cod_amount'         => $this->parseMoney($get('cod')),
                'item_description'   => $get('remark1'),
                'item_quantity'      => null,
                'item_weight'        => $this->parseMoney($get('weight')),
                'shipping_cost'      => $this->parseMoney($get('shipping_cost')),
                'valuation_fee'      => $this->parseMoney($get('valuation_fee')),
                'payment_method'     => null,
                'rts_reason'         => null,
                'remarks'            => trim($get('remark1') . ' ' . $get('remark2') . ' ' . $get('remark3')),
                'submission_time'    => $this->parseDate($get('submission_time')),
                'signing_time'       => $this->parseDate($get('signing_time')),
            ];
        }
    }

    // ─── Persist Chunk (Upsert) ───

    private function persistChunk(array $rows, ImportJob $log)
    {
        if (empty($rows)) return;

        // Load status cache once
        if ($this->statusCache === null) {
            $this->statusCache = ShipmentStatus::pluck('id', 'code')->toArray();
        }

        // Status text → code mapping
        $jntStatusMap = [
            'In Transit' => 'in_transit', 'Delivering' => 'delivering',
            'Delivered' => 'delivered', 'Returned' => 'returned', 'For Return' => 'for_return',
        ];
        $flashStatusMap = [
            'Picked Up' => 'picked_up', 'In Transit' => 'in_transit',
            'Out for Delivery' => 'delivering', 'Delivering' => 'delivering',
            'On Delivery' => 'delivering', 'Delivered' => 'delivered',
            'Returned' => 'returned', 'Closed' => 'closed', 'Failed Delivery' => 'failed_delivery',
        ];

        $waybills = array_column($rows, 'waybill_no');

        $existing = Shipment::where('company_id', $log->company_id)
            ->where('courier', $log->courier)
            ->whereIn('waybill_no', $waybills)
            ->get()
            ->keyBy('waybill_no');

        $toInsert = [];
        $toUpdateIds = [];
        $now = now()->format('Y-m-d H:i:s');

        DB::transaction(function () use ($rows, $log, $existing, $jntStatusMap, $flashStatusMap, $now) {
            $statusMap = $log->courier === 'jnt' ? $jntStatusMap : $flashStatusMap;

            foreach ($rows as $r) {
                $wb = $r['waybill_no'];
                $statusCode = $statusMap[$r['source_status_text']] ?? 'unknown';
                $statusId = $this->statusCache[$statusCode] ?? null;

                // Build shipment data
                $shipmentData = $r;
                unset($shipmentData['source_status_text']);
                $shipmentData['company_id'] = $log->company_id;
                $shipmentData['normalized_status_id'] = $statusId;
                $shipmentData['last_status_update_at'] = $now;

                    if (isset($existing[$wb])) {
                    $existingShipment = $existing[$wb];

                    // Skip if already delivered or returned
                    $currentStatus = strtolower($existingShipment->source_status_text ?? '');
                    if (in_array($currentStatus, ['delivered', 'returned'])) {
                        $this->skipped++;
                        continue;
                    }

                    // Track previous status for transition rule processing
                    $oldStatusId = $existingShipment->normalized_status_id;
                    if ($oldStatusId !== $statusId) {
                        $shipmentData['previous_status_id'] = $oldStatusId;
                    }

                    $existingShipment->update($shipmentData);
                    $this->updated++;

                    // Log status change
                    if ($statusId) {
                        ShipmentStatusLog::create([
                            'shipment_id'        => $existingShipment->id,
                            'status_id'          => $statusId,
                            'source_status_text'  => $r['source_status_text'],
                            'import_job_id'      => $log->id,
                            'logged_at'          => $now,
                        ]);
                    }
                } else {
                    $shipmentData['source_status_text'] = $r['source_status_text'];
                    $shipmentData['created_at'] = $now;
                    $shipmentData['updated_at'] = $now;

                    $shipment = Shipment::create($shipmentData);
                    $this->inserted++;

                    // Log initial status
                    if ($statusId) {
                        ShipmentStatusLog::create([
                            'shipment_id'        => $shipment->id,
                            'status_id'          => $statusId,
                            'source_status_text'  => $r['source_status_text'],
                            'import_job_id'      => $log->id,
                            'logged_at'          => $now,
                        ]);
                    }
                }
            }
        });
    }

    // ─── Progress Tracking ───

    private function touchProgress(ImportJob $log)
    {
        $log->update([
            'processed_rows'          => $this->processed,
            'new_shipments_count'     => $this->inserted,
            'updated_shipments_count' => $this->updated,
            'skipped_count'           => $this->skipped,
            'failed_rows_count'       => $this->failed,
        ]);
    }

    // ─── Helpers ───

    private function cleanPhone(?string $phone): ?string
    {
        if (empty($phone)) return null;
        $phone = preg_replace('/[\t\s\-\(\)]+/', '', $phone);

        if (Str::startsWith($phone, '+63')) {
            $phone = '0' . substr($phone, 3);
        } elseif (Str::startsWith($phone, '63') && strlen($phone) === 12) {
            $phone = '0' . substr($phone, 2);
        } elseif (Str::startsWith($phone, '9') && strlen($phone) === 10) {
            $phone = '0' . $phone;
        }

        return $phone;
    }

    private function parseMoney(?string $value): float
    {
        if (empty($value) || $value === '-') return 0.00;
        $clean = preg_replace('/[^\d.\-]/', '', (string) $value);
        return $clean === '' ? 0.00 : (float) $clean;
    }

    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        // Handle Excel numeric dates
        if (is_numeric($value)) {
            try {
                $base = Carbon::create(1899, 12, 30, 0, 0, 0, 'Asia/Manila');
                return $base->copy()->addDays((int) $value)->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {}
        }

        $formats = [
            'Y-m-d H:i:s', 'Y-m-d H:i', 'm/d/Y H:i', 'd/m/Y H:i',
            'm/d/Y', 'd/m/Y', 'Y-m-d', 'd-m-Y H:i', 'd-m-Y H:i:s', 'd-m-Y',
        ];

        foreach ($formats as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $value, 'Asia/Manila')->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {}
        }

        try {
            return Carbon::parse($value, 'Asia/Manila')->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
