<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsSendLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'shipment_id',
        'campaign_id',
        'phone_number',
        'message_body',
        'send_date',
        'status',
        'provider_response',
        'provider_message_id',
        'sent_at',
        'retry_count',
        'error_message',
    ];

    protected $casts = [
        'send_date' => 'date',
        'sent_at' => 'datetime',
    ];

    // ── Relationships ──

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function campaign()
    {
        return $this->belongsTo(SmsCampaign::class, 'campaign_id');
    }

    // ── Scopes ──

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeToday($query)
    {
        return $query->where('send_date', now()->toDateString());
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // ── Helpers ──

    public function markSent(?string $providerResponse = null, ?string $messageId = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'provider_response' => $providerResponse,
            'provider_message_id' => $messageId,
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }
}
