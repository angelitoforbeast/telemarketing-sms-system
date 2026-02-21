<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelemarketingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'user_id',
        'disposition_id',
        'notes',
        'attempt_no',
        'callback_at',
        'phone_called',
    ];

    protected $casts = [
        'callback_at' => 'datetime',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function disposition()
    {
        return $this->belongsTo(TelemarketingDisposition::class, 'disposition_id');
    }
}
