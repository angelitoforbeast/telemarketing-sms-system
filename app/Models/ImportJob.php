<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'courier',
        'original_filename',
        'storage_path',
        'status',
        'total_rows',
        'processed_rows',
        'new_shipments_count',
        'updated_shipments_count',
        'skipped_count',
        'failed_rows_count',
        'error_summary',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'error_summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ── Relationships ──

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rawJntRows()
    {
        return $this->hasMany(RawJntRow::class);
    }

    public function rawFlashRows()
    {
        return $this->hasMany(RawFlashRow::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(ShipmentStatusLog::class);
    }

    // ── Scopes ──

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // ── Helpers ──

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing', 'started_at' => now()]);
    }

    public function markAsProcessing(): void
    {
        $this->markProcessing();
    }

    public function markCompleted(): void
    {
        $this->update(['status' => 'completed', 'completed_at' => now()]);
    }

    public function markAsCompleted(array $stats = []): void
    {
        $this->update(array_merge([
            'status' => 'completed',
            'completed_at' => now(),
        ], $stats));
    }

    public function markFailed(array $errors = []): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_summary' => $errors,
        ]);
    }

    public function incrementNewShipments(): void
    {
        $this->increment('new_shipments_count');
    }

    public function incrementUpdatedShipments(): void
    {
        $this->increment('updated_shipments_count');
    }

    public function incrementProcessedRows(): void
    {
        $this->increment('processed_rows');
    }

    public function incrementFailedRows(): void
    {
        $this->increment('failed_rows_count');
    }
}
