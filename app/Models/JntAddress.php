<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JntAddress extends Model
{
    public $timestamps = false;

    protected $table = 'jnt_addresses';

    protected $fillable = [
        'province',
        'city',
        'barangay',
    ];

    // ── Scopes ──

    public static function getProvinces()
    {
        return static::select('province')
            ->distinct()
            ->orderBy('province')
            ->pluck('province');
    }

    public static function getCities(string $province)
    {
        return static::where('province', $province)
            ->select('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');
    }

    public static function getBarangays(string $province, string $city)
    {
        return static::where('province', $province)
            ->where('city', $city)
            ->select('barangay')
            ->distinct()
            ->orderBy('barangay')
            ->pluck('barangay');
    }
}
