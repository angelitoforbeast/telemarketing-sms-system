<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'product_id',
        'product_name',
        'user_id',
        'user_name',
        'action',
        'details',
        'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a product activity
     */
    public static function log($product, $user, string $action, ?array $details = null): self
    {
        return self::create([
            'company_id' => $product->company_id ?? $user->company_id,
            'product_id' => $product->id ?? null,
            'product_name' => $product->name ?? 'Unknown',
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => $action,
            'details' => $details,
            'created_at' => now(),
        ]);
    }

    /**
     * Get human-readable action label
     */
    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            'created' => 'Created product',
            'updated' => 'Updated product',
            'deleted' => 'Deleted product',
            'tiers_updated' => 'Updated price tiers',
            'activated' => 'Activated product',
            'deactivated' => 'Deactivated product',
            default => ucfirst($this->action),
        };
    }

    /**
     * Get action color for UI
     */
    public function getActionColorAttribute(): string
    {
        return match($this->action) {
            'created' => 'green',
            'updated' => 'blue',
            'deleted' => 'red',
            'tiers_updated' => 'purple',
            'activated' => 'emerald',
            'deactivated' => 'orange',
            default => 'gray',
        };
    }
}
