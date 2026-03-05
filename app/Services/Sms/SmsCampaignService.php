<?php
namespace App\Services\Sms;
use App\Models\Shipment;
use App\Models\SmsCampaign;
use App\Models\SmsSendLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class SmsCampaignService
{
    /**
     * Evaluate campaigns that are ready to send and queue SMS messages.
     * Called by the scheduled job every minute.
     */
    public function evaluateAndQueue(): int
    {
        $campaigns = SmsCampaign::readyToSend()
            ->with('triggerStatus')
            ->get();
        $totalQueued = 0;
        foreach ($campaigns as $campaign) {
            $queued = $this->queueForCampaign($campaign);
            $totalQueued += $queued;

            // After running, update next_run_at for recurring campaigns
            // ONLY if messages were actually queued
            if ($queued > 0) {
                $this->updateNextRunAt($campaign);
            }
        }
        Log::info("SMS Campaign Evaluation: {$totalQueued} messages queued across {$campaigns->count()} campaigns.");
        return $totalQueued;
    }

    /**
     * Calculate and update next_run_at after a campaign runs.
     * For recurring campaigns, set the next run time.
     * For one-time scheduled campaigns, mark as completed.
     */
    protected function updateNextRunAt(SmsCampaign $campaign): void
    {
        switch ($campaign->schedule_type) {
            case SmsCampaign::SCHEDULE_RECURRING_DAILY:
                // Set next run to tomorrow at the recurring_time
                // Keep status as SENDING so device can fetch and send messages
                // Status will be reset to QUEUED after all messages are sent
                if ($campaign->recurring_time) {
                    $nextRun = Carbon::tomorrow()->setTimeFromTimeString($campaign->recurring_time . ':00');
                    $campaign->update([
                        'next_run_at' => $nextRun,
                        // Don't change status here - keep it as SENDING
                    ]);
                }
                break;

            case SmsCampaign::SCHEDULE_RECURRING_HOURLY:
                // Set next run to now + interval hours
                // Keep status as SENDING so device can fetch and send messages
                $hours = $campaign->recurring_interval_hours ?? 1;
                $campaign->update([
                    'next_run_at' => now()->addHours($hours),
                    // Don't change status here - keep it as SENDING
                ]);
                break;

            case SmsCampaign::SCHEDULE_SCHEDULED:
                // One-time scheduled campaign - don't run again
                $campaign->update([
                    'next_run_at' => null,
                    'campaign_status' => SmsCampaign::STATUS_SENDING,
                ]);
                break;

            case SmsCampaign::SCHEDULE_IMMEDIATE:
                // Immediate campaigns are handled in the controller
                break;
        }
    }

    /**
     * Calculate the initial next_run_at when a campaign is created or updated.
     */
    public static function calculateNextRunAt(string $scheduleType, ?string $recurringTime = null, ?int $recurringIntervalHours = null, ?string $scheduledAt = null): ?Carbon
    {
        switch ($scheduleType) {
            case SmsCampaign::SCHEDULE_RECURRING_DAILY:
                if ($recurringTime) {
                    $todayRun = Carbon::today()->setTimeFromTimeString($recurringTime . ':00');
                    // If the time hasn't passed today, run today; otherwise tomorrow
                    if ($todayRun->isFuture()) {
                        return $todayRun;
                    }
                    return Carbon::tomorrow()->setTimeFromTimeString($recurringTime . ':00');
                }
                return null;

            case SmsCampaign::SCHEDULE_RECURRING_HOURLY:
                $hours = $recurringIntervalHours ?? 1;
                return now()->addHours($hours);

            case SmsCampaign::SCHEDULE_SCHEDULED:
                if ($scheduledAt) {
                    return Carbon::parse($scheduledAt);
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * Queue SMS messages for a single campaign.
     */
    public function queueForCampaign(SmsCampaign $campaign): int
    {
        $query = Shipment::forCompany($campaign->company_id)
            ->where('normalized_status_id', $campaign->trigger_status_id)
            ->contactable();
        if (!empty($campaign->province_filter)) {
            $query->whereIn('consignee_province', $campaign->province_filter);
        }
        if (!empty($campaign->city_filter)) {
            $query->whereIn('consignee_city', $campaign->city_filter);
        }
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
        $triggerStatusId = $campaign->trigger_status_id;
        foreach ($shipments as $shipment) {
            // Cross-campaign dedup: skip if this waybill already got SMS
            // for the same trigger status today (from ANY campaign)
            $alreadySent = SmsSendLog::where('shipment_id', $shipment->id)
                ->where('send_date', now()->toDateString())
                ->whereIn('status', ['queued', 'sent'])
                ->whereHas('campaign', function ($q) use ($triggerStatusId) {
                    $q->where('trigger_status_id', $triggerStatusId);
                })
                ->exists();
            if ($alreadySent) {
                continue;
            }
            try {
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
                continue;
            }
        }
        // Auto-assign to SMS Operator after queuing
        if ($queued > 0) {
            $this->autoAssignMessages($campaign);
            // Update campaign status to sending
            $campaign->update([
                'campaign_status' => SmsCampaign::STATUS_SENDING,
                'total_recipients' => ($campaign->total_recipients ?? 0) + $queued,
                'last_run_at' => now(),
            ]);
        }
        return $queued;
    }

    /**
     * Auto-assign unassigned queued messages to SMS Operator(s).
     */
    protected function autoAssignMessages(SmsCampaign $campaign): void
    {
        $unassigned = SmsSendLog::where('campaign_id', $campaign->id)
            ->where('status', 'queued')
            ->whereNull('assigned_to')
            ->orderBy('id')
            ->pluck('id');

        if ($unassigned->isEmpty()) return;

        if ($campaign->assigned_operator_id) {
            SmsSendLog::whereIn('id', $unassigned)
                ->update(['assigned_to' => $campaign->assigned_operator_id]);
            return;
        }

        $operators = User::where('company_id', $campaign->company_id)
            ->where('is_active', true)
            ->role('SMS Operator')
            ->pluck('id')
            ->toArray();

        if (empty($operators)) return;

        $count = count($operators);
        foreach ($unassigned as $i => $logId) {
            SmsSendLog::where('id', $logId)
                ->update(['assigned_to' => $operators[$i % $count]]);
        }
    }

    /**
     * Process all queued SMS messages and send them.
     */
    public function processQueue(int $batchSize = 100): int
    {
        // Only process messages from NON sim_based campaigns.
        // SIM-based messages are sent by the device via the SmsBlastApiController.
        $logs = SmsSendLog::where('status', 'queued')
            ->where('send_date', now()->toDateString())
            ->whereHas('campaign', function ($q) {
                $q->where('sending_method', '!=', 'sim_based');
            })
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
     */
    protected function sendSms(SmsSendLog $log): void
    {
        // For now, simulate success:
        $log->markSent('simulated_response', 'sim_' . uniqid());
    }
}
