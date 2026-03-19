<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CompanyTelemarketingSetting extends Model
{
    protected $table = 'company_telemarketing_settings';
    const QUEUE_PRE_ASSIGNED = 'pre_assigned';
    const QUEUE_SHARED = 'shared_queue';
    const QUEUE_HYBRID = 'hybrid';
    protected $fillable = [
        'company_id',
        'auto_call_enabled',
        'auto_call_delay',
        'queue_mode',
        'recording_mode',
        'require_recording',
        'recording_upload_timeout',
        'recording_exempt_dispositions',
        'call_log_columns',
        'pending_callbacks_view',
    ];
    protected $casts = [
        'auto_call_enabled' => 'boolean',
        'auto_call_delay' => 'integer',
        'require_recording' => 'boolean',
        'recording_upload_timeout' => 'integer',
        'recording_exempt_dispositions' => 'array',
        'call_log_columns' => 'array',
    ];
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    /**
     * Get settings for a company, creating defaults if not exists.
     */
    public static function getOrCreate(int $companyId): self
    {
        return static::firstOrCreate(
            ['company_id' => $companyId],
            [
                'auto_call_enabled' => false,
                'auto_call_delay' => 5,
                'queue_mode' => self::QUEUE_PRE_ASSIGNED,
                'recording_mode' => 'both',
                'require_recording' => false,
                'recording_upload_timeout' => 30,
                'recording_exempt_dispositions' => null,
                'pending_callbacks_view' => 'callbacks_only',
            ]
        );
    }
    /**
     * Check if a disposition is exempt from recording requirement.
     */
    public function isDispositionExempt(int $dispositionId): bool
    {
        $exempt = $this->recording_exempt_dispositions ?? [];
        return in_array($dispositionId, $exempt);
    }
    /**
     * Get default exempt disposition IDs (No Answer, Busy, Wrong Number, Not in Service, Voicemail).
     */
    public static function getDefaultExemptDispositionIds(): array
    {
        return [6, 7, 8, 9, 10]; // No Answer, Busy, Wrong Number, Not in Service, Voicemail
    }
    public function isPreAssigned(): bool
    {
        return $this->queue_mode === self::QUEUE_PRE_ASSIGNED;
    }
    public function isSharedQueue(): bool
    {
        return $this->queue_mode === self::QUEUE_SHARED;
    }
    public function isHybrid(): bool
    {
        return $this->queue_mode === self::QUEUE_HYBRID;
    }
}
