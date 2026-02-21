<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'is_terminal',
        'is_sms_triggerable',
        'sort_order',
    ];

    protected $casts = [
        'is_terminal' => 'boolean',
        'is_sms_triggerable' => 'boolean',
    ];

    // ── Relationships ──

    public function shipments()
    {
        return $this->hasMany(Shipment::class, 'normalized_status_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(ShipmentStatusLog::class, 'status_id');
    }

    public function smsCampaigns()
    {
        return $this->hasMany(SmsCampaign::class, 'trigger_status_id');
    }

    // ── Scopes ──

    public function scopeTriggerable($query)
    {
        return $query->where('is_sms_triggerable', true);
    }
}
