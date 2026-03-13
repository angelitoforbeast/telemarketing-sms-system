<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelemarketingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'user_id',
        'disposition_id',
        'status',
        'notes',
        'attempt_no',
        'callback_at',
        'phone_called',
        'call_duration_seconds',
        'call_started_at',
        'recording_path',
        'recording_url',
        'transcription',
        'ai_summary',
        'ai_analyzed_at',
        'ai_disposition_id',
        'ai_sentiment',
        'ai_agent_score',
        'ai_customer_intent',
        'ai_key_issues',
        'ai_action_items',
    ];

    protected $casts = [
        'callback_at' => 'datetime',
        'call_started_at' => 'datetime',
        'ai_analyzed_at' => 'datetime',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function disposition()
    {
        return $this->belongsTo(TelemarketingDisposition::class, 'disposition_id');
    }
    public function aiDisposition()
    {
        return $this->belongsTo(TelemarketingDisposition::class, 'ai_disposition_id');
    }

    /**
     * Check if this log has a recording.
     */
    public function hasRecording(): bool
    {
        return !empty($this->recording_path) || !empty($this->recording_url);
    }

    /**
     * Get the URL for playing back the recording.
     */
    public function getRecordingPlaybackUrl(): ?string
    {
        if (!empty($this->recording_url)) {
            return $this->recording_url;
        }

        if (!empty($this->recording_path)) {
            return route('telemarketing.play-recording', $this->id);
        }

        return null;
    }
}
