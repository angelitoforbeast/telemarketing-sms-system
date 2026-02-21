<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\Import\FileParserService;
use App\Services\Import\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries = 1;

    public function __construct(public ImportJob $importJob)
    {}

    public function handle(FileParserService $fileParser, ImportService $importService)
    {
        if ($this->importJob->status !== 'queued') {
            return; // Already processed or failed
        }

        $this->importJob->markProcessing();

        try {
            // Step 1: Parse the raw file into raw_jnt_rows or raw_flash_rows
            $totalRows = $fileParser->parseAndStoreRawRows($this->importJob);
            $this->importJob->update(['total_rows' => $totalRows]);

            // Step 2: Normalize the raw rows into the main shipments table
            if ($this->importJob->courier === 'jnt') {
                $importService->processJntRows($this->importJob);
            } else {
                $importService->processFlashRows($this->importJob);
            }

            // Step 3: Mark as completed
            $this->importJob->markCompleted();

        } catch (\Throwable $e) {
            Log::error("Import job failed for ID: {$this->importJob->id}", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->importJob->markFailed(['message' => $e->getMessage()]);
        }
    }
}
