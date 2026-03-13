<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\TelemarketingLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\AnalyzeCallRecording;
use Illuminate\Support\Facades\Storage;

class RecordingController extends Controller
{
    /**
     * Create a draft telemarketing log row when user clicks the call button.
     * This ensures the log_id exists BEFORE the call starts, so auto-upload
     * can link the recording directly to this row.
     *
     * POST /api/telemarketing/create-draft-log
     */
    public function createDraftLog(Request $request)
    {
        $request->validate([
            'shipment_id' => 'required|integer|exists:shipments,id',
            'phone_number' => 'nullable|string|max:20',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $shipmentId = (int) $request->shipment_id;
        $phoneCalled = $request->phone_number;

        // Check if a draft already exists for this shipment + user (reuse it, no duplicates)
        $existingDraft = TelemarketingLog::where('shipment_id', $shipmentId)
            ->where('user_id', $user->id)
            ->where('status', 'draft')
            ->first();

        if ($existingDraft) {
            // Update the phone_called if different, and refresh call_started_at
            $existingDraft->update([
                'phone_called' => $phoneCalled ?? $existingDraft->phone_called,
                'call_started_at' => now(),
            ]);

            Log::info('Draft log reused', [
                'log_id' => $existingDraft->id,
                'shipment_id' => $shipmentId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'log_id' => $existingDraft->id,
                'reused' => true,
                'message' => 'Existing draft log reused.',
            ]);
        }

        // Calculate attempt number
        $shipment = Shipment::findOrFail($shipmentId);
        $attemptNo = $shipment->telemarketing_attempt_count + 1;

        // Create new draft row
        $log = TelemarketingLog::create([
            'shipment_id' => $shipmentId,
            'user_id' => $user->id,
            'status' => 'draft',
            'disposition_id' => null, // Will be set when user submits the form
            'phone_called' => $phoneCalled ?? $shipment->consignee_phone_1,
            'attempt_no' => $attemptNo,
            'call_started_at' => now(),
        ]);

        Log::info('Draft log created', [
            'log_id' => $log->id,
            'shipment_id' => $shipmentId,
            'user_id' => $user->id,
            'attempt_no' => $attemptNo,
        ]);

        return response()->json([
            'success' => true,
            'log_id' => $log->id,
            'reused' => false,
            'message' => 'Draft log created.',
        ]);
    }

    /**
     * Upload a call recording from the Android app.
     * Now primarily uses log_id from the draft row for direct matching.
     *
     * POST /api/telemarketing/upload-recording
     */
    public function upload(Request $request)
    {
        $request->validate([
            'recording' => 'required|file|max:51200', // 50MB max
            'shipment_id' => 'nullable|string',
            'log_id' => 'nullable|string',
            'phone_number' => 'nullable|string|max:20',
            'call_duration' => 'nullable|integer|min:0',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $companyId = $user->company_id;
        // Check recording mode - reject auto-uploads if mode is "manual" only
        $settings = \App\Models\CompanyTelemarketingSetting::getOrCreate($companyId);
        if ($settings->recording_mode === "manual") {
            return response()->json(["error" => "Auto-upload is disabled. Recording mode is set to manual only."], 403);
        }
        $file = $request->file('recording');

        // Try to find the matching telemarketing log (draft or completed)
        $log = $this->findMatchingLog($request, $user->id, $companyId);

        if (!$log) {
            Log::warning('Recording upload: No matching telemarketing log found', [
                'user_id' => $user->id,
                'shipment_id' => $request->shipment_id,
                'log_id' => $request->log_id,
                'phone_number' => $request->phone_number,
            ]);
            // Still save the file even without a matching log
        }

        // Generate a unique filename
        $timestamp = now()->format('Y-m-d_His');
        $extension = $file->getClientOriginalExtension() ?: 'mp3';
        $filename = "recordings/{$companyId}/{$timestamp}_{$user->id}";

        if ($log) {
            $filename .= "_log{$log->id}";
        }

        $filename .= ".{$extension}";

        // Store the file
        $path = $file->storeAs('', $filename, 'local');

        Log::info('Recording uploaded', [
            'path' => $path,
            'size' => $file->getSize(),
            'log_id' => $log?->id,
            'log_status' => $log?->status,
            'user_id' => $user->id,
        ]);

        // Update the telemarketing log with the recording path
        if ($log) {
            $log->update([
                'recording_path' => $path,
                'recording_url' => route('telemarketing.play-recording', $log->id),
            ]);

            // Also update call duration if provided and not already set
            if ($request->filled('call_duration') && (!$log->call_duration_seconds || $log->call_duration_seconds == 0)) {
                $log->update(['call_duration_seconds' => (int) $request->call_duration]);
            }
        }

        // Auto-dispatch AI analysis job if recording was linked to a log
        if ($log && $log->recording_path) {
            AnalyzeCallRecording::dispatch($log->id)->delay(now()->addSeconds(10));
        }

        return response()->json([
            'success' => true,
            'message' => 'Recording uploaded and linked successfully.',
            'log_id' => $log?->id,
            'path' => $path,
            'linked' => $log !== null,
        ]);
    }

    /**
     * Play back a recording.
     */
    public function play(TelemarketingLog $log)
    {
        $user = auth()->user();

        // Authorization: must be same company
        if ($log->shipment && $log->shipment->company_id !== $user->company_id) {
            abort(403);
        }

        if (empty($log->recording_path)) {
            abort(404, 'No recording found for this call log.');
        }

        $fullPath = storage_path('app/' . $log->recording_path);

        if (!file_exists($fullPath)) {
            abort(404, 'Recording file not found.');
        }

        // Determine MIME type
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'amr' => 'audio/amr',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/ogg',
            '3gp' => 'audio/3gpp',
            'aac' => 'audio/aac',
            'webm' => 'audio/webm',
        ];

        $mimeType = $mimeTypes[$extension] ?? 'audio/mpeg';

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="recording_' . $log->id . '.' . $extension . '"',
        ]);
    }

    /**
     * Find the matching telemarketing log for a recording upload.
     * Priority: log_id (from draft) > shipment_id > phone number > recent fallback
     */
    private function findMatchingLog(Request $request, int $userId, int $companyId): ?TelemarketingLog
    {
        // 1. Try by log_id first (most precise — from draft row)
        if ($request->filled('log_id') && is_numeric($request->log_id)) {
            $log = TelemarketingLog::where('id', $request->log_id)
                ->where('user_id', $userId)
                ->first();
            if ($log) {
                Log::info('Recording matched by log_id (draft)', ['log_id' => $log->id]);
                return $log;
            }
        }

        // 2. Try by shipment_id — find draft row first, then any recent row
        if ($request->filled('shipment_id') && is_numeric($request->shipment_id)) {
            // Prefer draft row
            $log = TelemarketingLog::where('user_id', $userId)
                ->where('shipment_id', $request->shipment_id)
                ->where('status', 'draft')
                ->whereNull('recording_path')
                ->orderBy('created_at', 'desc')
                ->first();
            if ($log) {
                Log::info('Recording matched by shipment_id (draft)', ['log_id' => $log->id]);
                return $log;
            }

            // Fall back to any recent row without recording
            $log = TelemarketingLog::where('user_id', $userId)
                ->where('shipment_id', $request->shipment_id)
                ->whereNull('recording_path')
                ->orderBy('created_at', 'desc')
                ->first();
            if ($log) {
                Log::info('Recording matched by shipment_id (any)', ['log_id' => $log->id]);
                return $log;
            }
        }

        // 2.5. Try by phone number on DRAFT logs (no time limit - draft means still active)
        if ($request->filled('phone_number')) {
            $cleanPhone2 = preg_replace('/[^0-9]/', '', $request->phone_number);
            $log = TelemarketingLog::where('user_id', $userId)
                ->where('status', 'draft')
                ->whereNull('recording_path')
                ->where(function ($q) use ($cleanPhone2) {
                    $q->where('phone_called', 'LIKE', '%' . substr($cleanPhone2, -10) . '%')
                      ->orWhereHas('shipment', function ($sq) use ($cleanPhone2) {
                          $sq->where('consignee_phone_1', 'LIKE', '%' . substr($cleanPhone2, -10) . '%')
                              ->orWhere('consignee_phone_2', 'LIKE', '%' . substr($cleanPhone2, -10) . '%');
                      });
                })
                ->orderBy('created_at', 'desc')
                ->first();
            if ($log) {
                Log::info('Recording matched by phone number (draft, no time limit)', ['log_id' => $log->id]);
                return $log;
            }
        }

        // 3. Try by phone number + recent time (within last 2 hours)
        if ($request->filled('phone_number')) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $request->phone_number);
            $timeWindow = now()->subHours(2);

            $log = TelemarketingLog::where('user_id', $userId)
                ->where('created_at', '>=', $timeWindow)
                ->whereNull('recording_path')
                ->where(function ($q) use ($cleanPhone) {
                    $q->where('phone_called', 'LIKE', '%' . substr($cleanPhone, -10) . '%')
                      ->orWhereHas('shipment', function ($sq) use ($cleanPhone) {
                          $sq->where('consignee_phone_1', 'LIKE', '%' . substr($cleanPhone, -10) . '%')
                              ->orWhere('consignee_phone_2', 'LIKE', '%' . substr($cleanPhone, -10) . '%');
                      });
                })
                ->orderBy('created_at', 'desc')
                ->first();
            if ($log) {
                Log::info('Recording matched by phone number', ['log_id' => $log->id]);
                return $log;
            }
        }

