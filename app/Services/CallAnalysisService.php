<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\TelemarketingLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CallAnalysisService
{
    /**
     * Analyze a call recording: transcribe + reformat as dialogue + summarize + AI disposition.
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

            // 3. Transcribe using OpenAI Whisper (verbose JSON for better completeness)
            $rawTranscription = $this->transcribe($processedPath);
            if (empty($rawTranscription)) {
                return ['success' => false, 'message' => 'Transcription returned empty result.'];
            }

            // 4. Get the AI prompt from settings
            $analysisPrompt = AiSetting::getValue('call_analysis_prompt', $this->getDefaultPrompt());

            // 5. Get available dispositions from DB (for the log's company or system-wide)
            $dispositions = $this->getAvailableDispositions($log);

            // 6. Send raw transcription to GPT for dialogue reformat + summary + disposition (one API call)
            $result = $this->reformatAndSummarize($rawTranscription, $analysisPrompt, $dispositions);

            // 7. Resolve disposition ID from the AI's chosen disposition name
            $aiDispositionId = $this->resolveDispositionId($result['disposition'], $dispositions);

            // 8. Save results
            $log->update([
                'transcription' => $result['dialogue'],
                'ai_summary' => $result['summary'],
                'ai_analyzed_at' => now(),
                'ai_disposition_id' => $aiDispositionId,
            ]);

            // 9. Clean up temp file if we created one
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
     * Get available dispositions for the log's company context.
     * Returns array of ['id' => int, 'name' => string]
     */
    protected function getAvailableDispositions(TelemarketingLog $log): array
    {
        $companyId = null;
        if ($log->shipment) {
            $companyId = $log->shipment->company_id;
        }

        $query = DB::table('telemarketing_dispositions')
            ->select('id', 'name', 'code')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id'); // system dispositions
                if ($companyId) {
                    $q->orWhere('company_id', $companyId); // company custom dispositions
                }
            })
            ->orderBy('sort_order')
            ->get();

        return $query->map(fn($d) => [
            'id' => $d->id,
            'name' => $d->name,
            'code' => $d->code,
        ])->toArray();
    }

    /**
     * Resolve a disposition name (from AI) to its database ID.
     * Uses fuzzy matching to handle slight variations.
     */
    protected function resolveDispositionId(string $aiDisposition, array $dispositions): ?int
    {
        if (empty($aiDisposition)) {
            return null;
        }

        $aiDisposition = strtolower(trim($aiDisposition));

        // Exact match first
        foreach ($dispositions as $d) {
            if (strtolower($d['name']) === $aiDisposition) {
                return $d['id'];
            }
        }

        // Partial match (AI response contains the disposition name or vice versa)
        foreach ($dispositions as $d) {
            $name = strtolower($d['name']);
            if (str_contains($aiDisposition, $name) || str_contains($name, $aiDisposition)) {
                return $d['id'];
            }
        }

        // Code match
        foreach ($dispositions as $d) {
            if (strtolower($d['code']) === str_replace([' ', '-'], '_', $aiDisposition)) {
                return $d['id'];
            }
        }

        // Default to "Other" if no match found
        foreach ($dispositions as $d) {
            if ($d['code'] === 'other') {
                return $d['id'];
            }
        }

        return null;
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
     * Transcribe audio using OpenAI Whisper API with verbose output for completeness.
     */
    protected function transcribe(string $audioPath): string
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        $baseUrl = config('services.openai.base_url', env('OPENAI_BASE_URL', 'https://api.openai.com/v1'));

        $response = Http::timeout(120)
            ->withToken($apiKey)
            ->asMultipart()
            ->attach('file', file_get_contents($audioPath), basename($audioPath))
            ->post($baseUrl . '/audio/transcriptions', [
                ['name' => 'model', 'contents' => 'whisper-1'],
                ['name' => 'language', 'contents' => 'tl'],
                ['name' => 'response_format', 'contents' => 'verbose_json'],
                ['name' => 'prompt', 'contents' => 'Hello po, magandang hapon po. Ito po si Agent calling from the company. Kumusta po kayo? This is a telemarketing phone call in Filipino/Tagalog. Transcribe everything from the very beginning including greetings and introductions.'],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Whisper API error: ' . $response->body());
        }

        $data = $response->json();

        // Extract full text from verbose JSON segments for completeness
        if (isset($data['segments']) && is_array($data['segments'])) {
            $fullText = '';
            foreach ($data['segments'] as $segment) {
                $fullText .= trim($segment['text'] ?? '') . ' ';
            }
            $fullText = trim($fullText);
            if (!empty($fullText)) {
                return $fullText;
            }
        }

        // Fallback to top-level text
        if (isset($data['text'])) {
            return trim($data['text']);
        }

        // Fallback for plain text response
        return trim($response->body());
    }

    /**
     * Send raw transcription to GPT for dialogue reformatting, summary, AND disposition in one API call.
     * Returns ['dialogue' => string, 'summary' => string, 'disposition' => string]
     */
    protected function reformatAndSummarize(string $rawTranscription, string $analysisPrompt, array $dispositions): array
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        $baseUrl = config('services.openai.base_url', env('OPENAI_BASE_URL', 'https://api.openai.com/v1'));

        // Build the dispositions list for the prompt
        $dispositionNames = array_map(fn($d) => $d['name'], $dispositions);
        $dispositionList = implode("\n", array_map(fn($name) => "- {$name}", $dispositionNames));

        $systemPrompt = <<<PROMPT
You are an AI assistant that processes telemarketing call transcriptions. You will receive a raw transcription from a speech-to-text system. You must do THREE things:

**TASK 1 - DIALOGUE REFORMAT:**
Reformat the raw transcription into a clean, readable dialogue format. Rules:
- Label each line with "AGENT:" or "CUSTOMER:" based on context clues
- The agent is typically the one asking questions, using "po/ma'am/sir", and representing the company
- The customer is the one answering questions about their order, expressing concerns, or making decisions
- Keep the original language (Filipino/Tagalog/English mix) — do NOT translate
- Fix obvious speech-to-text errors if you can infer the correct word
- Include ALL parts of the conversation — do not skip or summarize any part
- Each speaker turn should be on its own line
- If you cannot determine the speaker, use "UNKNOWN:"

**TASK 2 - SUMMARY:**
{$analysisPrompt}

**TASK 3 - DISPOSITION:**
Based on the conversation, determine the most appropriate call disposition from the following options:
{$dispositionList}

Choose the single best matching disposition. Consider:
- If the customer agreed to accept delivery → "Answered - Will Accept"
- If the customer wants redelivery → "Answered - Request Redeliver"
- If the customer refused or wants to return → "Answered - Refused / RTS"
- If the customer asked to be called back later → "Answered - Callback Requested"
- If the customer expressed interest in reordering → "Answered - Reorder Interest"
- If no one answered → "No Answer"
- If the line was busy → "Busy"
- If it was a wrong number → "Wrong Number"
- If the number was not in service → "Not in Service"
- If it went to voicemail → "Voicemail"
- If none of the above clearly applies → "Other"

**OUTPUT FORMAT:**
You MUST respond in EXACTLY this format with the three sections separated by the delimiters:

---DIALOGUE---
(the reformatted dialogue here)

---SUMMARY---
(the summary here)

---DISPOSITION---
(the exact disposition name from the list above, nothing else)
PROMPT;

        $response = Http::timeout(90)
            ->withToken($apiKey)
            ->post($baseUrl . '/chat/completions', [
                'model' => 'gpt-4.1-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => "Here is the raw call transcription:\n\n" . $rawTranscription,
                    ],
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('GPT API error: ' . $response->body());
        }

        $content = $response->json()['choices'][0]['message']['content'] ?? '';

        return $this->parseGptResponse($content, $rawTranscription);
    }

    /**
     * Parse GPT response into dialogue, summary, and disposition parts.
     */
    protected function parseGptResponse(string $content, string $fallbackTranscription): array
    {
        $dialogue = $fallbackTranscription;
        $summary = 'No summary generated.';
        $disposition = '';

        // Try to split by our delimiters
        if (str_contains($content, '---DIALOGUE---') && str_contains($content, '---SUMMARY---')) {
            // Extract disposition first if present
            if (str_contains($content, '---DISPOSITION---')) {
                $parts = explode('---DISPOSITION---', $content, 2);
                $disposition = trim($parts[1] ?? '');
                $content = $parts[0]; // Remove disposition part for further parsing
            }

            // Extract dialogue and summary
            $parts = explode('---SUMMARY---', $content, 2);
            $dialoguePart = str_replace('---DIALOGUE---', '', $parts[0]);
            $summaryPart = $parts[1] ?? '';

            $dialogue = trim($dialoguePart);
            $summary = trim($summaryPart);
        } else {
            // Fallback: if GPT didn't follow format, use entire response as summary
            // and keep raw transcription as dialogue
            $summary = trim($content);
        }

        // Ensure we have content
        if (empty($dialogue)) {
            $dialogue = $fallbackTranscription;
        }
        if (empty($summary)) {
            $summary = 'No summary generated.';
        }

        return [
            'dialogue' => $dialogue,
            'summary' => $summary,
            'disposition' => $disposition,
        ];
    }

    /**
     * Get the default analysis prompt.
     */
    protected function getDefaultPrompt(): string
    {
        return 'Analyze this telemarketing call and provide a concise summary including: (1) Purpose of the call, (2) Customer response or concern, (3) Resolution or next steps, (4) Overall sentiment (positive/negative/neutral). Keep it in 3-5 sentences.';
    }
}
