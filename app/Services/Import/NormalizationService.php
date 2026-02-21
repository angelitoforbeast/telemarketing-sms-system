<?php

namespace App\Services\Import;

use App\Models\ShipmentStatus;
use Illuminate\Support\Str;

class NormalizationService
{
    /**
     * Status mapping: courier-specific status text → normalized status code.
     */
    protected array $jntStatusMap = [
        'In Transit'  => 'in_transit',
        'Delivering'  => 'delivering',
        'Delivered'   => 'delivered',
        'Returned'    => 'returned',
        'For Return'  => 'for_return',
    ];

    protected array $flashStatusMap = [
        'Picked Up'           => 'picked_up',
        'In Transit'          => 'in_transit',
        'Out for Delivery'    => 'delivering',
        'Delivering'          => 'delivering',
        'On Delivery'         => 'delivering',
        'Delivered'           => 'delivered',
        'Returned'            => 'returned',
        'Closed'              => 'closed',
        'Failed Delivery'     => 'failed_delivery',
    ];

    protected ?array $statusCache = null;

    /**
     * Normalize a raw JNT row into a standardized shipment array.
     */
    public function normalizeJnt(array $raw): array
    {
        $sourceStatus = trim($raw['Order Status'] ?? '');
        $statusCode = $this->jntStatusMap[$sourceStatus] ?? 'unknown';

        return [
            'courier'            => 'jnt',
            'waybill_no'         => trim($raw['Waybill Number'] ?? ''),
            'source_status_text' => $sourceStatus,
            'status_code'        => $statusCode,
            'consignee_name'     => trim($raw['Receiver'] ?? ''),
            'consignee_phone_1'  => $this->cleanPhone($raw['Receiver Cellphone'] ?? ''),
            'consignee_phone_2'  => null,
            'consignee_address'  => trim($raw['Address'] ?? ''),
            'consignee_province' => trim($raw['Province'] ?? ''),
            'consignee_city'     => trim($raw['City'] ?? ''),
            'consignee_barangay' => trim($raw['Barangay'] ?? ''),
            'sender_name'        => trim($raw['Sender Name'] ?? ''),
            'sender_phone'       => $this->cleanPhone($raw['Sender Cellphone'] ?? ''),
            'cod_amount'         => $this->toDecimal($raw['Cod'] ?? '0'),
            'item_description'   => trim($raw['Item Name'] ?? $raw['Remarks'] ?? ''),
            'item_quantity'      => $this->toInt($raw['Number Of Items'] ?? null),
            'item_weight'        => $this->toDecimal($raw['Item Weight'] ?? $raw['Settlement Weight'] ?? null),
            'shipping_cost'      => $this->toDecimal($raw['Total Shipping Cost'] ?? '0'),
            'payment_method'     => trim($raw['Payment Method'] ?? ''),
            'rts_reason'         => trim($raw['RTS Reason'] ?? ''),
            'remarks'            => trim($raw['Remarks'] ?? ''),
            'submission_time'    => $this->parseDate($raw['Submission Time'] ?? null),
            'signing_time'       => $this->parseDate($raw['SigningTime'] ?? null),
        ];
    }

    /**
     * Normalize a raw Flash row into a standardized shipment array.
     */
    public function normalizeFlash(array $raw): array
    {
        $sourceStatus = trim($raw['Status'] ?? '');
        $statusCode = $this->flashStatusMap[$sourceStatus] ?? 'unknown';

        return [
            'courier'            => 'flash',
            'waybill_no'         => trim($raw['Tracking No.'] ?? ''),
            'source_status_text' => $sourceStatus,
            'status_code'        => $statusCode,
            'consignee_name'     => trim($raw['Consignee'] ?? ''),
            'consignee_phone_1'  => $this->cleanPhone($raw['Consignee phone'] ?? ''),
            'consignee_phone_2'  => $this->cleanPhone($raw['Consignee phone2'] ?? ''),
            'consignee_address'  => trim($raw['Consignee address'] ?? ''),
            'consignee_province' => $this->extractProvince($raw['Consignee address'] ?? ''),
            'consignee_city'     => $this->extractCity($raw['Consignee address'] ?? ''),
            'consignee_barangay' => null,
            'sender_name'        => trim($raw['Sender'] ?? ''),
            'sender_phone'       => $this->cleanPhone($raw['Sender phone'] ?? ''),
            'cod_amount'         => $this->toDecimal($raw['COD Amt'] ?? '0'),
            'item_description'   => trim($raw['Remark1'] ?? ''),
            'item_quantity'      => null,
            'item_weight'        => $this->toDecimal($raw['weight'] ?? null),
            'shipping_cost'      => $this->toDecimal($raw['Total charge'] ?? '0'),
            'payment_method'     => null,
            'rts_reason'         => null,
            'remarks'            => trim(($raw['Remark1'] ?? '') . ' ' . ($raw['Remark2'] ?? '') . ' ' . ($raw['Remark3'] ?? '')),
            'submission_time'    => $this->parseDate($raw['PU time'] ?? null),
            'signing_time'       => $this->parseDate($raw['Delivery time'] ?? null),
        ];
    }

    /**
     * Resolve a status code to its database ID.
     */
    public function resolveStatusId(string $statusCode): ?int
    {
        if ($this->statusCache === null) {
            $this->statusCache = ShipmentStatus::pluck('id', 'code')->toArray();
        }

        return $this->statusCache[$statusCode] ?? null;
    }

    // ── Private Helpers ──

    protected function cleanPhone(?string $phone): ?string
    {
        if (empty($phone)) return null;

        // Remove tabs, spaces, dashes, parentheses
        $phone = preg_replace('/[\t\s\-\(\)]+/', '', $phone);

        // Normalize PH numbers: +63... → 09...
        if (Str::startsWith($phone, '+63')) {
            $phone = '0' . substr($phone, 3);
        } elseif (Str::startsWith($phone, '63') && strlen($phone) === 12) {
            $phone = '0' . substr($phone, 2);
        } elseif (Str::startsWith($phone, '9') && strlen($phone) === 10) {
            $phone = '0' . $phone;
        }

        // Validate: must be 11 digits starting with 09
        if (preg_match('/^09\d{9}$/', $phone)) {
            return $phone;
        }

        return $phone; // Return as-is if non-standard, let validation handle it
    }

    protected function toDecimal(?string $value): float
    {
        if (empty($value) || $value === '-') return 0.00;
        return (float) preg_replace('/[^0-9.\-]/', '', $value);
    }

    protected function toInt(?string $value): ?int
    {
        if (empty($value)) return null;
        return (int) $value;
    }

    protected function parseDate(?string $value): ?string
    {
        if (empty($value) || trim($value) === '' || $value === "\t") return null;
        $value = trim($value, "\t ");
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract province from Flash address (last word is usually province).
     */
    protected function extractProvince(?string $address): ?string
    {
        if (empty($address)) return null;
        $parts = array_filter(explode(' ', trim($address)));
        return end($parts) ?: null;
    }

    /**
     * Extract city from Flash address (second-to-last segment).
     */
    protected function extractCity(?string $address): ?string
    {
        if (empty($address)) return null;
        $parts = array_filter(explode(' ', trim($address)));
        $parts = array_values($parts);
        $count = count($parts);
        return $count >= 2 ? $parts[$count - 2] : null;
    }
}
