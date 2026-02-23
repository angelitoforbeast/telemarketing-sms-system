<?php

namespace App\Http\Controllers\Telemarketing;

use App\Http\Controllers\Controller;
use App\Jobs\ManualAssignJob;
use App\Jobs\RunAutoAssignJob;
use App\Jobs\UnassignShipmentsJob;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\StatusTransitionRule;
use App\Models\TelemarketingAssignmentRule;
use App\Models\TelemarketingDisposition;
use App\Models\User;
use App\Services\Telemarketing\TelemarketingService;
use Illuminate\Http\Request;

class TelemarketingController extends Controller
{
    public function __construct(
        protected TelemarketingService $telemarketingService
    ) {}

    // ────────────────────────────────────────────────────────────────
    //  TELEMARKETER DASHBOARD
    // ────────────────────────────────────────────────────────────────

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        if ($user->hasRole('Telemarketer')) {
            $stats = $this->telemarketingService->getUserStats($user->id, $companyId);
            return view('telemarketing.dashboard', compact('stats'));
        }

        $stats = $this->telemarketingService->getCompanyStats($companyId);
        $rules = TelemarketingAssignmentRule::forCompany($companyId)->orderBy('priority', 'desc')->get();
        return view('telemarketing.manager-dashboard', compact('stats', 'rules'));
    }

    // ────────────────────────────────────────────────────────────────
    //  CALL QUEUE
    // ────────────────────────────────────────────────────────────────

    public function queue(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        if ($user->hasRole('Telemarketer')) {
            $shipments = $this->telemarketingService->getQueue($user->id, $companyId, $request->all());
            $statuses = ShipmentStatus::orderBy('sort_order')->get();
            return view('telemarketing.queue', compact('shipments', 'statuses'));
        }

        $telemarketerId = $request->input('telemarketer_id');
        if ($telemarketerId) {
            $shipments = $this->telemarketingService->getQueue($telemarketerId, $companyId, $request->all());
        } else {
            $shipments = Shipment::with(['status', 'lastDisposition', 'assignedTo'])
                ->forCompany($companyId)
                ->whereNotNull('assigned_to_user_id')
                ->telemarketable()
                ->orderBy('last_contacted_at', 'asc')
                ->paginate(25)
                ->withQueryString();
        }

        $statuses = ShipmentStatus::orderBy('sort_order')->get();
        $telemarketers = User::forCompany($companyId)->active()->role('Telemarketer')->get();
        return view('telemarketing.queue', compact('shipments', 'statuses', 'telemarketers'));
    }

    public function nextCall(Request $request)
    {
        $user = $request->user();
        $excludeId = $request->input('exclude');

        $next = $this->telemarketingService->getNextCall(
            $user->id,
            $user->company_id,
            $excludeId ? (int) $excludeId : null
        );

        if (!$next) {
            return redirect()->route('telemarketing.queue')
                ->with('info', 'No more shipments in your queue. Great job!');
        }

        return redirect()->route('telemarketing.call', $next);
    }

    // ────────────────────────────────────────────────────────────────
    //  CALL FORM
    // ────────────────────────────────────────────────────────────────

    public function callForm(Shipment $shipment)
    {
        $this->authorizeAssignment($shipment);

        $shipment->load(['status', 'telemarketingLogs.disposition', 'telemarketingLogs.user', 'lastDisposition']);

        $dispositions = $this->telemarketingService->getDispositions(auth()->user()->company_id);
        $callHistory = $this->telemarketingService->getCallHistory($shipment->id);

        $queueCount = Shipment::forCompany(auth()->user()->company_id)
            ->assignedTo(auth()->id())
            ->telemarketable()
            ->count();

        return view('telemarketing.call', compact('shipment', 'dispositions', 'callHistory', 'queueCount'));
    }

    public function logCall(Request $request, Shipment $shipment)
    {
        $this->authorizeAssignment($shipment);

        $request->validate([
            'disposition_id' => 'required|integer|exists:telemarketing_dispositions,id',
            'notes' => 'nullable|string|max:1000',
            'callback_at' => 'nullable|date|after:now',
            'phone_called' => 'nullable|string|max:20',
            'call_duration_seconds' => 'nullable|integer|min:0',
        ]);

        $this->telemarketingService->logCall(
            $shipment->id,
            $request->user()->id,
            $request->disposition_id,
            $request->notes,
            $request->callback_at,
            $request->phone_called,
            $request->call_duration_seconds
        );

        if ($request->input('action') === 'save_next') {
            return redirect()->route('telemarketing.next-call', ['exclude' => $shipment->id]);
        }

        return redirect()->route('telemarketing.queue')
            ->with('success', 'Call logged successfully for ' . $shipment->waybill_no);
    }

    // ────────────────────────────────────────────────────────────────
    //  ASSIGNMENT MANAGEMENT (Manager/Owner)
    // ────────────────────────────────────────────────────────────────

    public function assignments(Request $request)
    {
        $companyId = $request->user()->company_id;

        $telemarketers = User::forCompany($companyId)
            ->active()
            ->role('Telemarketer')
            ->withCount([
                'assignedShipments as pending_count' => function ($q) {
                    $q->whereIn('telemarketing_status', ['pending', 'in_progress']);
                },
                'assignedShipments as completed_count' => function ($q) {
                    $q->where('telemarketing_status', 'completed');
                },
            ])
            ->get();

        $unassignedCount = Shipment::forCompany($companyId)
            ->unassigned()
            ->contactable()
            ->whereIn('telemarketing_status', ['pending', 'in_progress'])
            ->count();

        $onCooldownCount = Shipment::forCompany($companyId)->onCooldown()->count();

        $statuses = ShipmentStatus::orderBy('sort_order')->get();
        $rules = TelemarketingAssignmentRule::forCompany($companyId)->orderBy('priority', 'desc')->get();
        $agentStatusMap = $this->telemarketingService->getAllAgentStatusAssignments($companyId);
        $transitionRules = $this->telemarketingService->getTransitionRules($companyId);

        return view('telemarketing.assignments', compact(
            'telemarketers', 'unassignedCount', 'onCooldownCount', 'statuses', 'rules', 'agentStatusMap', 'transitionRules'
        ));
    }

    /**
     * Update the assigned statuses for a telemarketer.
     */
    public function syncAgentStatuses(Request $request)
    {
        $request->validate([
            'telemarketer_id' => 'required|integer|exists:users,id',
            'status_ids' => 'nullable|array',
            'status_ids.*' => 'integer|exists:shipment_statuses,id',
        ]);

        $companyId = $request->user()->company_id;
        $statusIds = $request->input('status_ids', []);

        $this->telemarketingService->syncAgentStatuses(
            (int) $request->telemarketer_id,
            $companyId,
            $statusIds
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Status assignments updated successfully.',
                'status_ids' => $statusIds,
            ]);
        }

        return back()->with('success', 'Status assignments updated.');
    }

    /**
     * Toggle a telemarketer's active/inactive status for telemarketing.
     */
    public function toggleAgentActive(Request $request)
    {
        $request->validate([
            'telemarketer_id' => 'required|integer|exists:users,id',
        ]);

        $companyId = $request->user()->company_id;
        $agent = User::forCompany($companyId)->findOrFail($request->telemarketer_id);

        $newState = !$agent->is_telemarketing_active;
        $agent->update(['is_telemarketing_active' => $newState]);

        $statusLabel = $newState ? 'Active' : 'Inactive';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$agent->name} is now {$statusLabel} for telemarketing.",
                'is_active' => $newState,
            ]);
        }

        return back()->with('success', "{$agent->name} is now {$statusLabel} for telemarketing.");
    }

    /**
     * Redistribute shipments from an agent to other active agents.
     */
    public function redistributeAgent(Request $request)
    {
        $request->validate([
            'telemarketer_id' => 'required|integer|exists:users,id',
        ]);

        $companyId = $request->user()->company_id;
        $result = $this->telemarketingService->redistributeFromAgent(
            (int) $request->telemarketer_id,
            $companyId
        );

        $message = "Redistributed {$result['redistributed']} shipments.";
        if ($result['unassigned'] > 0) {
            $message .= " {$result['unassigned']} returned to unassigned pool (no eligible agents).";
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'result' => $result,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Run auto-assignment based on rules (dispatched to background queue).
     */
    public function runAutoAssign(Request $request)
    {
        $companyId = $request->user()->company_id;
        $ruleId = $request->input('rule_id');

        RunAutoAssignJob::dispatch($companyId, $ruleId ? (int) $ruleId : null);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $ruleId
                    ? 'Assignment rule is running in the background...'
                    : 'All assignment rules are running in the background...',
            ]);
        }

        return back()->with('success', 'Auto-assignment is running in the background.');
    }

    /**
     * Manual bulk assignment (dispatched to background queue).
     * STRICT MODE: Validates status match before assigning.
     */
    public function manualAssign(Request $request)
    {
        $request->validate([
            'telemarketer_id' => 'required|integer|exists:users,id',
            'status_id' => 'nullable|integer|exists:shipment_statuses,id',
            'courier' => 'nullable|in:jnt,flash',
            'limit' => 'nullable|integer|min:1|max:5000',
        ]);

        $companyId = $request->user()->company_id;

        // STRICT: Validate that the status matches the agent's assigned statuses
        $validationError = $this->telemarketingService->validateManualAssign(
            (int) $request->telemarketer_id,
            $request->status_id ? (int) $request->status_id : null,
            $companyId
        );

        if ($validationError) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $validationError,
                ], 422);
            }
            return back()->with('error', $validationError);
        }

        $query = Shipment::forCompany($companyId)
            ->unassigned()
            ->contactable()
            ->whereIn('telemarketing_status', ['pending', 'in_progress'])
            ->where(function ($q) {
                $q->whereNull('telemarketing_cooldown_until')
                  ->orWhere('telemarketing_cooldown_until', '<=', now());
            });

        if ($request->status_id) {
            $query->where('normalized_status_id', $request->status_id);
        }

        if ($request->courier) {
            $query->where('courier', $request->courier);
        }

        $limit = $request->limit ?? 100;
        $shipmentIds = $query->limit($limit)->pluck('id')->toArray();

        if (empty($shipmentIds)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No unassigned shipments match the criteria.',
                ]);
            }
            return back()->with('info', 'No unassigned shipments match the criteria.');
        }

        ManualAssignJob::dispatch($shipmentIds, (int) $request->telemarketer_id, $companyId);

        $count = count($shipmentIds);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Assigning {$count} shipments in the background...",
            ]);
        }

        return back()->with('success', "Assigning {$count} shipments in the background.");
    }

    /**
     * Unassign all shipments from a telemarketer (dispatched to background queue).
     */
    public function unassignAll(Request $request)
    {
        $request->validate([
            'telemarketer_id' => 'required|integer|exists:users,id',
        ]);

        $companyId = $request->user()->company_id;

        $shipmentIds = Shipment::forCompany($companyId)
            ->assignedTo($request->telemarketer_id)
            ->whereIn('telemarketing_status', ['pending', 'in_progress'])
            ->pluck('id')
            ->toArray();

        if (empty($shipmentIds)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending shipments to unassign.',
                ]);
            }
            return back()->with('info', 'No pending shipments to unassign.');
        }

        UnassignShipmentsJob::dispatch($shipmentIds, $companyId);

        $count = count($shipmentIds);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Unassigning {$count} shipments in the background...",
            ]);
        }

        return back()->with('success', "Unassigning {$count} shipments in the background.");
    }

    // ────────────────────────────────────────────────────────────────
    //  ASSIGNMENT RULES (CRUD)
    // ────────────────────────────────────────────────────────────────

    public function storeRule(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'rule_type' => 'required|in:status_based,delivered_age,custom',
            'status_id' => 'nullable|integer|exists:shipment_statuses,id',
            'days_threshold' => 'nullable|integer|min:1|max:365',
            'assignment_method' => 'required|in:round_robin,workload_based',
            'max_attempts' => 'required|integer|min:1|max:50',
            'priority' => 'nullable|integer|min:0|max:100',
        ]);

        TelemarketingAssignmentRule::create(array_merge(
            $request->only(['name', 'rule_type', 'status_id', 'days_threshold', 'assignment_method', 'max_attempts', 'priority']),
            ['company_id' => $request->user()->company_id, 'is_active' => true]
        ));

        return back()->with('success', 'Assignment rule created.');
    }

    public function toggleRule(TelemarketingAssignmentRule $rule)
    {
        if ($rule->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $rule->update(['is_active' => !$rule->is_active]);

        return back()->with('success', "Rule '{$rule->name}' " . ($rule->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function deleteRule(TelemarketingAssignmentRule $rule)
    {
        if ($rule->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $rule->delete();

        return back()->with('success', 'Assignment rule deleted.');
    }

    // ────────────────────────────────────────────────────────────────
    //  STATUS TRANSITION RULES (CRUD)
    // ────────────────────────────────────────────────────────────────

    public function storeTransitionRule(Request $request)
    {
        $request->validate([
            'from_status_id' => 'nullable|integer|exists:shipment_statuses,id',
            'to_status_id' => 'nullable|integer|exists:shipment_statuses,id',
            'action' => 'required|in:auto_reassign,auto_unassign,mark_completed,no_action',
            'reset_attempts' => 'boolean',
            'cooldown_days' => 'nullable|integer|min:0|max:365',
            'priority' => 'nullable|integer|min:0|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        $companyId = $request->user()->company_id;

        // Check for duplicate
        $exists = StatusTransitionRule::forCompany($companyId)
            ->where('from_status_id', $request->from_status_id)
            ->where('to_status_id', $request->to_status_id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'A rule for this status transition already exists.');
        }

        $this->telemarketingService->createTransitionRule($companyId, $request->only([
            'from_status_id', 'to_status_id', 'action', 'reset_attempts', 'cooldown_days', 'priority', 'description'
        ]));

        return back()->with('success', 'Status transition rule created.');
    }

    public function toggleTransitionRule(StatusTransitionRule $transitionRule)
    {
        if ($transitionRule->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $transitionRule->update(['is_active' => !$transitionRule->is_active]);

        return back()->with('success', 'Transition rule ' . ($transitionRule->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function deleteTransitionRule(StatusTransitionRule $transitionRule)
    {
        if ($transitionRule->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $this->telemarketingService->deleteTransitionRule($transitionRule);

        return back()->with('success', 'Transition rule deleted.');
    }

    // ────────────────────────────────────────────────────────────────
    //  DISPOSITIONS MANAGEMENT
    // ────────────────────────────────────────────────────────────────

    public function dispositions(Request $request)
    {
        $companyId = $request->user()->company_id;
        $dispositions = $this->telemarketingService->getDispositions($companyId);

        return view('telemarketing.dispositions', compact('dispositions'));
    }

    public function storeDisposition(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|alpha_dash',
            'color' => 'required|string|max:20',
            'description' => 'nullable|string|max:255',
            'is_final' => 'boolean',
            'requires_callback' => 'boolean',
            'marks_do_not_call' => 'boolean',
            'is_recallable_on_status_change' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $this->telemarketingService->createDisposition(
            $request->user()->company_id,
            $request->only(['name', 'code', 'color', 'description', 'is_final', 'requires_callback', 'marks_do_not_call', 'is_recallable_on_status_change', 'sort_order'])
        );

        return back()->with('success', 'Custom disposition created.');
    }

    public function deleteDisposition(TelemarketingDisposition $disposition)
    {
        if ($disposition->is_system) {
            return back()->with('error', 'System dispositions cannot be deleted.');
        }

        if ($disposition->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $this->telemarketingService->deleteDisposition($disposition);

        return back()->with('success', 'Disposition deleted.');
    }

    // ────────────────────────────────────────────────────────────────
    //  CALL LOGS (Manager View)
    // ────────────────────────────────────────────────────────────────

    public function callLogs(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = \App\Models\TelemarketingLog::with(['shipment', 'user', 'disposition'])
            ->whereHas('shipment', fn ($q) => $q->forCompany($companyId))
            ->orderBy('created_at', 'desc');

        if ($request->filled('telemarketer_id')) {
            $query->where('user_id', $request->telemarketer_id);
        }

        if ($request->filled('disposition_id')) {
            $query->where('disposition_id', $request->disposition_id);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $logs = $query->paginate(50)->withQueryString();

        $telemarketers = User::forCompany($companyId)->active()->role('Telemarketer')->get();
        $dispositions = $this->telemarketingService->getDispositions($companyId);

        return view('telemarketing.call-logs', compact('logs', 'telemarketers', 'dispositions'));
    }

    // ────────────────────────────────────────────────────────────────
    //  HELPERS
    // ────────────────────────────────────────────────────────────────

    protected function authorizeAssignment(Shipment $shipment): void
    {
        $user = auth()->user();

        if ($shipment->company_id !== $user->company_id) {
            abort(403);
        }

        if ($user->hasRole('Telemarketer') && $shipment->assigned_to_user_id !== $user->id) {
            abort(403, 'This shipment is not assigned to you.');
        }
    }
}
