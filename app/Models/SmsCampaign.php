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
        'schedule_type',
        'scheduled_at',
        'recurring_time',
        'recurring_interval_hours',
        'cron_expression',
        'sending_method',
        'assigned_operator_id',
        'throttle_delay_seconds',
        'recipient_filter_type',
        'recipient_filters',
        'campaign_status',
        'total_recipients',
        'total_sent',
        'total_failed',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'send_daily_repeat' => 'boolean',
        'province_filter' => 'array',
        'city_filter' => 'array',
        'recipient_filters' => 'array',
        'scheduled_at' => 'datetime',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'throttle_delay_seconds' => 'integer',
        'recurring_interval_hours' => 'integer',
        'total_recipients' => 'integer',
        'total_sent' => 'integer',
        'total_failed' => 'integer',
    ];

    const SCHEDULE_IMMEDIATE = 'immediate';
    const SCHEDULE_SCHEDULED = 'scheduled';
    const SCHEDULE_RECURRING_DAILY = 'recurring_daily';
    const SCHEDULE_RECURRING_HOURLY = 'recurring_hourly';
    const SCHEDULE_CUSTOM_CRON = 'custom_cron';

    const STATUS_DRAFT = 'draft';
    const STATUS_QUEUED = 'queued';
    const STATUS_SENDING = 'sending';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    const METHOD_SIM = 'sim_based';
    const METHOD_GATEWAY = 'gateway';

    const MERGE_TAGS = [
        'consignee_name' => 'Recipient Name',
        'waybill_no' => 'Waybill/Tracking Number',
        'cod_amount' => 'COD Amount',
        'status' => 'Shipment Status',
        'courier' => 'Courier Name',
        'item_description' => 'Item Description',
        'consignee_address' => 'Full Address',
        'consignee_city' => 'City',
        'consignee_province' => 'Province',
        'consignee_barangay' => 'Barangay',
        'consignee_phone' => 'Phone Number',
        'sender_name' => 'Sender Name',
        'sender_phone' => 'Sender Phone',
        'item_quantity' => 'Item Quantity',
        'shipping_cost' => 'Shipping Cost',
        'company_name' => 'Company Name',
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

    public function assignedOperator()
    {
        return $this->belongsTo(User::class, 'assigned_operator_id');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, ?int $companyId)
    {
        if ($companyId) {
            return $query->where('company_id', $companyId);
        }
        return $query;
    }

    public function scopeReadyToSend($query)
    {
        return $query->where('is_active', true)
            ->where('campaign_status', self::STATUS_QUEUED)
            ->where(function ($q) {
                $q->where('schedule_type', self::SCHEDULE_IMMEDIATE)
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('next_run_at')
                         ->where('next_run_at', '<=', now());
                  });
            });
    }

    // ── Helpers ──

    public function renderMessage(Shipment $shipment): string
    {
        $values = [
            'consignee_name' => $shipment->consignee_name ?? '',
            'customer_name' => $shipment->consignee_name ?? '',
            'waybill_no' => $shipment->waybill_no ?? '',
            'courier' => strtoupper($shipment->courier ?? ''),
            'status' => $shipment->status?->name ?? 'Unknown',
            'cod_amount' => number_format($shipment->cod_amount ?? 0, 2),
            'item_description' => $shipment->item_description ?? '',
            'consignee_address' => $shipment->consignee_address ?? '',
            'consignee_city' => $shipment->consignee_city ?? '',
            'consignee_province' => $shipment->consignee_province ?? '',
            'consignee_barangay' => $shipment->consignee_barangay ?? '',
            'consignee_phone' => $shipment->consignee_phone_1 ?? '',
            'sender_name' => $shipment->sender_name ?? '',
            'sender_phone' => $shipment->sender_phone ?? '',
            'item_quantity' => $shipment->item_quantity ?? '',
            'shipping_cost' => number_format($shipment->shipping_cost ?? 0, 2),
            'company_name' => $shipment->company?->name ?? '',
        ];

        $result = $this->sms_template;
        foreach ($values as $key => $value) {
            $result = str_replace(['{' . $key . '}', '{{' . $key . '}}'], $value, $result);
        }
        return $result;
    }

    public function buildRecipientQuery()
    {
        $filters = $this->recipient_filters ?? [];
        $query = Shipment::forCompany($this->company_id)
            ->whereNotNull('consignee_phone_1')
            ->where('consignee_phone_1', '!=', '');

        if (!empty($filters['statuses'])) {
            $query->whereIn('normalized_status_id', $filters['statuses']);
        } elseif ($this->trigger_status_id) {
            $query->where('normalized_status_id', $this->trigger_status_id);
        }

        if (!empty($filters['provinces'])) {
            $query->whereIn('consignee_province', $filters['provinces']);
        } elseif (!empty($this->province_filter)) {
            $query->whereIn('consignee_province', $this->province_filter);
        }

        if (!empty($filters['cities'])) {
            $query->whereIn('consignee_city', $filters['cities']);
        } elseif (!empty($this->city_filter)) {
            $query->whereIn('consignee_city', $this->city_filter);
        }

        if (!empty($filters['date_range_days'])) {
            $query->where('created_at', '>=', now()->subDays($filters['date_range_days']));
        }

        if (!empty($filters['exclude_already_sent'])) {
            $query->whereDoesntHave('smsSendLogs', function ($q) {
                $q->where('campaign_id', $this->id)
                  ->where('status', 'sent');
            });
        }

        return $query;
    }

    public function queueMessages(): int
    {
        $shipments = $this->buildRecipientQuery()->get();
        $count = 0;

        foreach ($shipments as $shipment) {
            $exists = SmsSendLog::where('campaign_id', $this->id)
                ->where('shipment_id', $shipment->id)
                ->where('status', 'queued')
                ->exists();

            if (!$exists) {
                SmsSendLog::create([
                    'company_id' => $this->company_id,
                    'shipment_id' => $shipment->id,
                    'campaign_id' => $this->id,
                    'phone_number' => $shipment->consignee_phone_1,
                    'message_body' => $this->renderMessage($shipment),
                    'send_date' => now()->toDateString(),
                    'status' => 'queued',
                ]);
                $count++;
            }
        }

        $this->update([
            'total_recipients' => $count,
            'campaign_status' => self::STATUS_SENDING,
            'last_run_at' => now(),
        ]);

        return $count;
    }

    public function getProgressPercentage(): float
    {
        if ($this->total_recipients <= 0) return 0;
        return round(($this->total_sent + $this->total_failed) / $this->total_recipients * 100, 1);
    }

    public function getStatusBadgeColor(): string
    {
        return match ($this->campaign_status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_QUEUED => 'blue',
            self::STATUS_SENDING => 'yellow',
            self::STATUS_PAUSED => 'orange',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_CANCELLED => 'red',
            default => 'gray',
        };
    }
}
