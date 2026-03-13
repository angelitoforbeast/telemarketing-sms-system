<?php

namespace App\Services\Telemarketing;

use App\Models\Shipment;
use App\Models\StatusTransitionRule;
use App\Models\TelemarketerStatusAssignment;
use App\Models\TelemarketingAssignmentRule;
use App\Models\TelemarketingDisposition;
use App\Models\TelemarketingLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $settings = \App\Models\CompanyTelemarketingSetting::getOrCreate($companyId);
        $queueMode = $settings->queue_mode;

        $query = Shipment::with(['status', 'lastDisposition', 'assignedTo'])
            ->forCompany($companyId)
            ->telemarketable();

        // Apply queue mode filter
        if ($queueMode === 'pre_assigned') {
            $query->assignedTo($userId);
        } elseif ($queueMode === 'shared_queue') {
            // Show all telemarketable shipments (assigned or unassigned)
            // No assignedTo filter
        } elseif ($queueMode === 'hybrid') {
            // Show own assigned + unassigned pool
            $query->where(function ($q) use ($userId) {
                $q->where('assigned_to_user_id', $userId)
                  ->orWhereNull('assigned_to_user_id');
            });
        }

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
        // Smart ordering: own assigned first (for hybrid), callbacks due, never-contacted, oldest
        $now = now()->toDateTimeString();
        if ($queueMode === 'hybrid') {
            $query->orderByRaw("CASE WHEN assigned_to_user_id = ? THEN 0 ELSE 1 END ASC", [$userId]);
        }
        $query->orderByRaw("CASE WHEN callback_scheduled_at IS NOT NULL AND callback_scheduled_at <= ? THEN 0 ELSE 1 END ASC", [$now])
              ->orderByRaw("CASE WHEN telemarketing_attempt_count = 0 THEN 0 ELSE 1 END ASC")
              ->orderByRaw("last_contacted_at IS NULL DESC")
              ->orderBy('last_contacted_at', 'asc');
        return $query->paginate($perPage)->withQueryString();
    }
    /**
     * Get the next shipment to call for a telemarketer (auto-advance).
     * Respects queue mode and per-agent status assignments.
     */
    public function getNextCall(int $userId, int $companyId, ?int $excludeShipmentId = null): ?Shipment
    {
        $settings = \App\Models\CompanyTelemarketingSetting::getOrCreate($companyId);
        $queueMode = $settings->queue_mode;

        $query = Shipment::with(['status', 'lastDisposition'])
            ->forCompany($companyId)
            ->telemarketable();

        // Apply queue mode filter
        if ($queueMode === 'pre_assigned') {
            $query->assignedTo($userId);
        } elseif ($queueMode === 'shared_queue') {
            // Grab any available shipment — no assignment filter
        } elseif ($queueMode === 'hybrid') {
            // Prefer own assigned, then unassigned pool
            $query->where(function ($q) use ($userId) {
                $q->where('assigned_to_user_id', $userId)
                  ->orWhereNull('assigned_to_user_id');
            });
        }

        // Apply per-agent status filter
        $this->applyAgentStatusFilter($query, $userId);
        if ($excludeShipmentId) {
            $query->where('id', '!=', $excludeShipmentId);
        }
        // Priority: own assigned first (hybrid), callbacks due > never contacted > oldest
        $now = now()->toDateTimeString();
        if ($queueMode === 'hybrid') {
            $query->orderByRaw("CASE WHEN assigned_to_user_id = ? THEN 0 ELSE 1 END ASC", [$userId]);
        }
        $shipment = $query
            ->orderByRaw("CASE WHEN callback_scheduled_at IS NOT NULL AND callback_scheduled_at <= ? THEN 0 ELSE 1 END ASC", [$now])
            ->orderByRaw("CASE WHEN telemarketing_attempt_count = 0 THEN 0 ELSE 1 END ASC")
            ->orderByRaw("last_contacted_at IS NULL DESC")
            ->orderBy('last_contacted_at', 'asc')
            ->first();

        // For shared_queue and hybrid: auto-assign the grabbed shipment to this user
        if ($shipment && $queueMode !== 'pre_assigned' && $shipment->assigned_to_user_id !== $userId) {
            $shipment->update(['assigned_to_user_id' => $userId]);
            $shipment->refresh();
        }

        return $shipment;
    }

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
        TelemarketerStatusAssignment::where('user_id', $userId)
            ->where('company_id', $companyId)
            ->delete();

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

        // Check if a draft row already exists (created when user clicked call button)
        $existingDraft = TelemarketingLog::where('shipment_id', $shipmentId)
            ->where('user_id', $userId)
            ->where('status', 'draft')
            ->first();

        if ($existingDraft) {
            // Update the existing draft row instead of creating a new one
            $existingDraft->update([
                'status' => 'completed',
                'disposition_id' => $dispositionId,
                'notes' => $notes,
                'callback_at' => $callbackAt,
                'phone_called' => $phoneCalled ?? $existingDraft->phone_called ?? $shipment->consignee_phone_1,
                'call_duration_seconds' => $callDurationSeconds ?? $existingDraft->call_duration_seconds,
            ]);
            $log = $existingDraft;

            Log::info('Draft log finalized', [
                'log_id' => $log->id,
                'shipment_id' => $shipmentId,
                'has_recording' => !empty($log->recording_path),
            ]);
        } else {
            // No draft exists — create a new completed row (fallback for non-Android usage)
            $log = TelemarketingLog::create([
                'shipment_id' => $shipmentId,
                'user_id' => $userId,
                'status' => 'completed',
                'disposition_id' => $dispositionId,
                'notes' => $notes,
                'attempt_no' => $shipment->telemarketing_attempt_count + 1,
                'callback_at' => $callbackAt,
                'phone_called' => $phoneCalled ?? $shipment->consignee_phone_1,
                'call_duration_seconds' => $callDurationSeconds,
                'call_started_at' => now(),
            ]);

            Log::info('New completed log created (no draft)', [
                'log_id' => $log->id,
                'shipment_id' => $shipmentId,
            ]);
        }

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
    //  STATUS CHANGE HANDLING (Auto-Reassign)
    // ────────────────────────────────────────────────────────────────

    /**
     * Handle a shipment status change — apply transition rules.
     * Called after import updates a shipment's status.
     *
     * @return string The action taken: 'reassigned', 'unassigned', 'completed', 'no_action', 'skipped'
     */
    public function handleStatusChange(Shipment $shipment, ?int $oldStatusId, ?int $newStatusId): string
    {
        // No change
        if ($oldStatusId === $newStatusId) return 'no_action';

        // Not assigned — nothing to do
        if (!$shipment->assigned_to_user_id) return 'no_action';

        // Check if the last disposition allows re-calling
        $shipment->load('lastDisposition');
        if (!$shipment->isRecallableOnStatusChange()) {
            Log::info("Shipment #{$shipment->id} ({$shipment->waybill_no}): Status changed but last disposition is not recallable. Keeping as completed.");
            return 'skipped';
        }

        // Find matching transition rule
        $rule = StatusTransitionRule::findMatchingRule($shipment->company_id, $oldStatusId, $newStatusId);

        if (!$rule) {
            // Default behavior: auto-reassign if agent doesn't handle the new status
            return $this->defaultStatusChangeHandler($shipment, $newStatusId);
        }

        return $this->applyTransitionRule($shipment, $rule);
    }

    /**
     * Default handler when no specific transition rule exists.
     * Checks if the current agent handles the new status; if not, unassigns.
     */
    protected function defaultStatusChangeHandler(Shipment $shipment, ?int $newStatusId): string
    {
        if (!$newStatusId) return 'no_action';

        $allowedStatusIds = $this->getAgentAllowedStatusIds($shipment->assigned_to_user_id);

        // Agent has no restrictions — keep assignment
        if ($allowedStatusIds === null) return 'no_action';

        // Agent handles this new status — keep assignment
        if (in_array($newStatusId, $allowedStatusIds)) return 'no_action';

        // Agent does NOT handle this new status — unassign for re-assignment
        $shipment->update([
            'assigned_to_user_id' => null,
            'assigned_at' => null,
            'telemarketing_status' => 'pending',
        ]);

        Log::info("Shipment #{$shipment->id} ({$shipment->waybill_no}): Auto-unassigned — agent no longer handles status #{$newStatusId}.");
        return 'reassigned';
    }

    /**
     * Apply a specific transition rule to a shipment.
     */
    protected function applyTransitionRule(Shipment $shipment, StatusTransitionRule $rule): string
    {
        switch ($rule->action) {
            case 'auto_reassign':
                $updateData = [
                    'assigned_to_user_id' => null,
                    'assigned_at' => null,
                    'telemarketing_status' => 'pending',
                ];

                if ($rule->reset_attempts) {
                    $updateData['telemarketing_attempt_count'] = 0;
                    $updateData['last_contacted_at'] = null;
                    $updateData['last_disposition_id'] = null;
                    $updateData['callback_scheduled_at'] = null;
                }

                if ($rule->cooldown_days > 0) {
                    $updateData['telemarketing_cooldown_until'] = now()->addDays($rule->cooldown_days);
                }

                $shipment->update($updateData);
                Log::info("Shipment #{$shipment->id}: auto_reassign applied (reset={$rule->reset_attempts}, cooldown={$rule->cooldown_days}d).");
                return 'reassigned';

            case 'auto_unassign':
                $shipment->update([
                    'assigned_to_user_id' => null,
                    'assigned_at' => null,
                    'telemarketing_status' => 'pending',
                ]);
                Log::info("Shipment #{$shipment->id}: auto_unassign applied — no call needed.");
                return 'unassigned';

            case 'mark_completed':
                $shipment->update([
                    'assigned_to_user_id' => null,
                    'assigned_at' => null,
                    'telemarketing_status' => 'completed',
                ]);
                Log::info("Shipment #{$shipment->id}: mark_completed applied.");
                return 'completed';

            case 'no_action':
            default:
                return 'no_action';
        }
    }

    /**
     * Process all status changes for a batch of shipments after import.
     * Returns summary of actions taken.
     */
    public function processStatusChangesAfterImport(int $companyId): array
    {
        $summary = ['reassigned' => 0, 'unassigned' => 0, 'completed' => 0, 'skipped' => 0, 'no_action' => 0];

        // Find shipments where status changed (previous_status_id != normalized_status_id)
        $shipments = Shipment::forCompany($companyId)
            ->whereNotNull('assigned_to_user_id')
            ->whereNotNull('previous_status_id')
            ->whereColumn('previous_status_id', '!=', 'normalized_status_id')
            ->get();

        foreach ($shipments as $shipment) {
            $action = $this->handleStatusChange($shipment, $shipment->previous_status_id, $shipment->normalized_status_id);
            $summary[$action] = ($summary[$action] ?? 0) + 1;

            // Clear the previous_status_id after processing
            $shipment->update(['previous_status_id' => null]);
        }

        Log::info("Post-import status change processing for company #{$companyId}: " . json_encode($summary));
        return $summary;
    }

    // ────────────────────────────────────────────────────────────────
    //  ASSIGNMENT
    // ────────────────────────────────────────────────────────────────

    /**
     * Auto-assign shipments to telemarketers based on configured rules.
     * Respects per-agent status assignments and active toggle.
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
     * Respects per-agent status assignments and active toggle.
     */
    protected function executeAssignmentRule(TelemarketingAssignmentRule $rule, int $companyId): int
    {
        $telemarketers = $this->getAvailableTelemarketers($companyId);
        if ($telemarketers->isEmpty()) return 0;

        $query = Shipment::forCompany($companyId)
            ->unassigned()
            ->contactable()
            ->where('telemarketing_status', '!=', 'completed')
            ->where('telemarketing_status', '!=', 'do_not_call')
            // Respect cooldown
            ->where(function ($q) {
                $q->whereNull('telemarketing_cooldown_until')
                  ->orWhere('telemarketing_cooldown_until', '<=', now());
            });

        // Apply rule-specific filters
        switch ($rule->rule_type) {
            case 'status_based':
                if ($rule->status_id) {
                    $query->where('normalized_status_id', $rule->status_id);
                }
                break;

            case 'delivered_age':
                $deliveredStatusId = \App\Models\ShipmentStatus::where('code', 'delivered')->value('id');
                if ($deliveredStatusId) {
                    $query->where('normalized_status_id', $deliveredStatusId)
                          ->where('signing_time', '<=', now()->subDays($rule->days_threshold ?? 7));
                }
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
     */
    protected function getEligibleTelemarketers($telemarketers, int $shipmentStatusId, array $agentStatusMap): array
    {
        $eligible = [];

        foreach ($telemarketers as $tm) {
            if (isset($agentStatusMap[$tm->id])) {
                if (in_array($shipmentStatusId, $agentStatusMap[$tm->id])) {
                    $eligible[] = $tm;
                }
            } else {
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
        $rrIndexes = [];

        foreach ($shipments as $shipment) {
            $eligible = $this->getEligibleTelemarketers($telemarketers, $shipment->normalized_status_id, $agentStatusMap);

            if (empty($eligible)) continue;

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
     * STRICT MODE: Validates that shipment statuses match agent's assigned statuses.
     *
     * @throws \InvalidArgumentException if status mismatch detected
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
     * Validate that a manual assignment is allowed (strict mode).
     * Returns an error message if not allowed, or null if OK.
     */
    public function validateManualAssign(int $userId, ?int $statusId, int $companyId): ?string
    {
        $allowedStatusIds = $this->getAgentAllowedStatusIds($userId);

        // No restrictions — always allowed
        if ($allowedStatusIds === null) return null;

        // No status filter specified — check if there are unassigned shipments with non-matching statuses
        if (!$statusId) {
            return 'This agent has specific status assignments. Please select a status filter that matches their assigned statuses.';
        }

        // Check if the selected status matches the agent's assigned statuses
        if (!in_array($statusId, $allowedStatusIds)) {
            $agent = User::find($userId);
            $agentName = $agent ? $agent->name : 'This agent';
            $allowedNames = \App\Models\ShipmentStatus::whereIn('id', $allowedStatusIds)->pluck('name')->join(', ');
            return "{$agentName} only handles: {$allowedNames}. The selected status does not match.";
        }

        return null;
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
     * Redistribute shipments from one agent to other active agents.
     * Used when an agent is set to inactive.
     */
    public function redistributeFromAgent(int $fromUserId, int $companyId): array
    {
        $shipments = Shipment::forCompany($companyId)
            ->assignedTo($fromUserId)
            ->whereIn('telemarketing_status', ['pending', 'in_progress'])
            ->get();

        if ($shipments->isEmpty()) {
            return ['redistributed' => 0, 'unassigned' => 0];
        }

        $activeTelemarketers = $this->getAvailableTelemarketers($companyId)
            ->filter(fn($tm) => $tm->id !== $fromUserId);

        if ($activeTelemarketers->isEmpty()) {
            // No active agents to redistribute to — just unassign
            $count = Shipment::forCompany($companyId)
                ->assignedTo($fromUserId)
                ->whereIn('telemarketing_status', ['pending', 'in_progress'])
                ->update([
                    'assigned_to_user_id' => null,
                    'assigned_at' => null,
                    'telemarketing_status' => 'pending',
                ]);

            return ['redistributed' => 0, 'unassigned' => $count];
        }

        $agentStatusMap = $this->getAllAgentStatusAssignments($companyId);
        $redistributed = 0;
        $unassigned = 0;

        // Use workload-based for redistribution
        $workloads = Shipment::forCompany($companyId)
            ->whereIn('assigned_to_user_id', $activeTelemarketers->pluck('id'))
            ->whereIn('telemarketing_status', ['pending', 'in_progress'])
            ->groupBy('assigned_to_user_id')
            ->select('assigned_to_user_id', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'assigned_to_user_id')
            ->toArray();

        foreach ($shipments as $shipment) {
            $eligible = $this->getEligibleTelemarketers($activeTelemarketers, $shipment->normalized_status_id, $agentStatusMap);

            if (empty($eligible)) {
                // No eligible agent — unassign
                $shipment->update([
                    'assigned_to_user_id' => null,
                    'assigned_at' => null,
                    'telemarketing_status' => 'pending',
                ]);
                $unassigned++;
                continue;
            }

            // Find agent with lowest workload
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
                ]);
                $workloads[$assignTo] = ($workloads[$assignTo] ?? 0) + 1;
                $redistributed++;
            }
        }

        return ['redistributed' => $redistributed, 'unassigned' => $unassigned];
    }

    /**
     * Get available telemarketers for a company (active + telemarketing active).
     */
    public function getAvailableTelemarketers(int $companyId)
    {
        return User::forCompany($companyId)
            ->active()
            ->telemarketingActive()
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

        $assignedStatuses = $allowedStatusIds
            ? \App\Models\ShipmentStatus::whereIn('id', $allowedStatusIds)->pluck('name')->toArray()
            : ['All Statuses'];

        // Pending in queue (assigned but not yet called today / still needs follow-up)
        $pendingInQueue = (clone $baseQuery)
            ->when($allowedStatusIds, fn($q) => $q->whereIn('normalized_status_id', $allowedStatusIds))
            ->where(function ($q) {
                $q->where('telemarketing_status', 'pending')
                  ->orWhere('telemarketing_status', 'in_progress');
            })
            ->count();

        // Confirmation rate today (confirmed / total calls with final disposition today)
        $confirmedToday = TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', $today)
            ->whereHas('disposition', fn ($q) => $q->where('name', 'like', '%Accept%')->orWhere('name', 'like', '%Confirm%'))
            ->count();
        $confirmationRate = $callsToday > 0 ? round(($confirmedToday / $callsToday) * 100, 1) : 0;

        // Average calls per day this week
        $weekStart = now()->startOfWeek();
        $daysWorked = TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', $weekStart)
            ->select(DB::raw('DATE(created_at) as call_date'))
            ->groupBy('call_date')
            ->get()
            ->count();
        $totalCallsThisWeek = TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', $weekStart)
            ->count();
        $avgCallsPerDay = $daysWorked > 0 ? round($totalCallsThisWeek / $daysWorked) : 0;

        // Daily target progress (calls today vs total assigned as rough target)
        $dailyTarget = $totalAssigned > 0 ? min($totalAssigned, 100) : 100; // Cap at 100 as reasonable daily target
        $progressPercent = $dailyTarget > 0 ? min(round(($callsToday / $dailyTarget) * 100, 1), 100) : 0;

        return [
            'total_assigned' => $totalAssigned,
            'calls_today' => $callsToday,
            'callbacks_due' => $callbacksDue,
            'completed_today' => $completedToday,
            'never_contacted' => $neverContacted,
            'disposition_breakdown' => $dispositionBreakdown,
            'assigned_statuses' => $assignedStatuses,
            'pending_in_queue' => $pendingInQueue,
            'confirmed_today' => $confirmedToday,
            'confirmation_rate' => $confirmationRate,
            'avg_calls_per_day' => $avgCallsPerDay,
            'total_calls_this_week' => $totalCallsThisWeek,
            'progress_percent' => $progressPercent,
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

        $onCooldown = Shipment::forCompany($companyId)
            ->onCooldown()
            ->count();

        $callsToday = TelemarketingLog::whereHas('shipment', fn ($q) => $q->forCompany($companyId))
            ->where('created_at', '>=', $today)
            ->count();

        // Per-telemarketer stats — include ALL telemarketers (active + inactive)
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

        $agentStatusMap = $this->getAllAgentStatusAssignments($companyId);

        return [
            'total_pending' => $totalPending,
            'total_in_progress' => $totalInProgress,
            'total_completed' => $totalCompleted,
            'total_unassigned' => $totalUnassigned,
            'on_cooldown' => $onCooldown,
            'calls_today' => $callsToday,
            'telemarketers' => $telemarketers,
            'agent_status_map' => $agentStatusMap,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    //  DISPOSITIONS
    // ────────────────────────────────────────────────────────────────

    public function getDispositions(?int $companyId)
    {
        return TelemarketingDisposition::forCompany($companyId)
            ->orderBy('sort_order')
            ->get();
    }

    public function createDisposition(int $companyId, array $data): TelemarketingDisposition
    {
        return TelemarketingDisposition::create(array_merge($data, [
            'company_id' => $companyId,
            'is_system' => false,
        ]));
    }

    public function updateDisposition(TelemarketingDisposition $disposition, array $data): TelemarketingDisposition
    {
        $disposition->update($data);
        return $disposition->fresh();
    }

    public function deleteDisposition(TelemarketingDisposition $disposition): bool
    {
        if ($disposition->is_system) {
            return false;
        }
        return $disposition->delete();
    }

    // ────────────────────────────────────────────────────────────────
    //  TRANSITION RULES
    // ────────────────────────────────────────────────────────────────

    public function getTransitionRules(int $companyId)
    {
        return StatusTransitionRule::forCompany($companyId)
            ->with(['fromStatus', 'toStatus'])
            ->orderBy('priority', 'desc')
            ->get();
    }

    public function createTransitionRule(int $companyId, array $data): StatusTransitionRule
    {
        return StatusTransitionRule::create(array_merge($data, [
            'company_id' => $companyId,
        ]));
    }

    public function deleteTransitionRule(StatusTransitionRule $rule): bool
    {
        return $rule->delete();
    }
}
