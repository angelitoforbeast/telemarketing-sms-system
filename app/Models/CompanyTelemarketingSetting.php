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
    ];
    protected $casts = [
        'auto_call_enabled' => 'boolean',
        'auto_call_delay' => 'integer',
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
            ['auto_call_enabled' => false, 'auto_call_delay' => 5, 'queue_mode' => self::QUEUE_PRE_ASSIGNED]
        );
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
