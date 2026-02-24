<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\TelemarketingLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecordingController extends Controller
{
    /**
     * Upload a call recording from the Android app.
     * Matches the recording to the most recent telemarketing log entry.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'recording' => 'required|file|mimes:mp3,m4a,amr,wav,ogg,3gp,aac,opus,webm|max:51200', // 50MB max
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
        $file = $request->file('recording');

        // Try to find the matching telemarketing log
        $log = $this->findMatchingLog($request, $user->id, $companyId);

        if (!$log) {
            Log::warning('Recording upload: No matching telemarketing log found', [
                'user_id' => $user->id,
                'shipment_id' => $request->shipment_id,
                'phone_number' => $request->phone_number,
            ]);

            // Still save the file even without a matching log — we can link it later
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

        return response()->json([
            'success' => true,
            'message' => 'Recording uploaded successfully.',
            'log_id' => $log?->id,
            'path' => $path,
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
     */
    private function findMatchingLog(Request $request, int $userId, int $companyId): ?TelemarketingLog
    {
        // Try by log_id first (most precise)
        if ($request->filled('log_id') && is_numeric($request->log_id)) {
            $log = TelemarketingLog::where('id', $request->log_id)
                ->where('user_id', $userId)
                ->first();
            if ($log) return $log;
        }

        // Try by shipment_id
        if ($request->filled('shipment_id') && is_numeric($request->shipment_id)) {
            $log = TelemarketingLog::where('user_id', $userId)
                ->where('shipment_id', $request->shipment_id)
                ->whereNull('recording_path')
                ->orderBy('created_at', 'desc')
                ->first();
            if ($log) return $log;
        }

        // Try by phone number + recent time (within last 10 minutes)
        if ($request->filled('phone_number')) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $request->phone_number);
            $tenMinutesAgo = now()->subMinutes(10);

            $log = TelemarketingLog::where('user_id', $userId)
                ->where('created_at', '>=', $tenMinutesAgo)
                ->whereNull('recording_path')
                ->where(function ($q) use ($cleanPhone) {
                    $q->where('phone_called', 'LIKE', '%' . substr($cleanPhone, -10) . '%')
                      ->orWhereHas('shipment', function ($sq) use ($cleanPhone) {
                          $sq->where('consignee_phone', 'LIKE', '%' . substr($cleanPhone, -10) . '%');
                      });
                })
                ->orderBy('created_at', 'desc')
                ->first();
            if ($log) return $log;
        }

        // Last resort: most recent log by this user without a recording (within last 10 minutes)
        return TelemarketingLog::where('user_id', $userId)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->whereNull('recording_path')
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