        // 4. Last resort: most recent log by this user without a recording (within last 2 hours)
        $log = TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours(2))
            ->whereNull('recording_path')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($log) {
            Log::info('Recording matched by fallback (recent)', ['log_id' => $log->id]);
        }

        return $log;
    }

    /**
     * Check if a recording exists for a shipment (used by enforcement JS).
     */
    public function checkRecording(Request $request, int $shipment)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Check if any log for this shipment by this user has a recording linked
        $hasRecording = TelemarketingLog::where('shipment_id', $shipment)
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNotNull('recording_path')
                  ->orWhereNotNull('recording_url');
            })
            ->exists();

        // Also check draft logs
        $draftLog = TelemarketingLog::where('shipment_id', $shipment)
            ->where('user_id', $user->id)
            ->where('status', 'draft')
            ->latest()
            ->first();

        $draftHasRecording = false;
        if ($draftLog) {
            $draftHasRecording = !empty($draftLog->recording_path) || !empty($draftLog->recording_url);
        }

        // Check for unlinked recordings: recordings uploaded by this user
        // that were not matched to any log (saved to disk but log_id is null)
        // Match by checking if any recording file exists for this user uploaded
        // after the draft log was created (call_started_at or created_at)
        $hasUnlinkedRecording = false;
        if ($draftLog && !$draftHasRecording && !$hasRecording) {
            $shipmentModel = \App\Models\Shipment::find($shipment);
            if ($shipmentModel) {
                $phones = array_filter([
                    $shipmentModel->consignee_phone_1,
                    $shipmentModel->consignee_phone_2,
                    $draftLog->phone_called,
                ]);

                // Check if there are any OTHER logs by this user that have recordings
                // but are not linked to any shipment (orphaned recordings)
                // These are logs where the upload happened but matching failed
                $referenceTime = $draftLog->call_started_at ?? $draftLog->created_at;

                // Look for any recording files uploaded by this user after the call started
                // that are not linked to any finalized log
                $hasUnlinkedRecording = TelemarketingLog::where('user_id', $user->id)
                    ->whereNotNull('recording_path')
                    ->where('created_at', '>=', $referenceTime)
                    ->where('shipment_id', $shipment)
                    ->exists();

                // Also check: any log by this user with recording, created after reference time,
                // that might have been saved without proper shipment linking
                if (!$hasUnlinkedRecording) {
                    // Check the recordings directory for files by this user after the call
                    $companyId = $user->company_id;
                    $recordingsDir = storage_path("app/recordings/{$companyId}");
                    if (is_dir($recordingsDir)) {
                        $referenceTimestamp = strtotime($referenceTime);
                        $files = glob($recordingsDir . "/*_{$user->id}*");
                        foreach ($files as $file) {
                            if (filemtime($file) >= $referenceTimestamp) {
                                $hasUnlinkedRecording = true;
                                // Try to link this recording to the draft log
                                $relativePath = "recordings/{$companyId}/" . basename($file);
                                $draftLog->update(['recording_path' => $relativePath]);
                                Log::info('Linked orphaned recording to draft log', [
                                    'log_id' => $draftLog->id,
                                    'recording_path' => $relativePath,
                                ]);
                                break;
                            }
                        }
                    }
                }
            }
        }

        return response()->json([
            'has_recording' => $hasRecording || $draftHasRecording || $hasUnlinkedRecording,
            'draft_log_id' => $draftLog?->id,
            'draft_has_recording' => $draftHasRecording || $hasUnlinkedRecording,
        ]);
    }

}
