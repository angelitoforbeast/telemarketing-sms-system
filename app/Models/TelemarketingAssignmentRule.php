<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelemarketingAssignmentRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'rule_type',
        'status_id',
        'days_threshold',
        'assignment_method',
        'is_active',
        'priority',
        'max_attempts',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function status()
    {
        return $this->belongsTo(ShipmentStatus::class, 'status_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
