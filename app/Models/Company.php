<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'address',
        'contact_email',
        'contact_phone',
    ];

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (empty($company->slug)) {
                $company->slug = Str::slug($company->name) . '-' . Str::random(5);
            }
        });
    }

    // ── Relationships ──

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function importJobs()
    {
        return $this->hasMany(ImportJob::class);
    }

    public function smsCampaigns()
    {
        return $this->hasMany(SmsCampaign::class);
    }

    public function telemarketingDispositions()
    {
        return $this->hasMany(TelemarketingDisposition::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ── Helpers ──

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
