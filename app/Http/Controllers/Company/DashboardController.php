<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\SmsSendLog;
use App\Models\TelemarketingLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        // Shipment counts by status
        $statusCounts = Shipment::forCompany($companyId)
            ->select('normalized_status_id', DB::raw('count(*) as count'))
            ->groupBy('normalized_status_id')
            ->with('status')
            ->get()
            ->mapWithKeys(fn ($item) => [
                ($item->status?->name ?? 'Unknown') => $item->count
            ]);

        // Shipment counts by courier
        $courierCounts = Shipment::forCompany($companyId)
            ->select('courier', DB::raw('count(*) as count'))
            ->groupBy('courier')
            ->pluck('count', 'courier');

        // Today's stats
        $todayImports = ImportJob::forCompany($companyId)
            ->whereDate('created_at', today())
            ->count();

        $todaySmsSent = SmsSendLog::forCompany($companyId)
            ->where('send_date', today())
            ->where('status', 'sent')
            ->count();

        $todayCalls = TelemarketingLog::whereHas('shipment', fn ($q) => $q->forCompany($companyId))
            ->whereDate('created_at', today())
            ->count();

        $totalShipments = Shipment::forCompany($companyId)->count();
        $unassignedCount = Shipment::forCompany($companyId)->unassigned()->count();

        // Recent imports
        $recentImports = ImportJob::forCompany($companyId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'statusCounts',
            'courierCounts',
            'todayImports',
            'todaySmsSent',
            'todayCalls',
            'totalShipments',
            'unassignedCount',
            'recentImports'
        ));
    }
}
