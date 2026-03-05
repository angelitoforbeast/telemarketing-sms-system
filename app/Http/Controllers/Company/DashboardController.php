<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\SmsSendLog;
use App\Models\TelemarketingLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Redirect SMS Operators to their blast dashboard
        if ($user->hasRole("SMS Operator")) {
            return redirect()->route("sms.blast.dashboard");
        }
        $companyId = $user->company_id;
        $timezone = 'Asia/Manila';

        // Date range: default last 30 days including today (Manila time)
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'), $timezone)->endOfDay()
            : Carbon::now($timezone)->endOfDay();
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'), $timezone)->startOfDay()
            : Carbon::now($timezone)->subDays(29)->startOfDay();

        // Base query with date range (using created_at for shipments)
        $shipmentQuery = Shipment::forCompany($companyId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Shipment counts by status (within date range)
        $statusCounts = (clone $shipmentQuery)
            ->select('normalized_status_id', DB::raw('count(*) as count'))
            ->groupBy('normalized_status_id')
            ->with('status')
            ->get()
            ->mapWithKeys(fn ($item) => [
                ($item->status?->name ?? 'Unknown') => $item->count
            ]);

        // Shipment counts by courier (within date range)
        $courierCounts = (clone $shipmentQuery)
            ->select('courier', DB::raw('count(*) as count'))
            ->groupBy('courier')
            ->pluck('count', 'courier');

        // Imports within date range
        $periodImports = ImportJob::forCompany($companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // SMS sent within date range
        $periodSmsSent = SmsSendLog::forCompany($companyId)
            ->whereBetween('send_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('status', 'sent')
            ->count();

        // Calls within date range
        $periodCalls = TelemarketingLog::whereHas('shipment', fn ($q) => $q->forCompany($companyId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $totalShipments = (clone $shipmentQuery)->count();
        $unassignedCount = (clone $shipmentQuery)->unassigned()->count();

        // Recent imports
        $recentImports = ImportJob::forCompany($companyId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'statusCounts',
            'courierCounts',
            'periodImports',
            'periodSmsSent',
            'periodCalls',
            'totalShipments',
            'unassignedCount',
            'recentImports',
            'startDate',
            'endDate'
        ));
    }
}
