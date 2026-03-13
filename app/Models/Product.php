<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'default_price',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'default_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function priceTiers()
    {
        return $this->hasMany(ProductPriceTier::class)->orderBy('min_qty');
    }

    /**
     * Get the price for a given quantity based on tiers.
     * Falls back to default_price if no tier matches.
     */
    public function getPriceForQuantity(int $quantity): float
    {
        $tier = $this->priceTiers()
            ->where('min_qty', '<=', $quantity)
            ->where(function ($q) use ($quantity) {
                $q->where('max_qty', '>=', $quantity)
                  ->orWhereNull('max_qty');
            })
            ->first();

        return $tier ? (float) $tier->price : (float) $this->default_price;
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(ProductActivityLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
