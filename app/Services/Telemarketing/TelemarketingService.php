<?php

namespace App\Services\Telemarketing;

use App\Models\Shipment;
use App\Models\TelemarketerStatusAssignment;
use App\Models\TelemarketingAssignmentRule;
use App\Models\TelemarketingDisposition;
use App\Models\TelemarketingLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TelemarketingService
{
    // ────────────────────────────────────────────────────────────────
    //  QUEUE
    // ────────────────────────────────────────────────────────────────

    /**
     * Get the telemarketer's assigned shipment queue with smart ordering.
     * Respects per-agent status assignments: only shows statuses assigned to the agent.
     */
    public function getQueue(int $userId, int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Shipment::with(['status', 'lastDisposition', 'assignedTo'])
            ->forCompany($companyId)
            ->assignedTo($userId)
            ->telemarketable();

        // Apply per-agent status filter (if configured)
        $this->applyAgentStatusFilter($query, $userId);

        // Filter by status
        if (!empty($filters['status_id'])) {
            $query->where('normalized_status_id', $filters['status_id']);
        }

        // Filter by courier
        if (!empty($filters['courier'])) {
            $query->where('courier', $filters['courier']);
        }

        // Filter: only callbacks due
        if (!empty($filters['callbacks_only'])) {
            $query->callbackDue();
        }

        // Filter: never contacted
        if (!empty($filters['never_contacted'])) {
            $query->neverContacted();
        }

        // Search by waybill or name
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('waybill_no', 'like', "%{$search}%")
                  ->orWhere('consignee_name', 'like', "%{$search}%")
                  ->orWhere('consignee_phone_1', 'like', "%{$search}%");
            });
        }

        // Smart ordering: callbacks due first, then never-contacted, then oldest contacted
        $now = now()->toDateTimeString();
        $query->orderByRaw("CASE WHEN callback_scheduled_at IS NOT NULL AND callback_scheduled_at <= ? THEN 0 ELSE 1 END ASC", [$now])
              ->orderByRaw("CASE WHEN telemarketing_attempt_count = 0 THEN 0 ELSE 1 END ASC")
              ->orderByRaw("last_contacted_at IS NULL DESC")
              ->orderBy('last_contacted_at', 'asc');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Get the next shipment to call for a telemarketer (auto-advance).
     * Respects per-agent status assignments.
     */
    public function getNextCall(int $userId, int $companyId, ?int $excludeShipmentId = null): ?Shipment
    {
        $query = Shipment::with(['status', 'lastDisposition'])
            ->forCompany($companyId)
            ->assignedTo($userId)
            ->telemarketable();

        // Apply per-agent status filter
        $this->applyAgentStatusFilter($query, $userId);

        if ($excludeShipmentId) {
            $query->where('id', '!=', $excludeShipmentId);
        }

        // Priority: callbacks due > never contacted > oldest contacted
        $now = now()->toDateTimeString();
        return $query
            ->orderByRaw("CASE WHEN callback_scheduled_at IS NOT NULL AND callback_scheduled_at <= ? THEN 0 ELSE 1 END ASC", [$now])
            ->orderByRaw("CASE WHEN telemarketing_attempt_count = 0 THEN 0 ELSE 1 END ASC")
            ->orderByRaw("last_contacted_at IS NULL DESC")
            ->orderBy('last_contacted_at', 'asc')
            ->first();
    }

    /**
     * Apply the per-agent status filter to a query.
     * If the agent has assigned statuses, only show shipments with those statuses.
     * If no statuses assigned, show all (backwards compatible).
     */
    protected function applyAgentStatusFilter($query, int $userId): void
    {
        $allowedStatusIds = $this->getAgentAllowedStatusIds($userId);

        if ($allowedStatusIds !== null) {
            $query->whereIn('normalized_status_id', $allowedStatusIds);
        }
    }

    /**
     * Get the allowed status IDs for a telemarketer.
     * Returns null if no restrictions (all statuses allowed).
     */
    public function getAgentAllowedStatusIds(int $userId): ?array
    {
        $ids = TelemarketerStatusAssignment::where('user_id', $userId)
            ->pluck('shipment_status_id')
            ->toArray();

        return empty($ids) ? null : $ids;
    }

    /**
     * Sync the assigned statuses for a telemarketer.
     */
    public function syncAgentStatuses(int $userId, int $companyId, array $statusIds): void
    {
        // Remove existing assignments
        TelemarketerStatusAssignment::where('user_id', $userId)
            ->where('company_id', $companyId)
            ->delete();

        // Insert new assignments
        foreach ($statusIds as $statusId) {
            TelemarketerStatusAssignment::create([
                'user_id' => $userId,
                'shipment_status_id' => $statusId,
                'company_id' => $companyId,
            ]);
        }
    }

    /**
     * Get the assigned statuses for all telemarketers in a company.
     * Returns array keyed by user_id => [status_id, status_id, ...]
     */
    public function getAllAgentStatusAssignments(int $companyId): array
    {
        $assignments = TelemarketerStatusAssignment::where('company_id', $companyId)
            ->get()
            ->groupBy('user_id');

        $result = [];
        foreach ($assignments as $userId => $items) {
            $result[$userId] = $items->pluck('shipment_status_id')->toArray();
        }

        return $result;
    }

    // ────────────────────────────────────────────────────────────────
    //  CALL LOGGING
    // ────────────────────────────────────────────────────────────────

    /**
     * Log a telemarketing call attempt with disposition handling.
     */
    public function logCall(
        int $shipmentId,
        int $userId,
        int $dispositionId,
        ?string $notes = null,
        ?string $callbackAt = null,
        ?string $phoneCalled = null,
        ?int $callDurationSeconds = null
    ): TelemarketingLog {
        $shipment = Shipment::findOrFail($shipmentId);
        $disposition = TelemarketingDisposition::findOrFail($dispositionId);

        $log = TelemarketingLog::create([
            'shipment_id' => $shipmentId,
            'user_id' => $userId,
            'disposition_id' => $dispositionId,
            'notes' => $notes,
            'attempt_no' => $shipment->telemarketing_attempt_count + 1,
            'callback_at' => $callbackAt,
            'phone_called' => $phoneCalled ?? $shipment->consignee_phone_1,
            'call_duration_seconds' => $callDurationSeconds,
            'call_started_at' => now(),
        ]);

        // Determine new telemarketing status based on disposition
        $newStatus = 'in_progress';
        $callbackScheduledAt = null;

        if ($disposition->is_final) {
            $newStatus = 'completed';
        }

        if ($disposition->marks_do_not_call) {
            $newStatus = 'do_not_call';
        }

        if ($disposition->requires_callback && $callbackAt) {
            $callbackScheduledAt = $callbackAt;
        }

        // Update shipment
        $shipment->update([
            'telemarketing_attempt_count' => $shipment->telemarketing_attempt_count + 1,
            'last_contacted_at' => now(),
            'telemarketing_status' => $newStatus,
            'last_disposition_id' => $dispositionId,
            'callback_scheduled_at' => $callbackScheduledAt,
            'is_do_not_contact' => $disposition->marks_do_not_call,
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

    // ────────────────────────────────────────────────────────────────
    //  ASSIGNMENT
    // ────────────────────────────────────────────────────────────────

    /**
     * Auto-assign shipments to telemarketers based on configured rules.
     * Now respects per-agent status assignments: only assigns shipments
     * with matching statuses to agents who handle those statuses.
     */
    public function autoAssignByRules(int $companyId, ?int $ruleId = null): array
    {
        $rulesQuery = TelemarketingAssignmentRule::forCompany($companyId)->active();

        if ($ruleId) {
            $rulesQuery->where('id', $ruleId);
        }

        $rules = $rulesQuery->orderBy('priority', 'desc')->get();
        $results = [];

        foreach ($rules as $rule) {
            $count = $this->executeAssignmentRule($rule, $companyId);
            $results[] = [
                'rule' => $rule->name,
                'assigned' => $count,
            ];
        }

        return $results;
    }

    /**
     * Execute a single assignment rule.
     * Respects per-agent status assignments.
     */
    protected function executeAssignmentRule(TelemarketingAssignmentRule $rule, int $companyId): int
    {
        $telemarketers = $this->getAvailableTelemarketers($companyId);
        if ($telemarketers->isEmpty()) return 0;

        $query = Shipment::forCompany($companyId)
            ->unassigned()
            ->contactable()
            ->where('telemarketing_status', '!=', 'completed')
            ->where('telemarketing_status', '!=', 'do_not_call');

        // Apply rule-specific filters
        switch ($rule->rule_type) {
            case 'status_based':
                if ($rule->status_id) {
                    $query->where('normalized_status_id', $rule->status_id);
                }
                break;

            case 'delivered_age':
                $query->whereHas('status', fn ($q) => $q->where('code', 'delivered'))
                      ->where('signing_time', '<=', now()->subDays($rule->days_threshold ?? 7));
                break;
        }

        // Respect max attempts
        if ($rule->max_attempts > 0) {
            $query->where('telemarketing_attempt_count', '<', $rule->max_attempts);
        }

        $shipments = $query->get();

        if ($shipments->isEmpty()) return 0;

        // Get per-agent status assignments
        $agentStatusMap = $this->getAllAgentStatusAssignments($companyId);

        // Assign based on method
        if ($rule->assignment_method === 'workload_based') {
            return $this->assignWorkloadBased($shipments, $telemarketers, $companyId, $agentStatusMap);
        }

        return $this->assignRoundRobin($shipments, $telemarketers, $agentStatusMap);
    }

    /**
     * Get eligible telemarketers for a shipment based on its status.
     * If an agent has status assignments, only include them if the shipment's status matches.
     * If an agent has NO status assignments, they can handle any status.
     */
    protected function getEligibleTelemarketers($telemarketers, int $shipmentStatusId, array $agentStatusMap): array
    {
        $eligible = [];

        foreach ($telemarketers as $tm) {
            if (isset($agentStatusMap[$tm->id])) {
                // Agent has specific status assignments — check if this status is in their list
                if (in_array($shipmentStatusId, $agentStatusMap[$tm->id])) {
                    $eligible[] = $tm;
                }
            } else {
                // Agent has no status restrictions — can handle any status
                $eligible[] = $tm;
            }
        }

        return $eligible;
    }

    /**
     * Round-robin assignment. Respects per-agent status assignments.
     */
    protected function assignRoundRobin($shipments, $telemarketers, array $agentStatusMap): int
    {
        $count = 0;
        // Track round-robin index per eligible group (keyed by sorted eligible IDs)
        $rrIndexes = [];

        foreach ($shipments as $shipment) {
            $eligible = $this->getEligibleTelemarketers($telemarketers, $shipment->normalized_status_id, $agentStatusMap);

            if (empty($eligible)) continue;

            // Create a key for this eligible group
            $eligibleIds = array_map(fn($t) => $t->id, $eligible);
            sort($eligibleIds);
            $groupKey = implode(',', $eligibleIds);

            if (!isset($rrIndexes[$groupKey])) {
                $rrIndexes[$groupKey] = 0;
            }

            $assignTo = $eligible[$rrIndexes[$groupKey] % count($eligible)];
            $rrIndexes[$groupKey]++;

            $shipment->update([
                'assigned_to_user_id' => $assignTo->id,
                'assigned_at' => now(),
                'telemarketing_status' => 'in_progress',
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Workload-based assignment. Respects per-agent status assignments.
     */
    protected function assignWorkloadBased($shipments, $telemarketers, int $companyId, array $agentStatusMap): int
    {
        // Get current workload per telemarketer
        $workloads = Shipment::forCompany($companyId)
            ->whereIn('assigned_to_user_id', $telemarketers->pluck('id'))
            ->whereIn('telemarketing_status', ['pending', 'in_progress'])
            ->groupBy('assigned_to_user_id')
            ->select('assigned_to_user_id', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'assigned_to_user_id')
            ->toArray();

        $count = 0;

        foreach ($shipments as $shipment) {
            $eligible = $this->getEligibleTelemarketers($telemarketers, $shipment->normalized_status_id, $agentStatusMap);

            if (empty($eligible)) continue;

            // Find eligible telemarketer with lowest workload
            $minLoad = PHP_INT_MAX;
            $assignTo = null;

            foreach ($eligible as $tm) {
                $load = $workloads[$tm->id] ?? 0;
                if ($load < $minLoad) {
                    $minLoad = $load;
                    $assignTo = $tm->id;
                }
            }

            if ($assignTo) {
                $shipment->update([
                    'assigned_to_user_id' => $assignTo,
                    'assigned_at' => now(),
                    'telemarketing_status' => 'in_progress',
                ]);
                $workloads[$assignTo] = ($workloads[$assignTo] ?? 0) + 1;
                $count++;
            }
        }

        return $count;
    }

    /**
     * Manual assignment of specific shipments to a telemarketer.
     */
    public function manualAssign(array $shipmentIds, int $userId, int $companyId): int
    {
        return Shipment::forCompany($companyId)
            ->whereIn('id', $shipmentIds)
            ->update([
                'assigned_to_user_id' => $userId,
                'assigned_at' => now(),
                'telemarketing_status' => DB::raw("CASE WHEN telemarketing_status = 'pending' THEN 'in_progress' ELSE telemarketing_status END"),
            ]);
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

    /**
     * Get available telemarketers for a company.
     */
    public function getAvailableTelemarketers(int $companyId)
    {
        return User::forCompany($companyId)
            ->active()
            ->role('Telemarketer')
            ->get();
    }

    // ────────────────────────────────────────────────────────────────
    //  DASHBOARD STATS
    // ────────────────────────────────────────────────────────────────

    /**
     * Get telemarketing dashboard stats for a user.
     */
    public function getUserStats(int $userId, int $companyId): array
    {
        $today = now()->startOfDay();

        $baseQuery = Shipment::forCompany($companyId)
            ->assignedTo($userId)
            ->telemarketable();

        // Apply per-agent status filter
        $allowedStatusIds = $this->getAgentAllowedStatusIds($userId);

        $totalAssigned = (clone $baseQuery)->when($allowedStatusIds, fn($q) => $q->whereIn('normalized_status_id', $allowedStatusIds))->count();

        $callsToday = TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', $today)
            ->count();

        $callbacksDue = Shipment::forCompany($companyId)
            ->assignedTo($userId)
            ->callbackDue()
            ->when($allowedStatusIds, fn($q) => $q->whereIn('normalized_status_id', $allowedStatusIds))
            ->count();

        $completedToday = TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', $today)
            ->whereHas('disposition', fn ($q) => $q->where('is_final', true))
            ->count();

        $neverContacted = Shipment::forCompany($companyId)
            ->assignedTo($userId)
            ->telemarketable()
            ->neverContacted()
            ->when($allowedStatusIds, fn($q) => $q->whereIn('normalized_status_id', $allowedStatusIds))
            ->count();

        // Disposition breakdown for today
        $dispositionBreakdown = TelemarketingLog::where('user_id', $userId)
            ->where('telemarketing_logs.created_at', '>=', $today)
            ->join('telemarketing_dispositions', 'telemarketing_logs.disposition_id', '=', 'telemarketing_dispositions.id')
            ->groupBy('telemarketing_dispositions.name', 'telemarketing_dispositions.color')
            ->select(
                'telemarketing_dispositions.name',
                'telemarketing_dispositions.color',
                DB::raw('COUNT(*) as count')
            )
            ->orderBy('count', 'desc')
            ->get();

        // Get assigned statuses for display
        $assignedStatuses = $allowedStatusIds
            ? \App\Models\ShipmentStatus::whereIn('id', $allowedStatusIds)->pluck('name')->toArray()
            : ['All Statuses'];

        return [
            'total_assigned' => $totalAssigned,
            'calls_today' => $callsToday,
            'callbacks_due' => $callbacksDue,
            'completed_today' => $completedToday,
            'never_contacted' => $neverContacted,
            'disposition_breakdown' => $dispositionBreakdown,
            'assigned_statuses' => $assignedStatuses,
        ];
    }

    /**
     * Get manager-level overview stats for the whole company.
     */
    public function getCompanyStats(int $companyId): array
    {
        $today = now()->startOfDay();

        $totalPending = Shipment::forCompany($companyId)
            ->where('telemarketing_status', 'pending')
            ->count();

        $totalInProgress = Shipment::forCompany($companyId)
            ->where('telemarketing_status', 'in_progress')
            ->count();

        $totalCompleted = Shipment::forCompany($companyId)
            ->where('telemarketing_status', 'completed')
            ->count();

        $totalUnassigned = Shipment::forCompany($companyId)
            ->unassigned()
            ->contactable()
            ->whereIn('telemarketing_status', ['pending', 'in_progress'])
            ->count();

        $callsToday = TelemarketingLog::whereHas('shipment', fn ($q) => $q->forCompany($companyId))
            ->where('created_at', '>=', $today)
            ->count();

        // Per-telemarketer stats
        $telemarketers = User::forCompany($companyId)
            ->active()
            ->role('Telemarketer')
            ->withCount([
                'assignedShipments as pending_count' => function ($q) {
                    $q->whereIn('telemarketing_status', ['pending', 'in_progress']);
                },
                'telemarketingLogs as calls_today_count' => function ($q) use ($today) {
                    $q->where('created_at', '>=', $today);
                },
            ])
            ->get();

        // Get per-agent status assignments for display
        $agentStatusMap = $this->getAllAgentStatusAssignments($companyId);

        return [
            'total_pending' => $totalPending,
            'total_in_progress' => $totalInProgress,
            'total_completed' => $totalCompleted,
            'total_unassigned' => $totalUnassigned,
            'calls_today' => $callsToday,
            'telemarketers' => $telemarketers,
            'agent_status_map' => $agentStatusMap,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    //  DISPOSITIONS
    // ────────────────────────────────────────────────────────────────

    /**
     * Get dispositions for a company (system + custom).
     */
    public function getDispositions(int $companyId)
    {
        return TelemarketingDisposition::forCompany($companyId)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Create a custom disposition for a company.
     */
    public function createDisposition(int $companyId, array $data): TelemarketingDisposition
    {
        return TelemarketingDisposition::create(array_merge($data, [
            'company_id' => $companyId,
            'is_system' => false,
        ]));
    }

    /**
     * Update a custom disposition.
     */
    public function updateDisposition(TelemarketingDisposition $disposition, array $data): TelemarketingDisposition
    {
        $disposition->update($data);
        return $disposition->fresh();
    }

    /**
     * Delete a custom disposition (system dispositions cannot be deleted).
     */
    public function deleteDisposition(TelemarketingDisposition $disposition): bool
    {
        if ($disposition->is_system) {
            return false;
        }
        return $disposition->delete();
    }
}
