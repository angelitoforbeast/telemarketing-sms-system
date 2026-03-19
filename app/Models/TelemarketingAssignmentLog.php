<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemarketingAssignmentLog extends Model
{
    protected $fillable = [
        'company_id',
        'assigned_by_user_id',
        'assigned_to_user_id',
        'shipment_count',
        'shipment_ids',
        'status_filters',
        'assigned_at',
    ];

    protected $casts = [
        'shipment_ids' => 'array',
        'assigned_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
