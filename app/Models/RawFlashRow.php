<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawFlashRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_job_id',
        'is_processed',
        'data',
        'error_message',
    ];

    protected $casts = [
        'is_processed' => 'boolean',
        'data' => 'array',
    ];

    public function importJob()
    {
        return $this->belongsTo(ImportJob::class);
    }
}
