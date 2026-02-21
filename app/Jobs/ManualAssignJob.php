<?php

namespace App\Jobs;

use App\Services\Telemarketing\TelemarketingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ManualAssignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries   = 1;

    public function __construct(
        private array $shipmentIds,
        private int $telemarketerUserId,
        private int $companyId
    ) {}

    public function handle(): void
    {
        try {
            $service = app(TelemarketingService::class);
            $count = $service->manualAssign($this->shipmentIds, $this->telemarketerUserId, $this->companyId);

            Log::info("Manual assign completed: {$count} shipments assigned to user #{$this->telemarketerUserId} for company #{$this->companyId}");
        } catch (\Throwable $e) {
            Log::error("Manual assign failed for company #{$this->companyId}: {$e->getMessage()}");
        }
    }
}
