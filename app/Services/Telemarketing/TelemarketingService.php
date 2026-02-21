<?php

namespace App\Services\Telemarketing;

use App\Models\Shipment;
use App\Models\TelemarketingLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TelemarketingService
{
    /**
     * Get the telemarketer's assigned shipment queue.
     */
    public function getQueue(int $userId, int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Shipment::with(['status', 'telemarketingLogs.disposition'])
            ->forCompany($companyId)
            ->assignedTo($userId)
            ->contactable();

        // Filter by status
        if (!empty($filters['status_id'])) {
            $query->where('normalized_status_id', $filters['status_id']);
        }

        // Filter by callback due
        if (!empty($filters['callback_due'])) {
            $query->whereHas('telemarketingLogs', function ($q) {
                $q->where('callback_at', '<=', now())
                  ->whereNotNull('callback_at');
            });
        }

        $query->orderByRaw('last_contacted_at IS NULL DESC') // Not yet contacted first
              ->orderBy('last_contacted_at', 'asc');

        return $query->paginate($perPage);
    }

    /**
     * Log a telemarketing call attempt.
     */
    public function logCall(
        int $shipmentId,
        int $userId,
        int $dispositionId,
        ?string $notes = null,
        ?string $callbackAt = null,
        ?string $phoneCalled = null
    ): TelemarketingLog {
        $shipment = Shipment::findOrFail($shipmentId);

        $log = TelemarketingLog::create([
            'shipment_id' => $shipmentId,
            'user_id' => $userId,
            'disposition_id' => $dispositionId,
            'notes' => $notes,
            'attempt_no' => $shipment->telemarketing_attempt_count + 1,
            'callback_at' => $callbackAt,
            'phone_called' => $phoneCalled ?? $shipment->consignee_phone_1,
        ]);

        // Update shipment counters
        $shipment->update([
            'telemarketing_attempt_count' => $shipment->telemarketing_attempt_count + 1,
            'last_contacted_at' => now(),
        ]);

        return $log;
    }

    /**
     * Get call history for a specific shipment.
     */
    public function getCallHistory(int $shipmentId): \Illuminate\Database\Eloquent\Collection
    {
        return TelemarketingLog::with(['user', 'disposition'])
            ->where('shipment_id', $shipmentId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
