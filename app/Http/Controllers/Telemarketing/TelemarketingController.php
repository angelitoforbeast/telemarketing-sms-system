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
use App\Models\CompanyTelemarketingSetting;
use App\Models\StatusDispositionMapping;
use App\Models\User;
use App\Models\OrderType;
use App\Models\Order;
use App\Jobs\AnalyzeCallRecording;
use App\Services\Telemarketing\TelemarketingService;
use App\Services\CallAnalysisService;
use App\Models\TelemarketingLog;
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

        $params = ['shipment' => $next->id];
        if ($request->input('auto') === '1') {
            $params['auto'] = '1';
        }
        return redirect()->route('telemarketing.call', $params);
    }

    // ────────────────────────────────────────────────────────────────
    //  CALL FORM
    // ────────────────────────────────────────────────────────────────

    public function callForm(Shipment $shipment)
    {
        $this->authorizeAssignment($shipment);

        $shipment->load(['status', 'telemarketingLogs.disposition', 'telemarketingLogs.user', 'lastDisposition']);

        $companyId = auth()->user()->company_id;

        // Get dispositions filtered by shipment status
        $statusId = $shipment->normalized_status_id;
        $dispositions = StatusDispositionMapping::getDispositionsForStatus($statusId, $companyId);

        // Fallback: if no mapping exists for this status, show all dispositions
        if ($dispositions->isEmpty()) {
            $dispositions = $this->telemarketingService->getDispositions($companyId);
        }

        $callHistory = $this->telemarketingService->getCallHistory($shipment->id);

        // Check if this shipment was already called today
        $calledToday = $callHistory->first(function ($log) {
            return $log->created_at->isToday();
        });

        $queueCount = Shipment::forCompany($companyId)
            ->assignedTo(auth()->id())
            ->telemarketable()
            ->count();

        // Get auto-call settings for this company
        $autoCallSettings = CompanyTelemarketingSetting::getOrCreate($companyId);

        $recordingMode = $autoCallSettings->recording_mode ?? 'both';
        $requireRecording = $autoCallSettings->require_recording ?? false;
        $recordingUploadTimeout = $autoCallSettings->recording_upload_timeout ?? 30;
        $exemptDispositions = $autoCallSettings->recording_exempt_dispositions ?? [];
        $orderTypes = OrderType::forCompany($companyId)->active()->orderBy('sort_order')->get();
        $customerOrders = Order::forCompany($companyId)->forCustomerPhone($shipment->consignee_phone_1 ?? '')->with(['orderType', 'items'])->orderByDesc('created_at')->limit(5)->get();
        return view('telemarketing.call', compact('shipment', 'dispositions', 'callHistory', 'calledToday', 'queueCount', 'autoCallSettings', 'recordingMode', 'requireRecording', 'recordingUploadTimeout', 'exemptDispositions', 'orderTypes', 'customerOrders'));
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
            'call_recording' => 'nullable|file|max:51200',
        ]);

        $log = $this->telemarketingService->logCall(
            $shipment->id,
            $request->user()->id,
            $request->disposition_id,
            $request->notes,
            $request->callback_at,
            $request->phone_called,
            $request->call_duration_seconds
        );

        // Handle manual recording upload
        if ($request->hasFile('call_recording') && $log) {
            $file = $request->file('call_recording');
            $companyId = $request->user()->company_id;
            $timestamp = now()->format('Y-m-d_His');
            $extension = $file->getClientOriginalExtension() ?: 'mp3';
            $filename = "recordings/{$companyId}/{$timestamp}_{$request->user()->id}_log{$log->id}.{$extension}";

            $path = $file->storeAs('', $filename, 'local');

            $log->update([
                'recording_path' => $path,
                'recording_url' => route('telemarketing.play-recording', $log->id),
            ]);

            \Illuminate\Support\Facades\Log::info('Manual recording uploaded via form', [
                'path' => $path,
                'size' => $file->getSize(),
                'log_id' => $log->id,
                'user_id' => $request->user()->id,
            ]);

            // Auto-dispatch AI analysis job
            AnalyzeCallRecording::dispatch($log->id)->delay(now()->addSeconds(5));
        }

        if ($request->input('action') === 'save_next') {
            // Check if auto-call is enabled for this company
            $autoCallSettings = CompanyTelemarketingSetting::getOrCreate($request->user()->company_id);
            $params = ['exclude' => $shipment->id];
            if ($autoCallSettings->auto_call_enabled) {
                $params['auto'] = '1';
            }
            return redirect()->route('telemarketing.next-call', $params);
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

        // Shop names and item descriptions for manual assignment filters
        $shopNames = Shipment::forCompany($companyId)
            ->whereNotNull('sender_name')
            ->where('sender_name', '!=', '')
            ->distinct()
            ->orderBy('sender_name')
            ->pluck('sender_name');

        $itemDescriptions = Shipment::forCompany($companyId)
            ->whereNotNull('item_description')
            ->where('item_description', '!=', '')
            ->distinct()
            ->orderBy('item_description')
            ->pluck('item_description');

        // Dispositions for manual assignment filter
        $dispositions = TelemarketingDisposition::forCompany($companyId)
            ->orderBy('sort_order')
            ->get();

        return view('telemarketing.assignments', compact(
            'telemarketers', 'unassignedCount', 'onCooldownCount', 'statuses', 'rules', 'agentStatusMap', 'transitionRules',
            'shopNames', 'itemDescriptions', 'dispositions'
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
            'sender_name' => 'nullable|array',
            'sender_name.*' => 'string',
            'item_description' => 'nullable|array',
            'item_description.*' => 'string',
            'disposition' => 'nullable|array',
            'disposition.*' => 'string',
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

        if ($request->sender_name && is_array($request->sender_name) && count($request->sender_name) > 0) {
            $query->whereIn('sender_name', $request->sender_name);
        }

        if ($request->item_description && is_array($request->item_description) && count($request->item_description) > 0) {
            $query->whereIn('item_description', $request->item_description);
        }

        // Disposition filter
        if ($request->disposition && is_array($request->disposition) && count($request->disposition) > 0) {
            $dispositionFilters = $request->disposition;
            $hasNoDisposition = in_array('no_disposition', $dispositionFilters);
            $hasNoDispositionToday = in_array('no_disposition_today', $dispositionFilters);
            // Get actual disposition IDs (numeric values)
            $dispositionIds = array_filter($dispositionFilters, fn($v) => is_numeric($v));

            $query->where(function ($q) use ($hasNoDisposition, $hasNoDispositionToday, $dispositionIds) {
                // No Disposition = never been called (zero entries in telemarketing_logs)
                if ($hasNoDisposition) {
                    $q->orWhereDoesntHave('telemarketingLogs');
                }

                // No Disposition Today = no call log entry created today
                if ($hasNoDispositionToday) {
                    $q->orWhere(function ($sub) {
                        $sub->whereDoesntHave('telemarketingLogs', function ($logQ) {
                            $logQ->whereDate('created_at', today());
                        });
                    });
                }

                // Specific disposition IDs = latest log has that disposition
                if (!empty($dispositionIds)) {
                    $q->orWhereHas('telemarketingLogs', function ($logQ) use ($dispositionIds) {
                        $logQ->whereIn('disposition_id', $dispositionIds)
                             ->whereColumn('telemarketing_logs.id', '=',
                                 \DB::raw('(SELECT MAX(tl2.id) FROM telemarketing_logs tl2 WHERE tl2.shipment_id = shipments.id)')
                             );
                    });
                }
            });
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
        $user = $request->user();
        $companyId = $user->company_id;

        // Apply defaults when no filters are submitted (first page load)
        $hasFilters = $request->hasAny(['status', 'telemarketer_id', 'disposition_id', 'date_from', 'date_to', 'recording']);
        $defaultDateFrom = $hasFilters ? $request->date_from : now()->format('Y-m-d');
        $defaultRecording = $hasFilters ? ($request->recording ?? 'all') : 'with';

        // Build a query on Shipments that have telemarketing logs
        $query = Shipment::with(['status', 'lastDisposition'])
            ->whereHas('telemarketingLogs')
            ->withCount('telemarketingLogs as total_calls')
            ->orderBy('last_contacted_at', 'desc');

        // Platform Admin sees all, company users see only their company
        if ($companyId) {
            $query->forCompany($companyId);
        }

        // Filter by telemarketer — only show shipments that have logs by this agent
        if ($request->filled('telemarketer_id')) {
            $query->whereHas('telemarketingLogs', fn ($q) => $q->where('user_id', $request->telemarketer_id));
        }

        // Filter by disposition
        if ($request->filled('disposition_id')) {
            $query->whereHas('telemarketingLogs', fn ($q) => $q->where('disposition_id', $request->disposition_id));
        }

        // Filter by date range (based on log created_at)
        if (!empty($defaultDateFrom)) {
            $query->whereHas('telemarketingLogs', fn ($q) => $q->where('created_at', '>=', $defaultDateFrom));
        }
        if ($request->filled('date_to')) {
            $query->whereHas('telemarketingLogs', fn ($q) => $q->where('created_at', '<=', $request->date_to . ' 23:59:59'));
        }

        // Filter by status (draft/completed) — show shipments that have at least one log with this status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->whereHas('telemarketingLogs', fn ($q) => $q->where('status', $request->status));
        }

        // Filter by recording availability
        if ($defaultRecording === 'with') {
            $query->whereHas('telemarketingLogs', fn ($q) => $q->whereNotNull('recording_path')->where('recording_path', '!=', ''));
        } elseif ($defaultRecording === 'without') {
            $query->whereDoesntHave('telemarketingLogs', fn ($q) => $q->whereNotNull('recording_path')->where('recording_path', '!=', ''));
        }

        $shipments = $query->paginate(25)->withQueryString();

        // Eager-load all logs for the paginated shipments (with user & disposition)
        $shipmentIds = $shipments->pluck('id');
        $allLogs = \App\Models\TelemarketingLog::with(['user', 'disposition', 'aiDisposition'])
            ->whereIn('shipment_id', $shipmentIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('shipment_id');

        // Platform Admin: get telemarketers from all companies
        if ($companyId) {
            $telemarketers = User::forCompany($companyId)->active()->role('Telemarketer')->get();
        } else {
            $telemarketers = User::active()->role('Telemarketer')->get();
        }

        $dispositions = $this->telemarketingService->getDispositions($companyId);

        $columnConfig = CompanyTelemarketingSetting::getOrCreate($companyId ?? 0)->call_log_columns;

        // Pass effective filter values to the view (for pre-filling form inputs)
        $filterDefaults = [
            'date_from' => $defaultDateFrom,
            'recording' => $defaultRecording,
        ];

        return view('telemarketing.call-logs', compact('shipments', 'allLogs', 'telemarketers', 'dispositions', 'columnConfig', 'filterDefaults'));
    }

    // ────────────────────────────────────────────────────────────────
    //  AI CALL ANALYSIS
    // ────────────────────────────────────────────────────────────────

    public function analyzeCall(Request $request, TelemarketingLog $log)
    {
        $user = auth()->user();

        // Platform Admin can analyze any log; company users can only analyze their own company's logs
        if ($user->company_id && $log->shipment && $log->shipment->company_id !== $user->company_id) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            abort(403);
        }

        if (!$log->hasRecording()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'No recording found for this call log.']);
            }
            return back()->with('error', 'No recording found for this call log.');
        }

        $service = new CallAnalysisService();
        $result = $service->analyze($log);

        if ($request->expectsJson()) {
            $html = '';
            if ($result['success']) {
                $log->refresh();
                $log->load(['disposition', 'aiDisposition']);
                $html = view('telemarketing.partials.ai-result', ['log' => $log])->render();
            }
            return response()->json(array_merge($result, ['html' => $html]));
        }

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }


    /**
     * Queue all unanalyzed recordings for AI analysis (CEO/Owner only).
     */
    public function analyzeAllUnanalyzed(Request $request)
    {
        $user = auth()->user();

        // Only CEO, Company Owner, or Platform Admin can bulk analyze
        if (!$user->hasRole('CEO') && !$user->hasRole('Company Owner') && !$user->hasRole('Platform Admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Get all logs with recordings that need (re-)analysis (incomplete AI fields)
        $query = TelemarketingLog::needsAnalysis();

        // Company users only see their own company's logs
        if ($user->company_id) {
            $query->whereHas('shipment', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        }

        $logs = $query->get();
        $count = $logs->count();

        if ($count === 0) {
            return response()->json(['success' => true, 'queued' => 0, 'message' => 'No unanalyzed recordings found.']);
        }

        // Dispatch each recording to the queue for background processing
        foreach ($logs as $index => $log) {
            AnalyzeCallRecording::dispatch($log->id)->delay(now()->addSeconds($index * 5));
        }

        return response()->json([
            'success' => true,
            'queued' => $count,
            'message' => "Queued {$count} recordings for analysis. Results will appear automatically.",
        ]);
    }

    // ────────────────────────────────────────────────────────────────

    public function saveCallLogColumns(Request $request)
    {
        $user = $request->user();

        // Only CEO/Owner can change column settings
        if (!$user->hasRole('CEO') && !$user->hasRole('Company Owner')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'columns' => 'required|array',
            'columns.*.key' => 'required|string',
            'columns.*.label' => 'nullable|string',
            'columns.*.visible' => 'required|boolean',
            'columns.*.order' => 'required|integer',
        ]);

        $settings = CompanyTelemarketingSetting::getOrCreate($user->company_id);
        $settings->call_log_columns = $validated['columns'];
        $settings->save();

        return response()->json(['success' => true, 'message' => 'Column settings saved']);
    }
    //  HELPERS
    // ────────────────────────────────────────────────────────────────

    /**
     * API endpoint for polling call history (auto-refresh).
     */
    public function callHistoryApi(Shipment $shipment)
    {
        $user = auth()->user();
        if ($shipment->company_id !== $user->company_id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $logs = $this->telemarketingService->getCallHistory($shipment->id);

        $html = '';
        foreach ($logs as $i => $log) {
            $isDraft = $log->status === 'draft';
            $borderClass = $isDraft ? 'border-amber-300 bg-amber-50' : ($i === 0 ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200');
            $dispName = $isDraft ? 'Pending Disposition' : e($log->disposition?->name ?? 'N/A');
            $dispColor = $isDraft ? 'amber' : ($log->disposition?->color ?? 'gray');
            $userName = e($log->user?->name ?? 'N/A');
            $phone = e($log->phone_called ?? '-');
            $date = $log->created_at->format('M d, Y H:i');
            $duration = $log->call_duration_seconds ? ' | Duration: ' . gmdate('i:s', $log->call_duration_seconds) : '';
            $notes = $log->notes ? '<p class="text-sm text-gray-700 mt-1">' . e($log->notes) . '</p>' : '';
            $callback = $log->callback_at ? '<p class="text-xs text-orange-600 mt-1">Callback: ' . $log->callback_at->format('M d, Y H:i') . '</p>' : '';

            $html .= '<div class="border rounded-lg p-3 ' . $borderClass . '">';
            if ($isDraft) {
                $html .= '<div class="flex items-center space-x-1 mb-2"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Pending Disposition</span></div>';
            }
            $html .= '<div class="flex justify-between items-start mb-1">';
            $html .= '<div class="flex items-center space-x-2">';
            $html .= '<span class="text-sm font-semibold text-gray-900">Attempt #' . $log->attempt_no . '</span>';
            $html .= '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-' . $dispColor . '-100 text-' . $dispColor . '-800">' . $dispName . '</span>';
            $html .= '</div>';
            $html .= '<span class="text-xs text-gray-500">' . $date . '</span>';
            $html .= '</div>';
            $html .= '<p class="text-xs text-gray-500">By: ' . $userName . ' | Phone: ' . $phone . $duration . '</p>';
            $html .= $notes . $callback;
            $html .= '</div>';
        }

        if (empty($html)) {
            $html = '<p class="text-sm text-gray-500">No previous calls for this shipment.</p>';
        }

        // Find the most recent call today (draft or completed)
        $calledToday = $logs->first(function ($log) {
            return $log->created_at->isToday();
        });
        $bannerData = null;
        if ($calledToday) {
            $bannerData = [
                'status' => $calledToday->status,
                'time' => $calledToday->created_at->format('g:i A'),
                'user' => $calledToday->user?->name ?? 'N/A',
                'disposition' => $calledToday->disposition?->name ?? null,
                'duration' => $calledToday->call_duration_seconds ? gmdate('i:s', $calledToday->call_duration_seconds) : null,
            ];
        }
        return response()->json([
            'count' => $logs->count(),
            'html' => $html,
            'calledToday' => $bannerData,
        ]);
    }
    protected function authorizeAssignment(Shipment $shipment): void
    {
        $user = auth()->user();
        if ($shipment->company_id !== $user->company_id) {
            abort(403);
        }
        if ($user->hasRole('Telemarketer')) {
            $settings = CompanyTelemarketingSetting::getOrCreate($user->company_id);
            if ($settings->isPreAssigned() && $shipment->assigned_to_user_id !== $user->id) {
                abort(403, 'This shipment is not assigned to you.');
            }
            // For shared_queue: any telemarketer in the company can access
            // For hybrid: assigned to them OR unassigned
            if ($settings->isHybrid() && $shipment->assigned_to_user_id !== null && $shipment->assigned_to_user_id !== $user->id) {
                abort(403, 'This shipment is assigned to another agent.');
            }
            // Auto-assign on access for shared/hybrid if unassigned
            if (!$settings->isPreAssigned() && $shipment->assigned_to_user_id === null) {
                $shipment->update(['assigned_to_user_id' => $user->id]);
            }
        }
    }
}
