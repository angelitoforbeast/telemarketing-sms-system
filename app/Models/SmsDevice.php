<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'device_name',
        'device_token',
        'sim_number',
        'carrier',
        'daily_limit',
        'messages_sent_today',
        'throttle_delay_seconds',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'daily_limit' => 'integer',
        'messages_sent_today' => 'integer',
        'throttle_delay_seconds' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sendLogs()
    {
        return $this->hasMany(SmsSendLog::class, 'device_id');
    }

    public function scopeForCompany($query, $companyId)
    {
        if ($companyId) {
            return $query->where('company_id', $companyId);
        }
        return $query;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnline($query, $minutes = 5)
    {
        return $query->where('last_seen_at', '>=', now()->subMinutes($minutes));
    }

    public function hasCapacity(): bool
    {
        return $this->messages_sent_today < $this->daily_limit;
    }

    public function remainingCapacity(): int
    {
        return max(0, $this->daily_limit - $this->messages_sent_today);
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
