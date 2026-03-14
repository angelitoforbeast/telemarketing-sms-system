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
     * Analyze a call recording: transcribe + reformat as dialogue + summarize + AI disposition + extended analysis.
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

            // 5b. Get shipment context for smarter AI disposition
            $shipmentContext = $this->getShipmentContext($log);

            // 6. Send raw transcription to GPT for full analysis (one API call)
            $result = $this->reformatAndSummarize($rawTranscription, $analysisPrompt, $dispositions, $shipmentContext);

            // 7. Resolve disposition ID from the AI's chosen disposition name
            $aiDispositionId = $this->resolveDispositionId($result['disposition'], $dispositions);

            // 8. Save results (including new analysis fields)
            $log->update([
                'transcription' => $result['dialogue'],
                'ai_summary' => $result['summary'],
                'ai_analyzed_at' => now(),
                'ai_disposition_id' => $aiDispositionId,
                'ai_sentiment' => $result['sentiment'] ?? null,
                'ai_agent_score' => $result['agent_score'] ?? null,
                'ai_customer_intent' => $result['customer_intent'] ?? null,
                'ai_key_issues' => $result['key_issues'] ?? null,
                'ai_action_items' => $result['action_items'] ?? null,
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
     * Send raw transcription to GPT for dialogue reformatting, summary, disposition, AND extended analysis in one API call.
     * Returns ['dialogue' => string, 'summary' => string, 'disposition' => string, 'sentiment' => string, 'agent_score' => int, 'customer_intent' => string, 'key_issues' => string, 'action_items' => string]
     */
    protected function reformatAndSummarize(string $rawTranscription, string $analysisPrompt, array $dispositions, string $shipmentContext = ''): array
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        $baseUrl = config('services.openai.base_url', env('OPENAI_BASE_URL', 'https://api.openai.com/v1'));

        // Build the dispositions list for the prompt
        $dispositionNames = array_map(fn($d) => $d['name'], $dispositions);
        $dispositionList = implode("\n", array_map(fn($name) => "- {$name}", $dispositionNames));

        $systemPrompt = <<<PROMPT
You are an AI assistant that processes telemarketing call transcriptions. You will receive a raw transcription from a speech-to-text system. You must do SEVEN things:

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

**CALL CONTEXT:**
{$shipmentContext}

**TASK 3 - DISPOSITION:**
Based on the conversation AND the call context above, determine the most appropriate call disposition from the following options:
{$dispositionList}

Choose the single best matching disposition using these STRICT rules:
- "Answered - Reorder Interest" → Use when the customer is a RETURNING/EXISTING customer who shows interest in or agrees to buy/order again. This includes accepting promotional offers, buy-one-take-one deals, or any new purchase by a customer who has previously received products. This is the MOST COMMON disposition for Delivered shipments.
- "Answered - Will Accept" → Use ONLY when the customer agrees to ACCEPT A PENDING DELIVERY (e.g., a returned/for-return shipment they now agree to receive). Do NOT use this for returning customers agreeing to reorder.
- "Answered - Request Redeliver" → Use when the customer wants a PREVIOUSLY FAILED or RETURNED delivery to be sent again to them.
- "Answered - Refused / RTS" → Use ONLY when the customer CLEARLY and EXPLICITLY refuses the offer or requests return to sender. Do NOT use for undecided, hesitant, or "I'll think about it" responses.
- "Answered - Callback Requested" → Use when the customer or someone on their behalf asks to be called back later. This includes: target person not available, "I'll think about it and you call me back", "my spouse/child handles this, they're not here", "I have no cash right now, call later".
- "Answered - Call ended" → Use ONLY as a last resort when a real conversation happened but NO other disposition clearly fits. Do NOT use this for short/no-response calls.
- "No Answer" → No one answered or responded to the call, OR the call connected but there was no meaningful response/conversation.
- "Busy" → Line was busy, could not connect.
- "Wrong Number" → Reached the wrong person entirely.
- "Not in Service" → Number is not in service or disconnected.
- "Voicemail" → Reached voicemail.
- "Other" → Use only if absolutely none of the above categories fit.

**TASK 4 - SENTIMENT:**
Determine the overall sentiment of the customer during the call. Must be exactly one of: positive, neutral, negative

**TASK 5 - AGENT SCORE:**
Rate the telemarketer/agent's performance on a scale of 1-10 based on:
- Professionalism and politeness
- Product knowledge
- Handling of objections
- Call control and flow
- Closing ability
Output just the number (1-10).

**TASK 6 - CUSTOMER INTENT:**
Determine the primary intent of the customer. Must be exactly one of: reorder, complaint, inquiry, refusal, acceptance, callback, other

**TASK 7 - KEY ISSUES & ACTION ITEMS:**
- Key Issues: List any problems, complaints, or concerns raised during the call. Keep it brief (1-2 sentences). Write "None" if no issues.
- Action Items: List any follow-up actions needed after this call. Keep it brief (1-2 sentences). Write "None" if no actions needed.

**OUTPUT FORMAT:**
You MUST respond in EXACTLY this format with sections separated by the delimiters:

---DIALOGUE---
(the reformatted dialogue here)

---SUMMARY---
(the summary here)

---DISPOSITION---
(the exact disposition name from the list above, nothing else)

---SENTIMENT---
(positive, neutral, or negative)

---AGENT_SCORE---
(number 1-10)

---CUSTOMER_INTENT---
(reorder, complaint, inquiry, refusal, acceptance, callback, or other)

---KEY_ISSUES---
(brief key issues or "None")

---ACTION_ITEMS---
(brief action items or "None")
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
                'max_tokens' => 3000,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('GPT API error: ' . $response->body());
        }

        $content = $response->json()['choices'][0]['message']['content'] ?? '';

        return $this->parseGptResponse($content, $rawTranscription);
    }

    /**
     * Parse GPT response into all analysis parts.
     */
    protected function parseGptResponse(string $content, string $fallbackTranscription): array
    {
        $result = [
            'dialogue' => $fallbackTranscription,
            'summary' => 'No summary generated.',
            'disposition' => '',
            'sentiment' => null,
            'agent_score' => null,
            'customer_intent' => null,
            'key_issues' => null,
            'action_items' => null,
        ];

        // Extract each section using delimiters
        $sections = [
            'action_items' => '---ACTION_ITEMS---',
            'key_issues' => '---KEY_ISSUES---',
            'customer_intent' => '---CUSTOMER_INTENT---',
            'agent_score' => '---AGENT_SCORE---',
            'sentiment' => '---SENTIMENT---',
            'disposition' => '---DISPOSITION---',
            'summary' => '---SUMMARY---',
            'dialogue' => '---DIALOGUE---',
        ];

        $remaining = $content;

        // Extract from bottom up to avoid delimiter conflicts
        foreach ($sections as $key => $delimiter) {
            if (str_contains($remaining, $delimiter)) {
                $parts = explode($delimiter, $remaining, 2);
                $remaining = $parts[0];
                $value = trim($parts[1] ?? '');

                if (!empty($value)) {
                    if ($key === 'agent_score') {
                        // Extract just the number
                        preg_match('/(\d+)/', $value, $matches);
                        $result[$key] = isset($matches[1]) ? min(10, max(1, (int)$matches[1])) : null;
                    } elseif ($key === 'sentiment') {
                        $value = strtolower(trim($value));
                        $result[$key] = in_array($value, ['positive', 'neutral', 'negative']) ? $value : 'neutral';
                    } elseif ($key === 'customer_intent') {
                        $value = strtolower(trim($value));
                        $allowed = ['reorder', 'complaint', 'inquiry', 'refusal', 'acceptance', 'callback', 'other'];
                        $result[$key] = in_array($value, $allowed) ? $value : 'other';
                    } else {
                        $result[$key] = $value;
                    }
                }
            }
        }

        // Ensure we have content
        if (empty($result['dialogue'])) {
            $result['dialogue'] = $fallbackTranscription;
        }
        if (empty($result['summary'])) {
            $result['summary'] = 'No summary generated.';
        }

        return $result;
    }

    /**
     * Get shipment context for the AI to understand the call purpose.
     */
    protected function getShipmentContext(TelemarketingLog $log): string
    {
        $shipment = $log->shipment;
        if (!$shipment) {
            return 'No shipment context available.';
        }

        // Get the shipment status name
        $statusName = 'Unknown';
        if ($shipment->normalized_status_id) {
            $status = DB::table('shipment_statuses')
                ->where('id', $shipment->normalized_status_id)
                ->first();
            if ($status) {
                $statusName = $status->name;
            }
        }

        // Map shipment status to call goal
        $callGoalMap = [
            'Delivered' => 'This customer previously RECEIVED and ACCEPTED their order. The purpose of this call is to offer them a REORDER or promotional deal. If the customer agrees to buy/order again, the correct disposition is "Answered - Reorder Interest" (NOT "Will Accept").',
            'For Return' => 'This customer\'s order is currently BEING RETURNED (failed delivery or customer initially refused). The purpose of this call is to convince them to ACCEPT the delivery or arrange REDELIVERY. If they agree to accept, use "Answered - Will Accept". If they want it resent, use "Answered - Request Redeliver".',
            'Returned' => 'This customer\'s order was RETURNED to sender. The purpose of this call is to convince them to accept a NEW delivery or place a new order. If they agree to accept redelivery, use "Answered - Will Accept" or "Answered - Request Redeliver".',
            'Failed Delivery' => 'This customer\'s delivery FAILED. The purpose of this call is to arrange redelivery or confirm the address. If they want it resent, use "Answered - Request Redeliver".',
            'Delivering' => 'This customer\'s order is currently BEING DELIVERED. The purpose of this call is to confirm delivery details or follow up.',
        ];

        $callGoal = $callGoalMap[$statusName] ?? 'The shipment status is "' . $statusName . '". Determine the appropriate disposition based on the conversation.';

        // Get customer info
        $customerName = $shipment->consignee_name ?? 'Unknown';
        $attemptCount = $shipment->telemarketing_attempt_count ?? 0;

        return "Customer: {$customerName}\nShipment Status: {$statusName}\nTelemarketing Attempt #: {$attemptCount}\nCall Goal: {$callGoal}";
    }

    /**
     * Get the default analysis prompt.
     */
    protected function getDefaultPrompt(): string
    {
        return 'Analyze this telemarketing call and provide a concise summary including: (1) Purpose of the call, (2) Customer response or concern, (3) Resolution or next steps, (4) Overall sentiment (positive/negative/neutral). Keep it in 3-5 sentences.';
    }
}
