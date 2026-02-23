<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemarketerStatusAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'shipment_status_id',
        'company_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shipmentStatus()
    {
        return $this->belongsTo(ShipmentStatus::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
