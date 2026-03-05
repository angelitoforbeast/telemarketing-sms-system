<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SmsCampaign;
use App\Models\SmsSendLog;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SmsBlastApiController extends Controller
{
    /**
     * Authenticate SMS Operator by email/password. Returns user info + assigned stats.
     * POST /api/sms-blast/auth
     */
    public function authenticate(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !password_verify($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Check if user has SMS Operator role
        if (!$user->hasRole('SMS Operator')) {
            return response()->json(['error' => 'User is not an SMS Operator'], 403);
        }

        // Mark user as online for SMS blast
        $user->update(['last_seen_at' => now()]);

        // Get pending count for this user
        $pendingCount = SmsSendLog::where('assigned_to', $user->id)
            ->where('status', 'queued')
            ->count();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'company_id' => $user->company_id,
            ],
            'pending_count' => $pendingCount,
        ]);
    }

    /**
     * Heartbeat - user pings to stay online.
     * POST /api/sms-blast/heartbeat
     */
    public function heartbeat(Request $request)
    {
        $user = $this->getUser($request);
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $user->update(['last_seen_at' => now()]);

        $pendingCount = SmsSendLog::where('assigned_to', $user->id)
            ->where('status', 'queued')
            ->count();

        $activeCampaigns = SmsCampaign::where('company_id', $user->company_id)
            ->where('campaign_status', 'sending')
            ->where('sending_method', 'sim_based')
            ->count();

        // Count messages sent today by this user
        $sentToday = SmsSendLog::where('assigned_to', $user->id)
            ->where('status', 'sent')
            ->where('sent_at', '>=', now()->startOfDay())
            ->count();

        return response()->json([
            'success' => true,
            'pending_count' => $pendingCount,
            'active_campaigns' => $activeCampaigns,
            'sent_today' => $sentToday,
        ]);
    }

    /**
     * Pull next batch of messages to send.
     * POST /api/sms-blast/pull
     */
    public function pull(Request $request)
    {
        $user = $this->getUser($request);
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $batchSize = min($request->input('batch_size', 10), 50);

        // First, check if there are queued messages already assigned to this user
        $messages = SmsSendLog::where('assigned_to', $user->id)
            ->where('status', 'queued')
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        // If no assigned messages, claim unassigned queued messages
        if ($messages->isEmpty()) {
            $messages = DB::transaction(function () use ($user, $batchSize) {
                $logs = SmsSendLog::whereNull('assigned_to')
                    ->where('status', 'queued')
                    ->whereHas('campaign', function ($q) use ($user) {
                        $q->where('company_id', $user->company_id)
                            ->where('campaign_status', 'sending')
                            ->where('sending_method', 'sim_based');
                    })
                    ->orderBy('id')
                    ->limit($batchSize)
                    ->lockForUpdate()
                    ->get();

                if ($logs->isNotEmpty()) {
                    SmsSendLog::whereIn('id', $logs->pluck('id'))
                        ->update(['assigned_to' => $user->id]);
                }

                return $logs;
            });
        }

        // If still no messages, try to generate queue from active campaigns
        if ($messages->isEmpty()) {
            $this->generateQueueForUser($user, $batchSize);

            $messages = SmsSendLog::where('assigned_to', $user->id)
                ->where('status', 'queued')
                ->orderBy('id')
                ->limit($batchSize)
                ->get();
        }

        $result = $messages->map(function ($log) {
            return [
                'log_id' => $log->id,
                'phone_number' => $log->phone_number,
                'message' => $log->message_body,
                'campaign_id' => $log->campaign_id,
            ];
        });

        return response()->json([
            'success' => true,
            'messages' => $result,
        ]);
    }

    /**
     * Report status of sent messages.
     * POST /api/sms-blast/report
     */
    public function report(Request $request)
    {
        $user = $this->getUser($request);
        if (!$user) return response()->json(['error' => 'Unauthorized'], 401);

        $request->validate([
            'results' => 'required|array',
            'results.*.log_id' => 'required|integer',
            'results.*.status' => 'required|in:sent,failed',
            'results.*.error_message' => 'nullable|string|max:500',
        ]);

        $sentCount = 0;
        $failedCount = 0;

        foreach ($request->results as $result) {
            $log = SmsSendLog::where('id', $result['log_id'])
                ->where('assigned_to', $user->id)
                ->first();

            if (!$log) continue;

            $log->update([
                'status' => $result['status'],
                'sent_at' => $result['status'] === 'sent' ? now() : null,
                'error_message' => $result['error_message'] ?? null,
            ]);

            if ($result['status'] === 'sent') {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        // Update campaign counters
        $campaignIds = collect($request->results)->pluck('log_id')
            ->map(fn($id) => SmsSendLog::find($id)?->campaign_id)
            ->filter()
            ->unique();

        foreach ($campaignIds as $campaignId) {
            $campaign = SmsCampaign::find($campaignId);
            if (!$campaign) continue;

            $stats = SmsSendLog::where('campaign_id', $campaignId)
                ->selectRaw("
                    COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                    COUNT(CASE WHEN status = 'queued' THEN 1 END) as queued
                ")
                ->first();

            $campaign->update([
                'total_sent' => $stats->sent,
                'total_failed' => $stats->failed,
            ]);

            // Check if campaign is complete (no more queued)
            if ($stats->queued == 0 && $campaign->campaign_status === 'sending') {
                if ($campaign->schedule_type === 'immediate' || $campaign->schedule_type === 'scheduled') {
                    $campaign->update(['campaign_status' => 'completed']);
                }
            }
        }

        $user->update(['last_seen_at' => now()]);

        return response()->json([
            'success' => true,
            'processed' => $sentCount + $failedCount,
            'sent' => $sentCount,
            'failed' => $failedCount,
        ]);
    }

    /**
     * Generate queue entries for a user from active campaigns.
     */
    private function generateQueueForUser(User $user, int $limit)
    {
        $campaigns = SmsCampaign::where('company_id', $user->company_id)
            ->where('campaign_status', 'sending')
            ->where('sending_method', 'sim_based')
            ->get();

        foreach ($campaigns as $campaign) {
            if ($limit <= 0) break;

            $recipients = $this->getFilteredRecipients($campaign, $limit);

            foreach ($recipients as $shipment) {
                if ($limit <= 0) break;

                $alreadySent = SmsSendLog::where('campaign_id', $campaign->id)
                    ->where('shipment_id', $shipment->id)
                    ->exists();

                if ($alreadySent) continue;

                $message = $this->personalizeMessage($campaign->sms_template, $shipment);

                SmsSendLog::create([
                    'campaign_id' => $campaign->id,
                    'shipment_id' => $shipment->id,
                    'phone_number' => $shipment->consignee_phone,
                    'message_body' => $message,
                    'status' => 'queued',
                    'assigned_to' => $user->id,
                ]);

                $limit--;
            }

            $totalQueued = SmsSendLog::where('campaign_id', $campaign->id)->count();
            $campaign->update(['total_recipients' => $totalQueued]);
        }
    }

    /**
     * Get filtered recipients for a campaign.
     */
    private function getFilteredRecipients(SmsCampaign $campaign, int $limit)
    {
        $filters = $campaign->recipient_filters ?? [];

        $query = Shipment::where('company_id', $campaign->company_id)
            ->whereNotNull('consignee_phone')
            ->where('consignee_phone', '!=', '');

        if (!empty($filters['statuses'])) {
            $query->whereIn('normalized_status_id', $filters['statuses']);
        }

        if (!empty($filters['date_range_days'])) {
            $query->where('created_at', '>=', now()->subDays($filters['date_range_days']));
        }

        if (!empty($filters['exclude_already_sent'])) {
            $query->whereDoesntHave('smsSendLogs', function ($q) use ($campaign) {
                $q->where('campaign_id', $campaign->id);
            });
        }

        return $query->orderBy('id')->limit($limit)->get();
    }

    /**
     * Personalize message template with shipment data.
     */
    private function personalizeMessage(string $template, Shipment $shipment): string
    {
        $replacements = [
            '{consignee_name}' => $shipment->consignee_name ?? '',
            '{waybill_no}' => $shipment->waybill_number ?? '',
            '{cod_amount}' => number_format($shipment->cod_amount ?? 0, 2),
            '{status}' => $shipment->normalizedStatus?->name ?? '',
            '{courier}' => $shipment->courier ?? '',
            '{item_description}' => $shipment->item_description ?? '',
            '{consignee_address}' => $shipment->consignee_address ?? '',
            '{consignee_city}' => $shipment->consignee_city ?? '',
            '{consignee_province}' => $shipment->consignee_province ?? '',
            '{consignee_barangay}' => $shipment->consignee_barangay ?? '',
            '{consignee_phone}' => $shipment->consignee_phone ?? '',
            '{sender_name}' => $shipment->shipper_name ?? '',
            '{sender_phone}' => $shipment->shipper_phone ?? '',
            '{item_quantity}' => $shipment->item_quantity ?? '',
            '{shipping_cost}' => number_format($shipment->shipping_cost ?? 0, 2),
            '{company_name}' => $shipment->company?->name ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Get authenticated user from request (via email+password or session).
     */
    private function getUser(Request $request): ?User
    {
        // Try email/password from request body
        $email = $request->input('email');
        $password = $request->input('password');

        if ($email && $password) {
            $user = User::where('email', $email)->first();
            if ($user && password_verify($password, $user->password) && $user->hasRole('SMS Operator')) {
                return $user;
            }
        }

        // Try auth session
        if (auth()->check() && auth()->user()->hasRole('SMS Operator')) {
            return auth()->user();
        }

        return null;
    }
}
