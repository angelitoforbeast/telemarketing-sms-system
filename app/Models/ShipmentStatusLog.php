<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'status_id',
        'source_status_text',
        'import_job_id',
        'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function status()
    {
        return $this->belongsTo(ShipmentStatus::class, 'status_id');
    }

    public function importJob()
    {
        return $this->belongsTo(ImportJob::class);
    }
}
