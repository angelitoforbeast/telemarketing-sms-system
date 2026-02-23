<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusTransitionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'from_status_id',
        'to_status_id',
        'action',
        'reset_attempts',
        'cooldown_days',
        'is_active',
        'priority',
        'description',
    ];

    protected $casts = [
        'reset_attempts' => 'boolean',
        'is_active' => 'boolean',
        'cooldown_days' => 'integer',
        'priority' => 'integer',
    ];

    // ── Relationships ──

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function fromStatus()
    {
        return $this->belongsTo(ShipmentStatus::class, 'from_status_id');
    }

    public function toStatus()
    {
        return $this->belongsTo(ShipmentStatus::class, 'to_status_id');
    }

    // ── Scopes ──

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ──

    /**
     * Find the matching transition rule for a given from→to status change.
     * Priority: exact match > wildcard from > wildcard to > wildcard both
     */
    public static function findMatchingRule(int $companyId, ?int $fromStatusId, ?int $toStatusId): ?self
    {
        // Try exact match first
        $rule = static::forCompany($companyId)
            ->active()
            ->where('from_status_id', $fromStatusId)
            ->where('to_status_id', $toStatusId)
            ->orderBy('priority', 'desc')
            ->first();

        if ($rule) return $rule;

        // Try wildcard from (any → specific to)
        $rule = static::forCompany($companyId)
            ->active()
            ->whereNull('from_status_id')
            ->where('to_status_id', $toStatusId)
            ->orderBy('priority', 'desc')
            ->first();

        if ($rule) return $rule;

        // Try wildcard to (specific from → any)
        $rule = static::forCompany($companyId)
            ->active()
            ->where('from_status_id', $fromStatusId)
            ->whereNull('to_status_id')
            ->orderBy('priority', 'desc')
            ->first();

        if ($rule) return $rule;

        // Try full wildcard (any → any) — catch-all
        return static::forCompany($companyId)
            ->active()
            ->whereNull('from_status_id')
            ->whereNull('to_status_id')
            ->orderBy('priority', 'desc')
            ->first();
    }

    /**
     * Get a human-readable label for the action.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'auto_reassign' => 'Auto-Reassign',
            'auto_unassign' => 'Auto-Unassign (No Call)',
            'mark_completed' => 'Mark Completed',
            'no_action' => 'No Action',
            default => ucfirst($this->action),
        };
    }
}
