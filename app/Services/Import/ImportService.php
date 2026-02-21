<?php

namespace App\Services\Import;

use App\Models\ImportJob;
use App\Models\RawFlashRow;
use App\Models\RawJntRow;
use App\Models\Shipment;
use App\Models\ShipmentStatusLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportService
{
    public function __construct(
        protected NormalizationService $normalizer
    ) {}

    /**
     * Detect courier type from file headers.
     */
    public function detectCourier(array $headers): ?string
    {
        $jntSignature = ['Waybill Number', 'Creator Code', 'Settlement Weight'];
        $flashSignature = ['Tracking No.', 'Consignee', 'PU time', 'COD Amt'];

        $jntMatch = count(array_intersect($jntSignature, $headers));
        $flashMatch = count(array_intersect($flashSignature, $headers));

        if ($jntMatch >= 2) return 'jnt';
        if ($flashMatch >= 2) return 'flash';

        return null;
    }

    /**
     * Process a single import job (called from the queued job).
     */
    public function processImportJob(ImportJob $importJob): void
    {
        $importJob->markProcessing();

        try {
            if ($importJob->courier === 'jnt') {
                $this->processJntRows($importJob);
            } else {
                $this->processFlashRows($importJob);
            }

            $importJob->markCompleted();
        } catch (\Throwable $e) {
            Log::error("Import job #{$importJob->id} failed: {$e->getMessage()}", [
                'exception' => $e,
            ]);
            $importJob->markFailed([
                'message' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 1000),
            ]);
        }
    }

    /**
     * Process all raw JNT rows for an import job.
     */
    protected function processJntRows(ImportJob $importJob): void
    {
        $rawRows = $importJob->rawJntRows()->where('is_processed', false)->cursor();

        foreach ($rawRows as $rawRow) {
            try {
                $normalized = $this->normalizer->normalizeJnt($rawRow->data);
                $this->upsertShipment($importJob, $normalized);
                $rawRow->update(['is_processed' => true]);
                $importJob->incrementProcessedRows();
            } catch (\Throwable $e) {
                $rawRow->update([
                    'is_processed' => true,
                    'error_message' => $e->getMessage(),
                ]);
                $importJob->incrementFailedRows();
            }
        }
    }

    /**
     * Process all raw Flash rows for an import job.
     */
    protected function processFlashRows(ImportJob $importJob): void
    {
        $rawRows = $importJob->rawFlashRows()->where('is_processed', false)->cursor();

        foreach ($rawRows as $rawRow) {
            try {
                $normalized = $this->normalizer->normalizeFlash($rawRow->data);
                $this->upsertShipment($importJob, $normalized);
                $rawRow->update(['is_processed' => true]);
                $importJob->incrementProcessedRows();
            } catch (\Throwable $e) {
                $rawRow->update([
                    'is_processed' => true,
                    'error_message' => $e->getMessage(),
                ]);
                $importJob->incrementFailedRows();
            }
        }
    }

    /**
     * Upsert a shipment record and log the status change.
     */
    protected function upsertShipment(ImportJob $importJob, array $normalized): void
    {
        if (empty($normalized['waybill_no'])) {
            throw new \RuntimeException('Missing waybill number.');
        }

        $statusId = $this->normalizer->resolveStatusId($normalized['status_code']);
        $sourceStatusText = $normalized['source_status_text'];

        // Remove non-shipment keys before upsert
        unset($normalized['status_code'], $normalized['source_status_text']);

        $normalized['company_id'] = $importJob->company_id;
        $normalized['normalized_status_id'] = $statusId;
        $normalized['last_status_update_at'] = now();

        $existing = Shipment::where('company_id', $importJob->company_id)
            ->where('courier', $normalized['courier'])
            ->where('waybill_no', $normalized['waybill_no'])
            ->first();

        if ($existing) {
            // Only update if status changed or new data is available
            $existing->update($normalized);
            $importJob->incrementUpdatedShipments();
            $shipment = $existing;
        } else {
            $shipment = Shipment::create($normalized);
            $importJob->incrementNewShipments();
        }

        // Log the status change
        if ($statusId) {
            ShipmentStatusLog::create([
                'shipment_id' => $shipment->id,
                'status_id' => $statusId,
                'source_status_text' => $sourceStatusText,
                'import_job_id' => $importJob->id,
                'logged_at' => now(),
            ]);
        }
    }
}
