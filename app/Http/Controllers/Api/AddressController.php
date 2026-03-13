<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JntAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Get all provinces.
     */
    public function provinces(): JsonResponse
    {
        $provinces = JntAddress::getProvinces();
        return response()->json($provinces);
    }

    /**
     * Get cities for a given province.
     */
    public function cities(Request $request): JsonResponse
    {
        $request->validate(['province' => 'required|string']);
        $cities = JntAddress::getCities($request->province);
        return response()->json($cities);
    }

    /**
     * Get barangays for a given province + city.
     */
    public function barangays(Request $request): JsonResponse
    {
        $request->validate([
            'province' => 'required|string',
            'city' => 'required|string',
        ]);
        $barangays = JntAddress::getBarangays($request->province, $request->city);
        return response()->json($barangays);
    }

    /**
     * Search addresses (for autocomplete/search functionality).
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2']);
        $query = $request->q;

        $results = JntAddress::where('barangay', 'LIKE', "%{$query}%")
            ->orWhere('city', 'LIKE', "%{$query}%")
            ->orWhere('province', 'LIKE', "%{$query}%")
            ->select('province', 'city', 'barangay')
            ->limit(50)
            ->get()
            ->map(function ($addr) {
                return [
                    'province' => $addr->province,
                    'city' => $addr->city,
                    'barangay' => $addr->barangay,
                    'full' => "{$addr->barangay}, {$addr->city}, {$addr->province}",
                ];
            });

        return response()->json($results);
    }
}
