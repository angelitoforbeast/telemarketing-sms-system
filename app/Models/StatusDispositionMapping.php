<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class StatusDispositionMapping extends Model
{
    protected $fillable = [
        'company_id',
        'shipment_status_id',
        'disposition_id',
        'sort_order',
    ];

    public function shipmentStatus()
    {
        return $this->belongsTo(ShipmentStatus::class, 'shipment_status_id');
    }

    public function disposition()
    {
        return $this->belongsTo(TelemarketingDisposition::class, 'disposition_id');
    }

    /**
     * Get dispositions available for a given shipment status.
     * Uses company-specific mapping if exists, falls back to system defaults.
     */
    public static function getDispositionsForStatus(int $statusId, ?int $companyId = null): Collection
    {
        // Check for company-specific mapping first
        if ($companyId) {
            $companyMappings = static::where('company_id', $companyId)
                ->where('shipment_status_id', $statusId)
                ->orderBy('sort_order')
                ->with('disposition')
                ->get();

            if ($companyMappings->isNotEmpty()) {
                return $companyMappings->pluck('disposition')->filter();
            }
        }

        // Fall back to system defaults (company_id = null)
        return static::whereNull('company_id')
            ->where('shipment_status_id', $statusId)
            ->orderBy('sort_order')
            ->with('disposition')
            ->get()
            ->pluck('disposition')
            ->filter();
    }

    /**
     * Get full mapping for a company (for settings page).
     * Returns: [status_id => [disposition_ids]]
     */
    public static function getMappingForCompany(?int $companyId): array
    {
        $query = $companyId
            ? static::where('company_id', $companyId)
            : static::whereNull('company_id');

        $mappings = $query->orderBy('shipment_status_id')
            ->orderBy('sort_order')
            ->get();

        $result = [];
        foreach ($mappings as $m) {
            $result[$m->shipment_status_id][] = $m->disposition_id;
        }

        return $result;
    }

    /**
     * Save mapping for a company. Replaces all existing mappings.
     */
    public static function saveMappingForCompany(int $companyId, array $mapping): void
    {
        // Delete existing company mappings
        static::where('company_id', $companyId)->delete();

        $rows = [];
        $now = now();

        foreach ($mapping as $statusId => $dispositionIds) {
            foreach ($dispositionIds as $sortOrder => $dispositionId) {
                $rows[] = [
                    'company_id' => $companyId,
                    'shipment_status_id' => $statusId,
                    'disposition_id' => $dispositionId,
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($rows)) {
            static::insert($rows);
        }
    }
}
