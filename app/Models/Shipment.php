<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'courier',
        'waybill_no',
        'normalized_status_id',
        'last_status_update_at',
        'consignee_name',
        'consignee_phone_1',
        'consignee_phone_2',
        'consignee_address',
        'consignee_province',
        'consignee_city',
        'consignee_barangay',
        'sender_name',
        'sender_phone',
        'cod_amount',
        'item_description',
        'item_quantity',
        'item_weight',
        'shipping_cost',
        'valuation_fee',
        'payment_method',
        'rts_reason',
        'remarks',
        'submission_time',
        'signing_time',
        'assigned_to_user_id',
        'assigned_at',
        'telemarketing_attempt_count',
        'last_contacted_at',
        'is_do_not_contact',
        'source_status_text',
        'telemarketing_status',
        'last_disposition_id',
        'callback_scheduled_at',
        'telemarketing_cooldown_until',
        'previous_status_id',
    ];

    protected $casts = [
        'cod_amount' => 'decimal:2',
        'item_weight' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'valuation_fee' => 'decimal:2',
        'last_status_update_at' => 'datetime',
        'submission_time' => 'datetime',
        'signing_time' => 'datetime',
        'assigned_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'is_do_not_contact' => 'boolean',
        'callback_scheduled_at' => 'datetime',
        'telemarketing_cooldown_until' => 'datetime',
    ];

    // ── Relationships ──

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function status()
    {
        return $this->belongsTo(ShipmentStatus::class, 'normalized_status_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(ShipmentStatusLog::class);
    }

    public function telemarketingLogs()
    {
        return $this->hasMany(TelemarketingLog::class);
    }

    public function smsSendLogs()
    {
        return $this->hasMany(SmsSendLog::class);
    }

    // ── Scopes ──

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeCourier(Builder $query, string $courier): Builder
    {
        return $query->where('courier', $courier);
    }

    public function scopeWithStatus(Builder $query, string $statusCode): Builder
    {
        return $query->whereHas('status', fn ($q) => $q->where('code', $statusCode));
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to_user_id', $userId);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to_user_id');
    }

    public function scopeContactable(Builder $query): Builder
    {
        return $query->where('is_do_not_contact', false)
                     ->where('telemarketing_status', '!=', 'do_not_call')
                     ->whereNotNull('consignee_phone_1');
    }

    public function scopeTelemarketable(Builder $query): Builder
    {
        return $query->contactable()
                     ->whereIn('telemarketing_status', ['pending', 'in_progress'])
                     ->where(function ($q) {
                         $q->whereNull('telemarketing_cooldown_until')
                           ->orWhere('telemarketing_cooldown_until', '<=', now());
                     });
    }

    public function scopeCallbackDue(Builder $query): Builder
    {
        return $query->whereNotNull('callback_scheduled_at')
                     ->where('callback_scheduled_at', '<=', now());
    }

    public function scopeNeverContacted(Builder $query): Builder
    {
        return $query->where('telemarketing_attempt_count', 0);
    }

    public function lastDisposition()
    {
        return $this->belongsTo(TelemarketingDisposition::class, 'last_disposition_id');
    }

    public function previousStatus()
    {
        return $this->belongsTo(ShipmentStatus::class, 'previous_status_id');
    }

    public function scopeOnCooldown(Builder $query): Builder
    {
        return $query->whereNotNull('telemarketing_cooldown_until')
                     ->where('telemarketing_cooldown_until', '>', now());
    }

    /**
     * Check if this shipment's last disposition allows re-calling on status change.
     */
    public function isRecallableOnStatusChange(): bool
    {
        // No disposition logged yet — always callable
        if (!$this->last_disposition_id) return true;

        $disposition = $this->lastDisposition;
        if (!$disposition) return true;

        // If disposition is final AND not recallable, don't re-assign
        if ($disposition->is_final && !$disposition->is_recallable_on_status_change) {
            return false;
        }

        // If marked do-not-call, don't re-assign
        if ($disposition->marks_do_not_call) {
            return false;
        }

        return true;
    }
}
