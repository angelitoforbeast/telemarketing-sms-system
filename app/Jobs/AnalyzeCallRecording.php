<?php

namespace App\Jobs;

use App\Models\TelemarketingLog;
use App\Services\CallAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeCallRecording implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [30, 60, 120];

    /**
     * The maximum number of seconds the job can run.
     */
    public $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $telemarketingLogId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $log = TelemarketingLog::find($this->telemarketingLogId);

        if (!$log) {
            Log::warning('AnalyzeCallRecording: Log not found', ['id' => $this->telemarketingLogId]);
            return;
        }

        // Skip if fully analyzed (all AI fields populated)
        if ($log->isFullyAnalyzed()) {
            Log::info('AnalyzeCallRecording: Fully analyzed, skipping', ['id' => $this->telemarketingLogId]);
            return;
        }

        // Skip if no recording
        if (!$log->hasRecording()) {
            Log::info('AnalyzeCallRecording: No recording found, skipping', ['id' => $this->telemarketingLogId]);
            return;
        }

        try {
            $service = new CallAnalysisService();
            $result = $service->analyze($log);

            if ($result['success']) {
                Log::info('AnalyzeCallRecording: Successfully analyzed', [
                    'id' => $this->telemarketingLogId,
                    'message' => $result['message'] ?? 'OK',
                ]);
            } else {
                Log::warning('AnalyzeCallRecording: Analysis returned failure', [
                    'id' => $this->telemarketingLogId,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
                // Throw to trigger retry
                throw new \Exception($result['message'] ?? 'Analysis failed');
            }
        } catch (\Exception $e) {
            Log::error('AnalyzeCallRecording: Exception during analysis', [
                'id' => $this->telemarketingLogId,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeCallRecording: Job permanently failed after all retries', [
            'id' => $this->telemarketingLogId,
            'error' => $exception->getMessage(),
        ]);
    }
}
