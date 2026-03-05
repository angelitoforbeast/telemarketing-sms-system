<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelemarketingDisposition extends Model
{
    use HasFactory;

    public const CATEGORY_LABELS = [
        'answered'    => 'Answered',
        'not_reached' => 'Not Reached',
        'invalid'     => 'Invalid Number',
        'other'       => 'Other',
    ];

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'category',
        'is_final',
        'is_system',
        'sort_order',
        'color',
        'description',
        'requires_callback',
        'marks_do_not_call',
        'is_recallable_on_status_change',
    ];

    protected $casts = [
        'is_final' => 'boolean',
        'is_system' => 'boolean',
        'requires_callback' => 'boolean',
        'marks_do_not_call' => 'boolean',
        'is_recallable_on_status_change' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function telemarketingLogs()
    {
        return $this->hasMany(TelemarketingLog::class, 'disposition_id');
    }

    public function scopeForCompany($query, ?int $companyId)
    {
        return $query->where(function ($q) use ($companyId) {
            $q->whereNull('company_id') // system-wide dispositions
              ->orWhere('company_id', $companyId);
        });
    }
}
