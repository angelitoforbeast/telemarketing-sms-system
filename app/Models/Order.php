<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'shipment_id',
        'telemarketing_log_id',
        'order_type_id',
        'created_by',
        'customer_phone',
        'customer_name',
        'province',
        'city',
        'barangay',
        'address_details',
        'total_amount',
        'process_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'process_date' => 'date',
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

    public function telemarketingLog()
    {
        return $this->belongsTo(TelemarketingLog::class);
    }

    public function orderType()
    {
        return $this->belongsTo(OrderType::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    // ── Scopes ──

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForCustomerPhone($query, string $phone)
    {
        return $query->where('customer_phone', $phone);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('process_date', today());
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    // ── Helpers ──

    public function recalculateTotal(): void
    {
        $this->update([
            'total_amount' => $this->items()->sum('subtotal'),
        ]);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_details,
            $this->barangay,
            $this->city,
            $this->province,
        ]);
        return implode(', ', $parts);
    }

    /**
     * Get all orders for the same customer phone number.
     */
    public function customerOrders()
    {
        return static::where('customer_phone', $this->customer_phone)
            ->where('company_id', $this->company_id)
            ->orderByDesc('created_at');
    }
}
