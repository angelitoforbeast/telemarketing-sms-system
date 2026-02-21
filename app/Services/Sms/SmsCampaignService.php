<?php

namespace App\Services\Sms;

use App\Models\Shipment;
use App\Models\SmsCampaign;
use App\Models\SmsSendLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SmsCampaignService
{
    /**
     * Evaluate all active campaigns and queue SMS messages.
     * Called by the scheduled job.
     */
    public function evaluateAndQueue(): int
    {
        $campaigns = SmsCampaign::active()
            ->with('triggerStatus')
            ->get();

        $totalQueued = 0;

        foreach ($campaigns as $campaign) {
            $queued = $this->queueForCampaign($campaign);
            $totalQueued += $queued;
        }

        Log::info("SMS Campaign Evaluation: {$totalQueued} messages queued across {$campaigns->count()} campaigns.");

        return $totalQueued;
    }

    /**
     * Queue SMS messages for a single campaign.
     */
    public function queueForCampaign(SmsCampaign $campaign): int
    {
        $query = Shipment::forCompany($campaign->company_id)
            ->where('normalized_status_id', $campaign->trigger_status_id)
            ->contactable();

        // Apply province filter
        if (!empty($campaign->province_filter)) {
            $query->whereIn('consignee_province', $campaign->province_filter);
        }

        // Apply city filter
        if (!empty($campaign->city_filter)) {
            $query->whereIn('consignee_city', $campaign->city_filter);
        }

        // Apply daily send limit
        if ($campaign->daily_send_limit) {
            $sentToday = SmsSendLog::where('campaign_id', $campaign->id)
                ->where('send_date', now()->toDateString())
                ->count();

            $remaining = max(0, $campaign->daily_send_limit - $sentToday);
            if ($remaining <= 0) return 0;

            $query->limit($remaining);
        }

        $shipments = $query->get();
        $queued = 0;

        foreach ($shipments as $shipment) {
            try {
                // Dedupe check: unique constraint (shipment_id, campaign_id, send_date)
                SmsSendLog::create([
                    'company_id' => $campaign->company_id,
                    'shipment_id' => $shipment->id,
                    'campaign_id' => $campaign->id,
                    'phone_number' => $shipment->consignee_phone_1,
                    'message_body' => $campaign->renderMessage($shipment),
                    'send_date' => now()->toDateString(),
                    'status' => 'queued',
                ]);
                $queued++;
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // Already queued for today — skip (dedupe working as intended)
                continue;
            }
        }

        return $queued;
    }

    /**
     * Process all queued SMS messages and send them.
     */
    public function processQueue(int $batchSize = 100): int
    {
        $logs = SmsSendLog::where('status', 'queued')
            ->where('send_date', now()->toDateString())
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        $sent = 0;

        foreach ($logs as $log) {
            try {
                $this->sendSms($log);
                $sent++;
            } catch (\Throwable $e) {
                $log->markFailed($e->getMessage());
                Log::error("SMS send failed for log #{$log->id}: {$e->getMessage()}");
            }
        }

        return $sent;
    }

    /**
     * Send a single SMS message via the configured provider.
     * This is the integration point for your SMS gateway (Semaphore, etc.).
     */
    protected function sendSms(SmsSendLog $log): void
    {
        // ─── SMS PROVIDER INTEGRATION POINT ───
        // Replace this with your actual SMS gateway call.
        // Example for Semaphore:
        //
        // $response = Http::post('https://api.semaphore.co/api/v4/messages', [
        //     'apikey' => config('services.semaphore.api_key'),
        //     'number' => $log->phone_number,
        //     'message' => $log->message_body,
        //     'sendername' => config('services.semaphore.sender_name'),
        // ]);
        //
        // if ($response->successful()) {
        //     $log->markSent($response->body(), $response->json('message_id'));
        // } else {
        //     throw new \RuntimeException("SMS API error: " . $response->body());
        // }

        // For now, simulate success:
        $log->markSent('simulated_response', 'sim_' . uniqid());
    }
}
