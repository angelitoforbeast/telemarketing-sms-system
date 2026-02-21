<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'sms_template',
        'trigger_status_id',
        'is_active',
        'send_daily_repeat',
        'daily_send_limit',
        'province_filter',
        'city_filter',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'send_daily_repeat' => 'boolean',
        'province_filter' => 'array',
        'city_filter' => 'array',
    ];

    // ── Relationships ──

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function triggerStatus()
    {
        return $this->belongsTo(ShipmentStatus::class, 'trigger_status_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function sendLogs()
    {
        return $this->hasMany(SmsSendLog::class, 'campaign_id');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // ── Helpers ──

    public function renderMessage(Shipment $shipment): string
    {
        $values = [
            'consignee_name' => $shipment->consignee_name,
            'customer_name' => $shipment->consignee_name,
            'waybill_no' => $shipment->waybill_no,
            'courier' => strtoupper($shipment->courier),
            'status' => $shipment->status?->name ?? 'Unknown',
            'cod_amount' => number_format($shipment->cod_amount, 2),
            'item_description' => $shipment->item_description ?? '',
        ];

        $result = $this->sms_template;
        foreach ($values as $key => $value) {
            $result = str_replace(['{' . $key . '}', '{{' . $key . '}}'], $value, $result);
        }

        return $result;
    }
}
