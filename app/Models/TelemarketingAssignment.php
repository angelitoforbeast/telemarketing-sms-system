<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelemarketingAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'assigned_to_user_id',
        'status_filter',
        'date_range_filter',
        'limit_filter',
        'total_shipments',
        'assigned_shipments',
        'disposition_date',
        'shipment_ids',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedToUser()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    protected $casts = [
        'shipment_ids' => 'array',
    ];
}
