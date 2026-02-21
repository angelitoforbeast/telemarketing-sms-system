<?php

namespace App\Jobs;

use App\Services\Telemarketing\TelemarketingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAutoAssignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries   = 1;

    public function __construct(
        private int $companyId,
        private ?int $ruleId = null
    ) {}

    public function handle(): void
    {
        try {
            $service = app(TelemarketingService::class);
            $results = $service->autoAssignByRules($this->companyId, $this->ruleId);

            $totalAssigned = collect($results)->sum('assigned');
            $details = collect($results)->map(fn ($r) => "{$r['rule']}: {$r['assigned']}")->join(', ');

            Log::info("Auto-assign completed for company #{$this->companyId}: {$totalAssigned} assigned ({$details})");
        } catch (\Throwable $e) {
            Log::error("Auto-assign failed for company #{$this->companyId}: {$e->getMessage()}");
        }
    }
}
