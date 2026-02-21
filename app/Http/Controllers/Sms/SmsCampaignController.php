<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Models\ShipmentStatus;
use App\Models\SmsCampaign;
use App\Models\SmsSendLog;
use Illuminate\Http\Request;

class SmsCampaignController extends Controller
{
    /**
     * List all SMS campaigns for the current company.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $campaigns = SmsCampaign::forCompany($user->company_id)
            ->with('triggerStatus')
            ->withCount(['sendLogs as total_sent' => fn ($q) => $q->where('status', 'sent')])
            ->withCount(['sendLogs as total_queued' => fn ($q) => $q->where('status', 'queued')])
            ->withCount(['sendLogs as total_failed' => fn ($q) => $q->where('status', 'failed')])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('sms.campaigns.index', compact('campaigns'));
    }

    /**
     * Show the campaign creation form.
     */
    public function create()
    {
        $statuses = ShipmentStatus::where('is_sms_triggerable', true)->orderBy('sort_order')->get();

        return view('sms.campaigns.create', compact('statuses'));
    }

    /**
     * Store a new SMS campaign.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sms_template' => 'required|string|max:500',
            'trigger_status_id' => 'required|integer|exists:shipment_statuses,id',
            'is_active' => 'boolean',
            'send_daily_repeat' => 'boolean',
            'daily_send_limit' => 'nullable|integer|min:1',
            'province_filter' => 'nullable|array',
            'city_filter' => 'nullable|array',
        ]);

        $user = $request->user();

        SmsCampaign::create([
            'company_id' => $user->company_id,
            'name' => $request->name,
            'sms_template' => $request->sms_template,
            'trigger_status_id' => $request->trigger_status_id,
            'is_active' => $request->boolean('is_active', true),
            'send_daily_repeat' => $request->boolean('send_daily_repeat', false),
            'daily_send_limit' => $request->daily_send_limit,
            'province_filter' => $request->province_filter,
            'city_filter' => $request->city_filter,
            'created_by_user_id' => $user->id,
        ]);

        return redirect()->route('sms.campaigns.index')
            ->with('success', 'SMS campaign created successfully.');
    }

    /**
     * Show campaign edit form.
     */
    public function edit(SmsCampaign $campaign)
    {
        $this->authorizeCompany($campaign);

        $statuses = ShipmentStatus::where('is_sms_triggerable', true)->orderBy('sort_order')->get();

        return view('sms.campaigns.edit', compact('campaign', 'statuses'));
    }

    /**
     * Update a campaign.
     */
    public function update(Request $request, SmsCampaign $campaign)
    {
        $this->authorizeCompany($campaign);

        $request->validate([
            'name' => 'required|string|max:255',
            'sms_template' => 'required|string|max:500',
            'trigger_status_id' => 'required|integer|exists:shipment_statuses,id',
            'is_active' => 'boolean',
            'send_daily_repeat' => 'boolean',
            'daily_send_limit' => 'nullable|integer|min:1',
            'province_filter' => 'nullable|array',
            'city_filter' => 'nullable|array',
        ]);

        $campaign->update($request->only([
            'name', 'sms_template', 'trigger_status_id',
            'is_active', 'send_daily_repeat', 'daily_send_limit',
            'province_filter', 'city_filter',
        ]));

        return redirect()->route('sms.campaigns.index')
            ->with('success', 'Campaign updated successfully.');
    }

    /**
     * Show campaign send logs.
     */
    public function logs(SmsCampaign $campaign, Request $request)
    {
        $this->authorizeCompany($campaign);

        $logs = SmsSendLog::where('campaign_id', $campaign->id)
            ->with('shipment')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('sms.campaigns.logs', compact('campaign', 'logs'));
    }

    /**
     * Toggle campaign active status.
     */
    public function toggle(SmsCampaign $campaign)
    {
        $this->authorizeCompany($campaign);

        $campaign->update(['is_active' => !$campaign->is_active]);

        $status = $campaign->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Campaign {$status} successfully.");
    }

    protected function authorizeCompany(SmsCampaign $campaign): void
    {
        if ($campaign->company_id !== auth()->user()->company_id) {
            abort(403);
        }
    }
}
