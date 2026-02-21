<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\Import\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes max

    public function __construct(
        public ImportJob $importJob
    ) {}

    public function handle(ImportService $importService): void
    {
        $importService->processImportJob($this->importJob);
    }

    public function failed(\Throwable $exception): void
    {
        $this->importJob->markFailed([
            'message' => $exception->getMessage(),
        ]);
    }
}
