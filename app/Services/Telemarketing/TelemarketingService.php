<?php

namespace App\Services\Telemarketing;

use App\Models\Shipment;
use App\Models\StatusTransitionRule;
use App\Models\TelemarketerStatusAssignment;
use App\Models\TelemarketingAssignmentRule;
use App\Models\TelemarketingDisposition;
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
    ): \App\Models\TelemarketingLog {
        $shipment = Shipment::findOrFail($shipmentId);
        $disposition = TelemarketingDisposition::findOrFail($dispositionId);

        // Check if a draft row already exists (created when user clicked call button)
        $existingDraft = \App\Models\TelemarketingLog::where('shipment_id', $shipmentId)
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
            $log = \App\Models\TelemarketingLog::create([
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

        if ($callbackAt) {
            // Keep status as in_progress (not 'callback' which is not a valid enum value).
            // The callback_scheduled_at column tracks when to call back.
            // Queue ordering automatically prioritises callbacks that are due.
            if ($newStatus === 'in_progress' || $newStatus === 'pending') {
                $newStatus = 'in_progress';
            }
            $callbackScheduledAt = $callbackAt;
        }

        // Update shipment
        $shipment->update([
            'telemarketing_status' => $newStatus,
            'last_disposition_id' => $dispositionId,
            'last_contacted_at' => now(),
            'callback_scheduled_at' => $callbackScheduledAt,
            'telemarketing_attempt_count' => $shipment->telemarketing_attempt_count + 1,
            'is_do_not_contact' => $disposition->marks_do_not_call,
            'telemarketing_cooldown_until' => $disposition->cooldown_hours > 0
                ? now()->addHours($disposition->cooldown_hours)
                : null,
        ]);

        return $log;
    }

    /**
     * Get call history for a specific shipment.
     */
    public function getCallHistory(int $shipmentId): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Models\TelemarketingLog::with(['user', 'disposition'])
            ->where('shipment_id', $shipmentId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ────────────────────────────────────────────────────────────────
    //  STATUS TRANSITIONS
    // ────────────────────────────────────────────────────────────────

    /**
     * Get all status transition rules for a company.
     */
    public function getStatusTransitionRules(int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        return StatusTransitionRule::with(['fromStatus', 'toStatus', 'disposition'])
            ->where('company_id', $companyId)
            ->get();
    }

    /**
     * Create or update a status transition rule.
     */
    public function updateStatusTransitionRule(
        int $companyId,
        int $fromStatusId,
        int $toStatusId,
        int $dispositionId
    ): StatusTransitionRule {
        return StatusTransitionRule::updateOrCreate(
            [
                'company_id' => $companyId,
                'from_status_id' => $fromStatusId,
                'disposition_id' => $dispositionId,
            ],
            ['to_status_id' => $toStatusId]
        );
    }

    /**
     * Delete a status transition rule.
     */
    public function deleteStatusTransitionRule(int $ruleId, int $companyId): void
    {
        StatusTransitionRule::where('id', $ruleId)
            ->where('company_id', $companyId)
            ->delete();
    }

    /**
     * Apply status transition rules after a call is logged.
     */
    public function applyStatusTransition(int $shipmentId, int $dispositionId): ?Shipment
    {
        $shipment = Shipment::findOrFail($shipmentId);

        $rule = StatusTransitionRule::where('company_id', $shipment->company_id)
            ->where('from_status_id', $shipment->normalized_status_id)
            ->where('disposition_id', $dispositionId)
            ->first();

        if ($rule) {
            $shipment->update(['normalized_status_id' => $rule->to_status_id]);
            Log::info('Status transition rule applied', [
                'shipment_id' => $shipmentId,
                'from_status' => $rule->from_status_id,
                'to_status' => $rule->to_status_id,
                'disposition' => $dispositionId,
            ]);
            return $shipment->fresh();
        }

        return null;
    }

    // ────────────────────────────────────────────────────────────────
    //  AUTO-ASSIGNMENT
    // ────────────────────────────────────────────────────────────────

    /**
     * Get all auto-assignment rules for a company.
     */
    public function getAssignmentRules(int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        return TelemarketingAssignmentRule::with(['status', 'user'])
            ->where('company_id', $companyId)
            ->get();
    }

    /**
     * Create or update an auto-assignment rule.
     */
    public function updateAssignmentRule(
        int $companyId,
        int $statusId,
        ?int $userId = null
    ): TelemarketingAssignmentRule {
        return TelemarketingAssignmentRule::updateOrCreate(
            [
                'company_id' => $companyId,
                'shipment_status_id' => $statusId,
            ],
            ['assigned_to_user_id' => $userId]
        );
    }

    /**
     * Delete an auto-assignment rule.
     */
    public function deleteAssignmentRule(int $ruleId, int $companyId): void
    {
        TelemarketingAssignmentRule::where('id', $ruleId)
            ->where('company_id', $companyId)
            ->delete();
    }

    /**
     * Apply auto-assignment rules to a single shipment.
     * Typically called when a shipment's status changes.
     */
    public function applyAssignmentRules(Shipment $shipment): ?Shipment
    {
        // Don't re-assign if it's already assigned to someone
        if ($shipment->assigned_to_user_id !== null) {
            return null;
        }

        // Don't re-assign if the last disposition was final and not recallable
        if (!$shipment->isRecallableOnStatusChange()) {
            Log::info('Auto-assignment skipped: Shipment has a final, non-recallable disposition.', [
                'shipment_id' => $shipment->id,
                'last_disposition_id' => $shipment->last_disposition_id,
            ]);
            return null;
        }

        $rule = TelemarketingAssignmentRule::where('company_id', $shipment->company_id)
            ->where('shipment_status_id', $shipment->normalized_status_id)
            ->first();

        if ($rule && $rule->assigned_to_user_id) {
            $shipment->update(['assigned_to_user_id' => $rule->assigned_to_user_id]);
            Log::info('Auto-assignment rule applied', [
                'shipment_id' => $shipment->id,
                'status_id' => $shipment->normalized_status_id,
                'assigned_to' => $rule->assigned_to_user_id,
            ]);
            return $shipment->fresh();
        }

        return null;
    }

    /**
     * Run auto-assignment for all unassigned shipments in a company.
     * This is a batch process, can be run periodically.
     */
    public function runBatchAssignment(int $companyId): int
    {
        $rules = $this->getAssignmentRules($companyId)->keyBy('shipment_status_id');
        if ($rules->isEmpty()) {
            return 0;
        }

        $unassignedShipments = Shipment::forCompany($companyId)
            ->unassigned()
            ->telemarketable()
            ->whereIn('normalized_status_id', $rules->keys())
            ->get();

        $count = 0;
        foreach ($unassignedShipments as $shipment) {
            $rule = $rules->get($shipment->normalized_status_id);
            if ($rule && $rule->assigned_to_user_id) {
                // Check if this shipment is recallable before assigning
                if ($shipment->isRecallableOnStatusChange()) {
                    $shipment->update(['assigned_to_user_id' => $rule->assigned_to_user_id]);
                    $count++;
                }
            }
        }

        if ($count > 0) {
            Log::info("Batch auto-assignment complete for company {$companyId}", [
                'assigned_count' => $count,
            ]);
        }

        return $count;
    }

    // ────────────────────────────────────────────────────────────────
    //  DISPOSITIONS
    // ────────────────────────────────────────────────────────────────

    /**
     * Get all dispositions for a company.
     */
    public function getDispositions(int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        return TelemarketingDisposition::where('company_id', $companyId)
            ->orWhereNull('company_id') // Include global dispositions
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new disposition for a company.
     */
    public function createDisposition(int $companyId, array $data): TelemarketingDisposition
    {
        $data['company_id'] = $companyId;
        return TelemarketingDisposition::create($data);
    }

    /**
     * Update an existing disposition.
     */
    public function updateDisposition(int $dispositionId, int $companyId, array $data): TelemarketingDisposition
    {
        $disposition = TelemarketingDisposition::where('id', $dispositionId)
            ->where('company_id', $companyId) // Ensure they can only edit their own
            ->firstOrFail();

        $disposition->update($data);
        return $disposition;
    }

    /**
     * Delete a disposition.
     */
    public function deleteDisposition(int $dispositionId, int $companyId): void
    {
        // Prevent deleting if it's in use by transition rules or logs
        $isUsedInRules = StatusTransitionRule::where('disposition_id', $dispositionId)->exists();
        $isUsedInLogs = \App\Models\TelemarketingLog::where('disposition_id', $dispositionId)->exists();

        if ($isUsedInRules || $isUsedInLogs) {
            throw new \Exception('Cannot delete disposition as it is currently in use.');
        }

        TelemarketingDisposition::where('id', $dispositionId)
            ->where('company_id', $companyId)
            ->delete();
    }

    // ────────────────────────────────────────────────────────────────
    //  STATS & DASHBOARD
    // ────────────────────────────────────────────────────────────────

    /**
     * Get dashboard stats for a single telemarketer.
     */
    public function getAgentStats(int $userId, int $companyId): array
    {
        $today = now()->startOfDay();
        $baseQuery = Shipment::forCompany($companyId)->assignedTo($userId);

        // Get agent's allowed statuses
        $allowedStatusIds = $this->getAgentAllowedStatusIds($userId);

        $totalAssigned = (clone $baseQuery)->when($allowedStatusIds, fn($q) => $q->whereIn('normalized_status_id', $allowedStatusIds))->count();

        $callsToday = \App\Models\TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', $today)
            ->count();

        $callbacksDue = Shipment::forCompany($companyId)
            ->assignedTo($userId)
            ->callbackDue()
            ->when($allowedStatusIds, fn($q) => $q->whereIn('normalized_status_id', $allowedStatusIds))
            ->count();

        $completedToday = \App\Models\TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', $today)
            ->whereHas('disposition', fn ($q) => $q->where('is_final', true))
            ->count();

        $neverContacted = (clone $baseQuery)
            ->neverContacted()
            ->when($allowedStatusIds, fn($q) => $q->whereIn('normalized_status_id', $allowedStatusIds))
            ->count();

        $dispositionBreakdown = \App\Models\TelemarketingLog::where('user_id', $userId)
            ->where('telemarketing_logs.created_at', '>=', $today)
            ->join('telemarketing_dispositions', 'telemarketing_logs.disposition_id', '=', 'telemarketing_dispositions.id')
            ->groupBy('telemarketing_dispositions.name', 'telemarketing_dispositions.color')
            ->select(
                'telemarketing_dispositions.name as disposition_name',
                'telemarketing_dispositions.color as disposition_color',
                DB::raw('COUNT(*) as count')
            )
            ->get();

        // Total calls today with a final disposition
        $finalCallsToday = \App\Models\TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', $today)
            ->whereHas('disposition', function ($q) {
                $q->where('is_final', true);
            })
            ->count();

        // Confirmation rate today (confirmed / total calls with final disposition today)
        $confirmedToday = \App\Models\TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', $today)
            ->whereHas('disposition', fn ($q) => $q->where('name', 'like', '%Accept%')->orWhere('name', 'like', '%Confirm%'))
            ->count();
        $confirmationRate = $callsToday > 0 ? round(($confirmedToday / $callsToday) * 100, 1) : 0;

        // Average calls per day this week
        $weekStart = now()->startOfWeek();
        $daysWorked = \App\Models\TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', $weekStart)
            ->select(DB::raw('DATE(created_at) as call_date'))
            ->groupBy('call_date')
            ->get()
            ->count();
        $totalCallsThisWeek = \App\Models\TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', $weekStart)
            ->count();
        $avgCallsPerDay = $daysWorked > 0 ? round($totalCallsThisWeek / $daysWorked) : 0;

        return [
            'total_assigned' => $totalAssigned,
            'calls_today' => $callsToday,
            'callbacks_due' => $callbacksDue,
            'completed_today' => $completedToday,
            'never_contacted' => $neverContacted,
            'disposition_breakdown' => $dispositionBreakdown,
            'confirmation_rate_today' => $confirmationRate,
            'avg_calls_per_day_this_week' => $avgCallsPerDay,
        ];
    }

    /**
     * Get dashboard stats for the entire company.
     */
    public function getCompanyStats(int $companyId): array
    {
        $today = now()->startOfDay();

        // Counts by telemarketing_status for stat cards
        $totalUnassigned = Shipment::forCompany($companyId)->unassigned()
            ->whereIn('telemarketing_status', ['pending', 'in_progress'])
            ->count();
        $totalPending = Shipment::forCompany($companyId)
            ->where('telemarketing_status', 'pending')
            ->count();
        $totalInProgress = Shipment::forCompany($companyId)
            ->where('telemarketing_status', 'in_progress')
            ->count();
        $totalCompleted = Shipment::forCompany($companyId)
            ->where('telemarketing_status', 'completed')
            ->count();

        $callsToday = \App\Models\TelemarketingLog::whereHas('shipment', fn ($q) => $q->forCompany($companyId))
            ->where('created_at', '>=', $today)
            ->count();

        // Per-telemarketer stats — include ALL telemarketers (active + inactive)
        $telemarketers = User::where('company_id', $companyId)
            ->role('Telemarketer')
            ->withCount([
                'telemarketingLogs as calls_today_count' => fn ($q) => $q->where('created_at', '>=', $today),
                'telemarketingLogs as calls_this_week' => fn ($q) => $q->where('created_at', '>=', now()->startOfWeek()),
            ])
            ->get();

        // Add pending_count for each telemarketer
        foreach ($telemarketers as $tm) {
            $tm->pending_count = Shipment::forCompany($companyId)
                ->where('assigned_to_user_id', $tm->id)
                ->whereIn('telemarketing_status', ['pending', 'in_progress'])
                ->count();
        }

        // Build agent_status_map from assignment rules
        $agentStatusMap = [];
        $rules = \App\Models\TelemarketingAssignmentRule::forCompany($companyId)->active()->get();
        foreach ($rules as $rule) {
            // Rules may assign specific statuses to agents via related logic
            // For now, provide empty arrays ("All" statuses) per agent
        }

        return [
            'total_unassigned' => $totalUnassigned,
            'total_pending' => $totalPending,
            'total_in_progress' => $totalInProgress,
            'total_completed' => $totalCompleted,
            'calls_today' => $callsToday,
            'telemarketers' => $telemarketers,
            'agent_status_map' => $agentStatusMap,
        ];
    }

    /**
     * Get all status transition rules for a company.
     */
    public function getTransitionRules(int $companyId)
    {
        return StatusTransitionRule::forCompany($companyId)
            ->with(['fromStatus', 'toStatus'])
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Validate a manual assignment request.
     * Returns an error message string if invalid, or null if valid.
     */
    public function validateManualAssign(int $telemarketerId, ?int $statusId, int $companyId): ?string
    {
        // Verify the telemarketer belongs to this company and is active
        $telemarketer = User::where('id', $telemarketerId)
            ->where('company_id', $companyId)
            ->first();

        if (!$telemarketer) {
            return 'Telemarketer not found in this company.';
        }

        if (!$telemarketer->is_telemarketing_active) {
            return 'This telemarketer is currently inactive.';
        }

        // No strict status validation for now — allow assigning any status to any agent
        return null;
    }

    /**
     * Get stats for a telemarketer's personal dashboard.
     */
    public function getUserStats(int $userId, int $companyId): array
    {
        $totalAssigned = Shipment::forCompany($companyId)
            ->assignedTo($userId)
            ->whereIn('telemarketing_status', ['pending', 'in_progress'])
            ->count();

        $pendingInQueue = Shipment::forCompany($companyId)
            ->assignedTo($userId)
            ->telemarketable()
            ->count();

        $callsToday = \App\Models\TelemarketingLog::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();

        // "Confirmed" = dispositions with color green or name containing 'Will Accept' or 'Reorder'
        $confirmedToday = \App\Models\TelemarketingLog::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->whereHas('disposition', function ($q) {
                $q->where('color', 'green')
                  ->orWhere('color', 'emerald');
            })
            ->count();

        $callbacksDue = Shipment::forCompany($companyId)
            ->assignedTo($userId)
            ->whereNotNull('callback_scheduled_at')
            ->where('callback_scheduled_at', '<=', now())
            ->whereIn('telemarketing_status', ['pending', 'in_progress'])
            ->count();

        $neverContacted = Shipment::forCompany($companyId)
            ->assignedTo($userId)
            ->whereIn('telemarketing_status', ['pending', 'in_progress'])
            ->where('telemarketing_attempt_count', 0)
            ->count();

        $progressPercent = $totalAssigned > 0
            ? min(100, round(($callsToday / max($totalAssigned, 1)) * 100))
            : 0;

        $confirmationRate = $callsToday > 0
            ? round(($confirmedToday / $callsToday) * 100)
            : 0;

        $startOfWeek = now()->startOfWeek();
        $totalCallsThisWeek = \App\Models\TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', $startOfWeek)
            ->count();

        $daysWorkedThisWeek = \App\Models\TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', $startOfWeek)
            ->selectRaw('COUNT(DISTINCT DATE(created_at)) as days')
            ->value('days') ?: 1;

        $avgCallsPerDay = round($totalCallsThisWeek / $daysWorkedThisWeek);

        $completedToday = \App\Models\TelemarketingLog::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->whereHas('disposition', fn($q) => $q->where('is_final', true))
            ->count();

        $dispositionBreakdown = \App\Models\TelemarketingLog::where('telemarketing_logs.user_id', $userId)
            ->whereDate('telemarketing_logs.created_at', today())
            ->join('telemarketing_dispositions', 'telemarketing_logs.disposition_id', '=', 'telemarketing_dispositions.id')
            ->selectRaw('telemarketing_dispositions.name, telemarketing_dispositions.color, COUNT(*) as count')
            ->groupBy('telemarketing_dispositions.name', 'telemarketing_dispositions.color')
            ->orderByDesc('count')
            ->get();

        return [
            'total_assigned' => $totalAssigned,
            'pending_in_queue' => $pendingInQueue,
            'calls_today' => $callsToday,
            'confirmed_today' => $confirmedToday,
            'callbacks_due' => $callbacksDue,
            'never_contacted' => $neverContacted,
            'progress_percent' => $progressPercent,
            'confirmation_rate' => $confirmationRate,
            'avg_calls_per_day' => $avgCallsPerDay,
            'total_calls_this_week' => $totalCallsThisWeek,
            'completed_today' => $completedToday,
            'disposition_breakdown' => $dispositionBreakdown,
        ];
    }

    /**
     * Manually assign shipments to a telemarketer (called from ManualAssignJob).
     */
    public function manualAssign(array $shipmentIds, int $telemarketerUserId, int $companyId): int
    {
        $count = Shipment::forCompany($companyId)
            ->whereIn('id', $shipmentIds)
            ->update([
                'assigned_to_user_id' => $telemarketerUserId,
                'assigned_at' => now(),
                'telemarketing_status' => 'in_progress',
            ]);

        return $count;
    }

    /**
     * Unassign shipments from a telemarketer (called from UnassignShipmentsJob).
     */
    public function unassign(array $shipmentIds, int $companyId): int
    {
        $count = Shipment::forCompany($companyId)
            ->whereIn('id', $shipmentIds)
            ->update([
                'assigned_to_user_id' => null,
                'assigned_at' => null,
                'telemarketing_status' => 'pending',
            ]);

        return $count;
    }

    /**
     * Get pending callbacks for the company (for manager/owner dashboard).
     * Respects the company setting for showing all shipments or callbacks only.
     */
    public function getPendingCallbacks(int $companyId)
    {
        $settings = \App\Models\CompanyTelemarketingSetting::getOrCreate($companyId);
        $viewMode = $settings->pending_callbacks_view ?? 'callbacks_only';

        $query = Shipment::with(['status', 'lastDisposition', 'assignedTo'])
            ->forCompany($companyId);

        if ($viewMode === 'callbacks_only') {
            // Show only shipments with a scheduled callback
            $query->whereNotNull('callback_scheduled_at')
                  ->whereIn('telemarketing_status', ['pending', 'in_progress']);
        } else {
            // Show all telemarketable shipments
            $query->whereIn('telemarketing_status', ['pending', 'in_progress']);
        }

        return $query->orderByRaw("CASE WHEN callback_scheduled_at IS NOT NULL AND callback_scheduled_at <= ? THEN 0 ELSE 1 END ASC", [now()])
                     ->orderBy('callback_scheduled_at', 'asc')
                     ->paginate(50)
                     ->withQueryString();
    }
}
