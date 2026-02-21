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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
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

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
