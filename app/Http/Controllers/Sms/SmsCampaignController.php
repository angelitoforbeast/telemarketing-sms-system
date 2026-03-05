<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Models\ShipmentStatus;
use App\Models\SmsCampaign;
use App\Models\SmsSendLog;
use App\Models\User;
use App\Models\SmsDevice;
use App\Services\Sms\SmsCampaignService;
use Illuminate\Http\Request;

class SmsCampaignController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $campaigns = SmsCampaign::forCompany($user->company_id)
            ->with('triggerStatus', 'assignedOperator')
            ->withCount(['sendLogs as total_sent_count' => fn ($q) => $q->where('status', 'sent')])
            ->withCount(['sendLogs as total_queued_count' => fn ($q) => $q->where('status', 'queued')])
            ->withCount(['sendLogs as total_failed_count' => fn ($q) => $q->where('status', 'failed')])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $onlineDevices = SmsDevice::forCompany($user->company_id)->active()->online()->count();

        return view('sms.campaigns.index', compact('campaigns', 'onlineDevices'));
    }

    public function create()
    {
        $statuses = ShipmentStatus::orderBy('sort_order')->get();
        $mergeTags = SmsCampaign::MERGE_TAGS;
        $smsOperators = User::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->role('SMS Operator')
            ->orderBy('name')
            ->get();
        return view('sms.campaigns.create', compact('statuses', 'mergeTags', 'smsOperators'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sms_template' => 'required|string',
            'trigger_status_id' => 'nullable|integer|exists:shipment_statuses,id',
            'schedule_type' => 'required|in:immediate,scheduled,recurring_daily,recurring_hourly,custom_cron',
            'sending_method' => 'required|in:sim_based,gateway',
            'recipient_filter_type' => 'required|in:dynamic,fixed',
            'assigned_operator_id' => 'nullable|integer|exists:users,id',
        ]);

        $user = $request->user();

        $recipientFilters = [
            'statuses' => $request->filter_statuses ?? [],
            'date_range_days' => $request->filter_date_range_days,
            'exclude_already_sent' => $request->boolean('filter_exclude_already_sent', true),
        ];

        $campaign = SmsCampaign::create([
            'company_id' => $user->company_id,
            'name' => $request->name,
            'sms_template' => $request->sms_template,
            'trigger_status_id' => $request->trigger_status_id,
            'is_active' => $request->boolean('is_active', true),
            'send_daily_repeat' => $request->schedule_type === 'recurring_daily',
            'daily_send_limit' => $request->daily_send_limit,
            'province_filter' => $request->province_filter,
            'city_filter' => $request->city_filter,
            'created_by_user_id' => $user->id,
            'schedule_type' => $request->schedule_type,
            'scheduled_at' => $request->scheduled_at,
            'recurring_time' => $request->recurring_time,
            'recurring_interval_hours' => $request->recurring_interval_hours,
            'cron_expression' => $request->cron_expression,
            'sending_method' => $request->sending_method,
            'assigned_operator_id' => $request->assigned_operator_id,
            'throttle_delay_seconds' => $request->throttle_delay_seconds ?? 10,
            'recipient_filter_type' => $request->recipient_filter_type,
            'recipient_filters' => $recipientFilters,
            'campaign_status' => SmsCampaign::STATUS_QUEUED,
        ]);

        // Calculate and set next_run_at for scheduled/recurring campaigns
        $nextRunAt = SmsCampaignService::calculateNextRunAt(
            $campaign->schedule_type,
            $campaign->recurring_time,
            $campaign->recurring_interval_hours,
            $request->scheduled_at
        );
        if ($nextRunAt) {
            $campaign->update(['next_run_at' => $nextRunAt]);
        }
        // For immediate campaigns, queue messages and start right away
        if ($campaign->schedule_type === 'immediate') {
            $count = $campaign->queueMessages();
            if ($count > 0) {
                $this->autoAssignMessages($campaign);
                return redirect()->route('sms.campaigns.show', $campaign)
                    ->with('success', "Campaign created and started! {$count} messages queued.");
            }
            return redirect()->route('sms.campaigns.show', $campaign)
                ->with('warning', 'Campaign created but no recipients found matching filters.');
        }

        // For scheduled/recurring, the Laravel scheduler cron will handle it
        return redirect()->route('sms.campaigns.show', $campaign)
            ->with('success', 'Campaign created and activated. Messages will be queued on schedule.');
    }

    public function show(SmsCampaign $campaign)
    {
        $this->authorizeCompany($campaign);

        $campaign->load('assignedOperator');
        $campaign->loadCount([
            'sendLogs as sent_count' => fn ($q) => $q->where('status', 'sent'),
            'sendLogs as queued_count' => fn ($q) => $q->where('status', 'queued'),
            'sendLogs as failed_count' => fn ($q) => $q->where('status', 'failed'),
        ]);

        $recipientCount = $campaign->buildRecipientQuery()->count();

        $recentLogs = SmsSendLog::where('campaign_id', $campaign->id)
            ->with('shipment')
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();

        $onlineDevices = SmsDevice::forCompany($campaign->company_id)->active()->online()->get();

        $smsOperators = User::where('company_id', $campaign->company_id)
            ->where('is_active', true)
            ->role('SMS Operator')
            ->orderBy('name')
            ->get();

        return view('sms.campaigns.show', compact('campaign', 'recipientCount', 'recentLogs', 'onlineDevices', 'smsOperators'));
    }

    public function edit(SmsCampaign $campaign)
    {
        $this->authorizeCompany($campaign);
        $statuses = ShipmentStatus::orderBy('sort_order')->get();
        $mergeTags = SmsCampaign::MERGE_TAGS;
        $smsOperators = User::where('company_id', $campaign->company_id)
            ->where('is_active', true)
            ->role('SMS Operator')
            ->orderBy('name')
            ->get();
        return view('sms.campaigns.edit', compact('campaign', 'statuses', 'mergeTags', 'smsOperators'));
    }

    public function update(Request $request, SmsCampaign $campaign)
    {
        $this->authorizeCompany($campaign);

        $request->validate([
            'name' => 'required|string|max:255',
            'sms_template' => 'required|string',
            'schedule_type' => 'required|in:immediate,scheduled,recurring_daily,recurring_hourly,custom_cron',
            'sending_method' => 'required|in:sim_based,gateway',
            'recipient_filter_type' => 'required|in:dynamic,fixed',
            'assigned_operator_id' => 'nullable|integer|exists:users,id',
        ]);

        $recipientFilters = [
            'statuses' => $request->filter_statuses ?? [],
            'date_range_days' => $request->filter_date_range_days,
            'exclude_already_sent' => $request->boolean('filter_exclude_already_sent', true),
        ];

        $campaign->update([
            'name' => $request->name,
            'sms_template' => $request->sms_template,
            'trigger_status_id' => $request->trigger_status_id,
            'is_active' => $request->boolean('is_active', true),
            'daily_send_limit' => $request->daily_send_limit,
            'province_filter' => $request->province_filter,
            'city_filter' => $request->city_filter,
            'schedule_type' => $request->schedule_type,
            'scheduled_at' => $request->scheduled_at,
            'recurring_time' => $request->recurring_time,
            'recurring_interval_hours' => $request->recurring_interval_hours,
            'cron_expression' => $request->cron_expression,
            'sending_method' => $request->sending_method,
            'assigned_operator_id' => $request->assigned_operator_id,
            'throttle_delay_seconds' => $request->throttle_delay_seconds ?? 10,
            'recipient_filter_type' => $request->recipient_filter_type,
            'recipient_filters' => $recipientFilters,
        ]);

        // Recalculate next_run_at when schedule changes
        $nextRunAt = SmsCampaignService::calculateNextRunAt(
            $campaign->schedule_type,
            $campaign->recurring_time,
            $campaign->recurring_interval_hours,
            $request->scheduled_at
        );
        $campaign->update(['next_run_at' => $nextRunAt]);

        return redirect()->route('sms.campaigns.show', $campaign)
            ->with('success', 'Campaign updated successfully.');
    }

    public function start(SmsCampaign $campaign)
    {
        $this->authorizeCompany($campaign);

        if (!in_array($campaign->campaign_status, ['draft', 'paused', 'completed'])) {
            return back()->with('error', 'Campaign cannot be started in its current state.');
        }

        $count = $campaign->queueMessages();

        if ($count === 0) {
            return back()->with('error', 'No recipients found matching the filters.');
        }

        // Auto-assign messages to the chosen operator (or all operators if none chosen)
        $assigned = $this->autoAssignMessages($campaign);

        return back()->with('success', "{$count} messages queued. {$assigned} assigned to SMS operators.");
    }

    /**
     * Assign queued messages to SMS Operator(s).
     * If campaign has assigned_operator_id set, all messages go to that operator.
     * Otherwise, round-robin across all active SMS Operators.
     */
    protected function autoAssignMessages(SmsCampaign $campaign): int
    {
        // Get unassigned queued messages for this campaign
        $unassigned = SmsSendLog::where('campaign_id', $campaign->id)
            ->where('status', 'queued')
            ->whereNull('assigned_to')
            ->orderBy('id')
            ->pluck('id');

        if ($unassigned->isEmpty()) {
            return 0;
        }

        // If a specific operator is assigned to this campaign, use that one
        if ($campaign->assigned_operator_id) {
            SmsSendLog::whereIn('id', $unassigned)
                ->update(['assigned_to' => $campaign->assigned_operator_id]);
            return $unassigned->count();
        }

        // Otherwise, round-robin across all active SMS Operators
        $operators = User::where('company_id', $campaign->company_id)
            ->where('is_active', true)
            ->role('SMS Operator')
            ->get();

        if ($operators->isEmpty()) {
            return 0;
        }

        $operatorIds = $operators->pluck('id')->toArray();
        $operatorCount = count($operatorIds);
        $assigned = 0;

        foreach ($unassigned as $index => $logId) {
            $operatorId = $operatorIds[$index % $operatorCount];
            SmsSendLog::where('id', $logId)->update(['assigned_to' => $operatorId]);
            $assigned++;
        }

        return $assigned;
    }

    /**
     * Reassign all queued messages for a campaign to a specific operator.
     * Called via AJAX from the campaign show page.
     */
    public function assignOperator(Request $request, SmsCampaign $campaign)
    {
        $this->authorizeCompany($campaign);

        $request->validate([
            'assigned_operator_id' => 'required|integer|exists:users,id',
        ]);

        $operatorId = $request->assigned_operator_id;

        // Update the campaign's assigned operator
        $campaign->update(['assigned_operator_id' => $operatorId]);

        // Reassign all queued messages to this operator
        $reassigned = SmsSendLog::where('campaign_id', $campaign->id)
            ->where('status', 'queued')
            ->update(['assigned_to' => $operatorId]);

        $operator = User::find($operatorId);

        return back()->with('success', "Campaign assigned to {$operator->name}. {$reassigned} queued messages reassigned.");
    }

    public function pause(SmsCampaign $campaign)
    {
        $this->authorizeCompany($campaign);
        $campaign->update(['campaign_status' => SmsCampaign::STATUS_PAUSED]);
        return back()->with('success', 'Campaign paused.');
    }

    public function cancel(SmsCampaign $campaign)
    {
        $this->authorizeCompany($campaign);
        SmsSendLog::where('campaign_id', $campaign->id)->where('status', 'queued')->update(['status' => 'cancelled']);
        $campaign->update(['campaign_status' => SmsCampaign::STATUS_CANCELLED]);
        return back()->with('success', 'Campaign cancelled.');
    }

    public function logs(SmsCampaign $campaign, Request $request)
    {
        $this->authorizeCompany($campaign);
        $logs = SmsSendLog::where('campaign_id', $campaign->id)
            ->with('shipment')
            ->orderBy('created_at', 'desc')
            ->paginate(50);
        return view('sms.campaigns.logs', compact('campaign', 'logs'));
    }

    public function toggle(SmsCampaign $campaign)
    {
        $this->authorizeCompany($campaign);
        $campaign->update(['is_active' => !$campaign->is_active]);
        $status = $campaign->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Campaign {$status} successfully.");
    }

    public function previewRecipients(Request $request)
    {
        $user = $request->user();
        $query = \App\Models\Shipment::forCompany($user->company_id)
            ->whereNotNull('consignee_phone_1')
            ->where('consignee_phone_1', '!=', '');

        if ($request->filter_statuses) {
            $query->whereIn('normalized_status_id', $request->filter_statuses);
        }
        if ($request->filter_date_range_days) {
            $query->where('created_at', '>=', now()->subDays($request->filter_date_range_days));
        }

        return response()->json(['count' => $query->count()]);
    }

    protected function authorizeCompany(SmsCampaign $campaign): void
    {
        $user = auth()->user();
        if ($user->company_id && $campaign->company_id !== $user->company_id) {
            abort(403);
        }
    }
}
