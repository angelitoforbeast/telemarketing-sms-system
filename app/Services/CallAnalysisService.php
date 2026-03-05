<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\TelemarketingLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CallAnalysisService
{
    /**
     * Analyze a call recording: transcribe + summarize.
     *
     * @param TelemarketingLog $log
     * @return array ['success' => bool, 'message' => string]
     */
    public function analyze(TelemarketingLog $log): array
    {
        // 1. Validate recording exists
        if (!$log->hasRecording()) {
            return ['success' => false, 'message' => 'No recording found for this call log.'];
        }

        $recordingPath = Storage::path($log->recording_path);
        if (!file_exists($recordingPath)) {
            return ['success' => false, 'message' => 'Recording file not found on disk.'];
        }

        try {
            // 2. Convert to supported format if needed (AAC → M4A)
            $processedPath = $this->ensureSupportedFormat($recordingPath);

            // 3. Transcribe using OpenAI Whisper
            $transcription = $this->transcribe($processedPath);
            if (empty($transcription)) {
                return ['success' => false, 'message' => 'Transcription returned empty result.'];
            }

            // 4. Get the AI prompt from settings
            $prompt = AiSetting::getValue('call_analysis_prompt', 'Summarize this call transcript.');

            // 5. Generate summary using GPT
            $summary = $this->summarize($transcription, $prompt);

            // 6. Save results
            $log->update([
                'transcription' => $transcription,
                'ai_summary' => $summary,
                'ai_analyzed_at' => now(),
            ]);

            // 7. Clean up temp file if we created one
            if ($processedPath !== $recordingPath && file_exists($processedPath)) {
                unlink($processedPath);
            }

            return ['success' => true, 'message' => 'Call analyzed successfully.'];

        } catch (\Exception $e) {
            Log::error('Call analysis failed', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Analysis failed: ' . $e->getMessage()];
        }
    }

    /**
     * Convert AAC files to M4A format (Whisper doesn't support .aac directly).
     */
    protected function ensureSupportedFormat(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Supported formats by Whisper API
        $supported = ['mp3', 'mpga', 'm4a', 'wav', 'webm', 'mp4'];

        if (in_array($ext, $supported)) {
            return $path;
        }

        // Convert to M4A using ffmpeg
        $outputPath = sys_get_temp_dir() . '/' . uniqid('call_') . '.m4a';

        $command = sprintf(
            'ffmpeg -i %s -c:a copy %s -y 2>&1',
            escapeshellarg($path),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputPath)) {
            throw new \RuntimeException('Failed to convert audio format: ' . implode("\n", $output));
        }

        return $outputPath;
    }

    /**
     * Transcribe audio using OpenAI Whisper API.
     */
    protected function transcribe(string $audioPath): string
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        $baseUrl = config('services.openai.base_url', env('OPENAI_BASE_URL', 'https://api.openai.com/v1'));

        $response = Http::timeout(120)
            ->withToken($apiKey)
            ->attach('file', file_get_contents($audioPath), basename($audioPath))
            ->post($baseUrl . '/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => 'tl', // Tagalog
                'response_format' => 'text',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Whisper API error: ' . $response->body());
        }

        $body = trim($response->body());

        // Handle case where API returns JSON instead of plain text
        $decoded = json_decode($body, true);
        if (is_array($decoded) && isset($decoded['text'])) {
            return trim($decoded['text']);
        }

        return $body;
    }

    /**
     * Summarize transcript using GPT-4.1-mini.
     */
    protected function summarize(string $transcription, string $prompt): string
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        $baseUrl = config('services.openai.base_url', env('OPENAI_BASE_URL', 'https://api.openai.com/v1'));

        $response = Http::timeout(60)
            ->withToken($apiKey)
            ->post($baseUrl . '/chat/completions', [
                'model' => 'gpt-4.1-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $prompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => "Here is the call transcription:\n\n" . $transcription,
                    ],
                ],
                'temperature' => 0.3,
                'max_tokens' => 500,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('GPT API error: ' . $response->body());
        }

        $data = $response->json();
        return $data['choices'][0]['message']['content'] ?? 'No summary generated.';
    }
}
