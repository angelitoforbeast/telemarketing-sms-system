<?php
namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsCampaign;
use App\Models\SmsSendLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SmsBlastController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $assignedStats = SmsSendLog::where('assigned_to', $user->id)
            ->select(
                DB::raw("SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as pending"),
                DB::raw("SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
                DB::raw("COUNT(*) as total")
            )->first();
        $campaigns = SmsCampaign::where('company_id', $user->company_id)
            ->where('campaign_status', 'sending')
            ->whereHas('sendLogs', function ($q) use ($user) {
                $q->where('assigned_to', $user->id);
            })->get();
        return view('sms.blast-dashboard', compact('user', 'assignedStats', 'campaigns'));
    }

    public function status(Request $request)
    {
        $user = $request->user();
        $stats = SmsSendLog::where('assigned_to', $user->id)
            ->select(
                DB::raw("SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as pending"),
                DB::raw("SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
                DB::raw("COUNT(*) as total")
            )->first();
        return response()->json([
            'pending' => (int) ($stats->pending ?? 0),
            'sent' => (int) ($stats->sent ?? 0),
            'failed' => (int) ($stats->failed ?? 0),
            'total' => (int) ($stats->total ?? 0),
        ]);
    }

    public function pull(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('SMS Operator')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $batchSize = min($request->input('batch_size', 5), 20);
        $messages = SmsSendLog::where('assigned_to', $user->id)
            ->where('status', 'queued')
            ->whereHas('campaign', function ($q) {
                $q->where('campaign_status', 'sending');
            })
            ->orderBy('id')
            ->limit($batchSize)
            ->get();
        if ($messages->isEmpty()) {
            $messages = DB::transaction(function () use ($user, $batchSize) {
                $logs = SmsSendLog::whereNull('assigned_to')
                    ->where('status', 'queued')
                    ->whereHas('campaign', function ($q) use ($user) {
                        $q->where('company_id', $user->company_id)
                            ->where('campaign_status', 'sending')
                            ->where('sending_method', 'sim_based');
                    })
                    ->orderBy('id')
                    ->limit($batchSize)
                    ->lockForUpdate()
                    ->get();
                if ($logs->isNotEmpty()) {
                    SmsSendLog::whereIn('id', $logs->pluck('id'))
                        ->update(['assigned_to' => $user->id]);
                }
                return $logs;
            });
        }
        $result = $messages->map(function ($log) {
            return [
                'log_id' => $log->id,
                'phone_number' => $log->phone_number,
                'message' => $log->message_body,
                'campaign_id' => $log->campaign_id,
            ];
        });
        return response()->json(['success' => true, 'messages' => $result]);
    }

    public function report(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('SMS Operator')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $request->validate([
            'results' => 'required|array',
            'results.*.log_id' => 'required|integer',
            'results.*.status' => 'required|in:sent,failed',
            'results.*.error_message' => 'nullable|string|max:500',
        ]);
        $sentCount = 0;
        $failedCount = 0;
        foreach ($request->results as $result) {
            $log = SmsSendLog::where('id', $result['log_id'])
                ->where('assigned_to', $user->id)
                ->first();
            if (!$log) continue;
            $log->update([
                'status' => $result['status'],
                'sent_at' => $result['status'] === 'sent' ? now() : null,
                'error_message' => $result['error_message'] ?? null,
            ]);
            if ($result['status'] === 'sent') $sentCount++;
            else $failedCount++;
        }
        $campaignIds = collect($request->results)
            ->pluck('log_id')
            ->map(fn($id) => SmsSendLog::find($id)?->campaign_id)
            ->filter()->unique();
        foreach ($campaignIds as $campaignId) {
            $campaign = SmsCampaign::find($campaignId);
            if (!$campaign) continue;
            $stats = SmsSendLog::where('campaign_id', $campaignId)
                ->selectRaw("COUNT(CASE WHEN status='sent' THEN 1 END) as sent, COUNT(CASE WHEN status='failed' THEN 1 END) as failed, COUNT(CASE WHEN status='queued' THEN 1 END) as queued")
                ->first();
            $campaign->update(['total_sent' => $stats->sent, 'total_failed' => $stats->failed]);
            if ($stats->queued == 0 && in_array($campaign->schedule_type, ['immediate', 'scheduled'])) {
                $campaign->update(['campaign_status' => 'completed']);
            }
        }
        return response()->json(['success' => true, 'sent' => $sentCount, 'failed' => $failedCount]);
    }
}
