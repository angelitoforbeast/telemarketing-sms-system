<?php

namespace App\Jobs;

use App\Services\Sms\SmsCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendQueuedSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public int $batchSize = 100
    ) {}

    public function handle(SmsCampaignService $smsService): void
    {
        $smsService->processQueue($this->batchSize);
    }
}
