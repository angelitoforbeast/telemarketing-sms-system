<?php

namespace App\Services\Shipment;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ShipmentService
{
    /**
     * Get a paginated, filtered list of shipments for a company.
     */
    public function list(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Shipment::with(['status', 'assignedTo'])
            ->forCompany($companyId);

        // Courier filter
        if (!empty($filters['courier'])) {
            $query->where('courier', $filters['courier']);
        }

        // Status filter
        if (!empty($filters['status_id'])) {
            $query->where('normalized_status_id', $filters['status_id']);
        }

        // Province filter
        if (!empty($filters['province'])) {
            $query->where('consignee_province', $filters['province']);
        }

        // City filter
        if (!empty($filters['city'])) {
            $query->where('consignee_city', $filters['city']);
        }

        // Assignment filter
        if (isset($filters['assigned'])) {
            if ($filters['assigned'] === 'unassigned') {
                $query->unassigned();
            } elseif ($filters['assigned'] === 'assigned') {
                $query->whereNotNull('assigned_to_user_id');
            }
        }

        // Assigned to specific user
        if (!empty($filters['assigned_to_user_id'])) {
            $query->assignedTo($filters['assigned_to_user_id']);
        }

        // Search by waybill or consignee name
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('waybill_no', 'like', "%{$search}%")
                  ->orWhere('consignee_name', 'like', "%{$search}%")
                  ->orWhere('consignee_phone_1', 'like', "%{$search}%");
            });
        }

        // Shop Name (sender_name) filter — supports array (multi-select)
        if (!empty($filters['sender_name'])) {
            $senderNames = is_array($filters['sender_name']) ? $filters['sender_name'] : [$filters['sender_name']];
            $senderNames = array_filter($senderNames); // remove empty values
            if (!empty($senderNames)) {
                $query->whereIn('sender_name', $senderNames);
            }
        }

        // Item Name (item_description) filter — supports array (multi-select)
        if (!empty($filters['item_description'])) {
            $itemDescs = is_array($filters['item_description']) ? $filters['item_description'] : [$filters['item_description']];
            $itemDescs = array_filter($itemDescs); // remove empty values
            if (!empty($itemDescs)) {
                $query->where(function ($q) use ($itemDescs) {
                    foreach ($itemDescs as $desc) {
                        $q->orWhere('item_description', 'like', '%' . $desc . '%');
                    }
                });
            }
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Bulk assign shipments to a telemarketer.
     */
    public function bulkAssign(array $shipmentIds, int $userId, int $companyId): int
    {
        return Shipment::forCompany($companyId)
            ->whereIn('id', $shipmentIds)
            ->update([
                'assigned_to_user_id' => $userId,
                'assigned_at' => now(),
            ]);
    }

    /**
     * Auto-assign unassigned shipments round-robin to active telemarketers.
     */
    public function autoAssign(int $companyId, ?int $statusId = null, ?int $limit = null): int
    {
        $telemarketers = User::forCompany($companyId)
            ->active()
            ->role('Telemarketer')
            ->pluck('id')
            ->toArray();

        if (empty($telemarketers)) return 0;

        $query = Shipment::forCompany($companyId)
            ->unassigned()
            ->contactable();

        if ($statusId) {
            $query->where('normalized_status_id', $statusId);
        }

        if ($limit) {
            $query->limit($limit);
        }

        $shipments = $query->get();
        $count = 0;
        $index = 0;

        foreach ($shipments as $shipment) {
            $shipment->update([
                'assigned_to_user_id' => $telemarketers[$index % count($telemarketers)],
                'assigned_at' => now(),
            ]);
            $index++;
            $count++;
        }

        return $count;
    }

    /**
     * Unassign shipments.
     */
    public function unassign(array $shipmentIds, int $companyId): int
    {
        return Shipment::forCompany($companyId)
            ->whereIn('id', $shipmentIds)
            ->update([
                'assigned_to_user_id' => null,
                'assigned_at' => null,
            ]);
    }
}
