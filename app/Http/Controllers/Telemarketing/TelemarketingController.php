<?php

namespace App\Http\Controllers\Telemarketing;

use App\Http\Controllers\Controller;
use App\Jobs\ManualAssignJob;
use App\Jobs\RunAutoAssignJob;
use App\Jobs\UnassignShipmentsJob;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
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

    /**
     * Telemarketer's personal dashboard with stats.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        // Telemarketer sees their own stats; managers see company overview
        if ($user->hasRole('Telemarketer')) {
            $stats = $this->telemarketingService->getUserStats($user->id, $companyId);
            return view('telemarketing.dashboard', compact('stats'));
        }

        // Manager / Owner / CEO view
        $stats = $this->telemarketingService->getCompanyStats($companyId);
        $rules = TelemarketingAssignmentRule::forCompany($companyId)->orderBy('priority', 'desc')->get();
        return view('telemarketing.manager-dashboard', compact('stats', 'rules'));
    }

    // ────────────────────────────────────────────────────────────────
    //  CALL QUEUE
    // ────────────────────────────────────────────────────────────────

    /**
     * Show the telemarketer's call queue.
     */
    public function queue(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        // Telemarketers see only their queue; managers can see all or filter by telemarketer
        if ($user->hasRole('Telemarketer')) {
            $shipments = $this->telemarketingService->getQueue($user->id, $companyId, $request->all());
            $statuses = ShipmentStatus::orderBy('sort_order')->get();
            return view('telemarketing.queue', compact('shipments', 'statuses'));
        }

        // Manager view: can filter by telemarketer
        $telemarketerId = $request->input('telemarketer_id');
        if ($telemarketerId) {
            $shipments = $this->telemarketingService->getQueue($telemarketerId, $companyId, $request->all());
        } else {
            // Show all assigned shipments across all telemarketers
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

    /**
     * "Next Call" — auto-advance to the next shipment in queue.
     */
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

    /**
     * Show the call form for a specific shipment.
     */
    public function callForm(Shipment $shipment)
    {
        $this->authorizeAssignment($shipment);

        $shipment->load(['status', 'telemarketingLogs.disposition', 'telemarketingLogs.user', 'lastDisposition']);

        $dispositions = $this->telemarketingService->getDispositions(auth()->user()->company_id);
        $callHistory = $this->telemarketingService->getCallHistory($shipment->id);

        // Get queue count for the progress indicator
        $queueCount = Shipment::forCompany(auth()->user()->company_id)
            ->assignedTo(auth()->id())
            ->telemarketable()
            ->count();

        return view('telemarketing.call', compact('shipment', 'dispositions', 'callHistory', 'queueCount'));
    }

    /**
     * Log a call attempt.
     */
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

        // If "Save & Next" was clicked, advance to next call
        if ($request->input('action') === 'save_next') {
            return redirect()->route('telemarketing.next-call', ['exclude' => $shipment->id]);
        }

        return redirect()->route('telemarketing.queue')
            ->with('success', 'Call logged successfully for ' . $shipment->waybill_no);
    }

    // ────────────────────────────────────────────────────────────────
    //  ASSIGNMENT MANAGEMENT (Manager/Owner)
    // ────────────────────────────────────────────────────────────────

    /**
     * Show the assignment management page.
     */
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

        $statuses = ShipmentStatus::orderBy('sort_order')->get();
        $rules = TelemarketingAssignmentRule::forCompany($companyId)->orderBy('priority', 'desc')->get();
        $agentStatusMap = $this->telemarketingService->getAllAgentStatusAssignments($companyId);

        return view('telemarketing.assignments', compact('telemarketers', 'unassignedCount', 'statuses', 'rules', 'agentStatusMap'));
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

        return back()->with('success', 'Auto-assignment is running in the background. Refresh in a few seconds to see results.');
    }

    /**
     * Manual bulk assignment (dispatched to background queue).
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

        $query = Shipment::forCompany($companyId)
            ->unassigned()
            ->contactable()
            ->whereIn('telemarketing_status', ['pending', 'in_progress']);

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

        return back()->with('success', "Assigning {$count} shipments in the background. Refresh in a few seconds to see results.");
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

        return back()->with('success', "Unassigning {$count} shipments in the background. Refresh in a few seconds.");
    }

    // ────────────────────────────────────────────────────────────────
    //  ASSIGNMENT RULES (CRUD)
    // ────────────────────────────────────────────────────────────────

    /**
     * Store a new assignment rule.
     */
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

    /**
     * Toggle an assignment rule active/inactive.
     */
    public function toggleRule(TelemarketingAssignmentRule $rule)
    {
        if ($rule->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $rule->update(['is_active' => !$rule->is_active]);

        return back()->with('success', "Rule '{$rule->name}' " . ($rule->is_active ? 'activated' : 'deactivated') . '.');
    }

    /**
     * Delete an assignment rule.
     */
    public function deleteRule(TelemarketingAssignmentRule $rule)
    {
        if ($rule->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $rule->delete();

        return back()->with('success', 'Assignment rule deleted.');
    }

    // ────────────────────────────────────────────────────────────────
    //  DISPOSITIONS MANAGEMENT
    // ────────────────────────────────────────────────────────────────

    /**
     * Show disposition management page.
     */
    public function dispositions(Request $request)
    {
        $companyId = $request->user()->company_id;
        $dispositions = $this->telemarketingService->getDispositions($companyId);

        return view('telemarketing.dispositions', compact('dispositions'));
    }

    /**
     * Store a new custom disposition.
     */
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
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $this->telemarketingService->createDisposition(
            $request->user()->company_id,
            $request->only(['name', 'code', 'color', 'description', 'is_final', 'requires_callback', 'marks_do_not_call', 'sort_order'])
        );

        return back()->with('success', 'Custom disposition created.');
    }

    /**
     * Delete a custom disposition.
     */
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

    /**
     * View all call logs for the company.
     */
    public function callLogs(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = \App\Models\TelemarketingLog::with(['shipment', 'user', 'disposition'])
            ->whereHas('shipment', fn ($q) => $q->forCompany($companyId))
            ->orderBy('created_at', 'desc');

        // Filter by telemarketer
        if ($request->filled('telemarketer_id')) {
            $query->where('user_id', $request->telemarketer_id);
        }

        // Filter by disposition
        if ($request->filled('disposition_id')) {
            $query->where('disposition_id', $request->disposition_id);
        }

        // Filter by date
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

        // Telemarketers can only access their own assigned shipments
        if ($user->hasRole('Telemarketer') && $shipment->assigned_to_user_id !== $user->id) {
            abort(403, 'This shipment is not assigned to you.');
        }
    }
}
