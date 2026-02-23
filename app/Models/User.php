<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'is_active',
        'is_telemarketing_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'is_telemarketing_active' => 'boolean',
    ];

    // ── Relationships ──

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedShipments()
    {
        return $this->hasMany(Shipment::class, 'assigned_to_user_id');
    }

    public function telemarketingLogs()
    {
        return $this->hasMany(TelemarketingLog::class);
    }

    public function importJobs()
    {
        return $this->hasMany(ImportJob::class);
    }

    public function assignedStatuses()
    {
        return $this->belongsToMany(ShipmentStatus::class, 'telemarketer_status_assignments', 'user_id', 'shipment_status_id')
                     ->withTimestamps();
    }

    /**
     * Get the status IDs this telemarketer is allowed to handle.
     * Returns null if no restrictions (all statuses allowed).
     */
    public function getAllowedStatusIds(): ?array
    {
        $ids = $this->assignedStatuses()->pluck('shipment_statuses.id')->toArray();
        return empty($ids) ? null : $ids;
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTelemarketingActive($query)
    {
        return $query->where('is_telemarketing_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // ── Helpers ──

    public function isPlatformUser(): bool
    {
        return is_null($this->company_id);
    }

    public function isCompanyUser(): bool
    {
        return !is_null($this->company_id);
    }

    public function belongsToCompany(int $companyId): bool
    {
        return $this->company_id === $companyId;
    }
}
