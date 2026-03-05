<?php

namespace App\Console\Commands;

use App\Models\SmsCampaign;
use App\Models\SmsDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessSmsCampaigns extends Command
{
    protected $signature = 'sms:process-campaigns';
    protected $description = 'Process scheduled and recurring SMS campaigns, queue messages when due.';

    public function handle()
    {
        $this->info('Processing SMS campaigns...');

        // 1. Process one-time scheduled campaigns
        $this->processScheduledCampaigns();

        // 2. Process recurring daily campaigns
        $this->processRecurringDailyCampaigns();

        // 3. Process recurring hourly campaigns
        $this->processRecurringHourlyCampaigns();

        // 4. Process custom cron campaigns
        $this->processCustomCronCampaigns();

        // 5. Reset daily device counters if new day
        $this->resetDailyCounters();

        $this->info('Done.');
        return 0;
    }

    protected function processScheduledCampaigns()
    {
        $campaigns = SmsCampaign::where('campaign_status', SmsCampaign::STATUS_QUEUED)
            ->where('schedule_type', SmsCampaign::SCHEDULE_SCHEDULED)
            ->where('is_active', true)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($campaigns as $campaign) {
            $count = $campaign->queueMessages();
            $this->info("Scheduled campaign '{$campaign->name}': {$count} messages queued.");
        }
    }

    protected function processRecurringDailyCampaigns()
    {
        $campaigns = SmsCampaign::where('schedule_type', SmsCampaign::SCHEDULE_RECURRING_DAILY)
            ->where('is_active', true)
            ->whereIn('campaign_status', [SmsCampaign::STATUS_QUEUED, SmsCampaign::STATUS_COMPLETED, SmsCampaign::STATUS_SENDING])
            ->whereNotNull('recurring_time')
            ->get();

        $now = now();

        foreach ($campaigns as $campaign) {
            $targetTime = Carbon::parse($now->toDateString() . ' ' . $campaign->recurring_time);

            // Check if it's time to run (within 5 minute window)
            if ($now->between($targetTime, $targetTime->copy()->addMinutes(5))) {
                // Check if already ran today
                if ($campaign->last_run_at && $campaign->last_run_at->isToday()) {
                    continue;
                }

                $count = $campaign->queueMessages();
                $this->info("Daily campaign '{$campaign->name}': {$count} messages queued.");
            }
        }
    }

    protected function processRecurringHourlyCampaigns()
    {
        $campaigns = SmsCampaign::where('schedule_type', SmsCampaign::SCHEDULE_RECURRING_HOURLY)
            ->where('is_active', true)
            ->whereIn('campaign_status', [SmsCampaign::STATUS_QUEUED, SmsCampaign::STATUS_COMPLETED, SmsCampaign::STATUS_SENDING])
            ->whereNotNull('recurring_interval_hours')
            ->get();

        foreach ($campaigns as $campaign) {
            $interval = $campaign->recurring_interval_hours;

            // Check if enough time has passed since last run
            if ($campaign->last_run_at && $campaign->last_run_at->diffInHours(now()) < $interval) {
                continue;
            }

            // Only run during business hours (8am - 8pm)
            $hour = now()->hour;
            if ($hour < 8 || $hour >= 20) {
                continue;
            }

            $count = $campaign->queueMessages();
            $this->info("Hourly campaign '{$campaign->name}': {$count} messages queued.");
        }
    }

    protected function processCustomCronCampaigns()
    {
        $campaigns = SmsCampaign::where('schedule_type', SmsCampaign::SCHEDULE_CUSTOM_CRON)
            ->where('is_active', true)
            ->whereIn('campaign_status', [SmsCampaign::STATUS_QUEUED, SmsCampaign::STATUS_COMPLETED, SmsCampaign::STATUS_SENDING])
            ->whereNotNull('cron_expression')
            ->get();

        foreach ($campaigns as $campaign) {
            if ($this->cronMatches($campaign->cron_expression)) {
                // Check if already ran this minute
                if ($campaign->last_run_at && $campaign->last_run_at->diffInMinutes(now()) < 1) {
                    continue;
                }

                $count = $campaign->queueMessages();
                $this->info("Cron campaign '{$campaign->name}': {$count} messages queued.");
            }
        }
    }

    protected function resetDailyCounters()
    {
        // Reset device daily counters at midnight
        SmsDevice::where('messages_sent_today', '>', 0)
            ->whereDate('updated_at', '<', now()->toDateString())
            ->update(['messages_sent_today' => 0]);
    }

    /**
     * Simple cron expression matcher (minute hour day-of-month month day-of-week).
     */
    protected function cronMatches(string $expression): bool
    {
        $parts = explode(' ', trim($expression));
        if (count($parts) !== 5) return false;

        $now = now();
        $checks = [
            (int) $now->minute,
            (int) $now->hour,
            (int) $now->day,
            (int) $now->month,
            (int) $now->dayOfWeek,
        ];

        foreach ($parts as $i => $part) {
            if ($part === '*') continue;

            // Handle comma-separated values
            $values = explode(',', $part);
            $match = false;

            foreach ($values as $val) {
                // Handle ranges (e.g., 1-5)
                if (str_contains($val, '-')) {
                    [$min, $max] = explode('-', $val);
                    if ($checks[$i] >= (int)$min && $checks[$i] <= (int)$max) {
                        $match = true;
                        break;
                    }
                }
                // Handle step values (e.g., */5)
                elseif (str_contains($val, '/')) {
                    $step = (int) explode('/', $val)[1];
                    if ($step > 0 && $checks[$i] % $step === 0) {
                        $match = true;
                        break;
                    }
                }
                // Exact match
                elseif ((int)$val === $checks[$i]) {
                    $match = true;
                    break;
                }
            }

            if (!$match) return false;
        }

        return true;
    }
}
