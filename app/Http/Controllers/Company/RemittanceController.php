<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RemittanceController extends Controller
{
    /**
     * Remittance report page.
     *
     * Computation:
     *   Net Remittance = COD Sum (delivered, by signing_time)
     *                  - SF (by submission_time)
     *                  - COD Fee (COD Sum × COD Fee Rate)
     *                  - COD Fee VAT (COD Fee × VAT Rate)
     *
     * Row listing:
     *   Based on submission_time.
     *   Range: (lowest selected date − 30 days) to (highest selected date).
     *   Default: This Month.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $company = $user->company;
        $companyId = $user->company_id;
        $timezone = 'Asia/Manila';

        // ── Date Range (for the "period" the user wants to view) ──
        // Default: This Month (1st of current month to today)
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $periodStart = Carbon::parse($request->input('start_date'), $timezone)->startOfDay();
            $periodEnd   = Carbon::parse($request->input('end_date'), $timezone)->endOfDay();
        } elseif ($request->input('preset') === 'last_month') {
            $periodStart = Carbon::now($timezone)->subMonth()->startOfMonth()->startOfDay();
            $periodEnd   = Carbon::now($timezone)->subMonth()->endOfMonth()->endOfDay();
        } else {
            // Default: This Month
            $periodStart = Carbon::now($timezone)->startOfMonth()->startOfDay();
            $periodEnd   = Carbon::now($timezone)->endOfDay();
        }

        // Company COD fee settings
        $codFeeRate = (float) ($company->cod_fee_rate ?? 0.015);   // default 1.5%
        $codVatRate = (float) ($company->cod_vat_rate ?? 0.12);    // default 12%

        // Get the "delivered" status ID
        $deliveredStatusId = ShipmentStatus::where('code', 'delivered')->value('id');

        // ══════════════════════════════════════════════════════════
        // 1. COD SUM — delivered shipments grouped by signing_time
        //    (within the selected period)
        // ══════════════════════════════════════════════════════════
        $codBySigningDate = Shipment::forCompany($companyId)
            ->where('normalized_status_id', $deliveredStatusId)
            ->whereNotNull('signing_time')
            ->whereBetween('signing_time', [$periodStart, $periodEnd])
            ->select(
                DB::raw("DATE(signing_time) as the_date"),
                DB::raw("SUM(cod_amount) as total_cod"),
                DB::raw("COUNT(*) as total_parcels"),
                'courier'
            )
            ->groupBy('the_date', 'courier')
            ->orderBy('the_date', 'desc')
            ->get();

        $grandCodSum = $codBySigningDate->sum('total_cod');

        // ══════════════════════════════════════════════════════════
        // 2. ROW LISTING — based on submission_time
        //    Range: (lowest selected date − 30 days) to (highest selected date)
        // ══════════════════════════════════════════════════════════
        $rowRangeStart = (clone $periodStart)->subDays(30);
        $rowRangeEnd   = $periodEnd;

        $submissionRows = Shipment::forCompany($companyId)
            ->whereNotNull('submission_time')
            ->whereBetween('submission_time', [$rowRangeStart, $rowRangeEnd])
            ->select(
                DB::raw("DATE(submission_time) as the_date"),
                DB::raw("SUM(shipping_cost) as total_sf"),
                DB::raw("SUM(valuation_fee) as total_valuation_fee"),
                DB::raw("SUM(cod_amount) as total_cod"),
                DB::raw("COUNT(*) as total_parcels"),
                'courier'
            )
            ->groupBy('the_date', 'courier')
            ->orderBy('the_date', 'desc')
            ->get();

        // ══════════════════════════════════════════════════════════
        // 3. SF SUM — shipping fees within the selected period
        //    (submission_time within periodStart to periodEnd)
        // ══════════════════════════════════════════════════════════
        $sfInPeriod = Shipment::forCompany($companyId)
            ->whereNotNull('submission_time')
            ->whereBetween('submission_time', [$periodStart, $periodEnd])
            ->sum('shipping_cost');

        $valuationFeeInPeriod = Shipment::forCompany($companyId)
            ->whereNotNull('submission_time')
            ->whereBetween('submission_time', [$periodStart, $periodEnd])
            ->sum('valuation_fee');

        // ══════════════════════════════════════════════════════════
        // 4. COMPUTE REMITTANCE
        // ══════════════════════════════════════════════════════════
        $codFee    = $grandCodSum * $codFeeRate;
        $codFeeVat = $codFee * $codVatRate;
        $totalDeductions = $sfInPeriod + $valuationFeeInPeriod + $codFee + $codFeeVat;
        $netRemittance   = $grandCodSum - $totalDeductions;

        $grandTotals = (object) [
            'total_cod'           => $grandCodSum,
            'total_sf'            => $sfInPeriod,
            'total_valuation_fee' => $valuationFeeInPeriod,
            'cod_fee'             => $codFee,
            'cod_fee_vat'         => $codFeeVat,
            'total_deductions'    => $totalDeductions,
            'net_remittance'      => $netRemittance,
            'total_delivered'     => $codBySigningDate->sum('total_parcels'),
            'total_submitted'     => $submissionRows->sum('total_parcels'),
        ];

        return view('remittance.index', compact(
            'codBySigningDate',
            'submissionRows',
            'grandTotals',
            'periodStart',
            'periodEnd',
            'rowRangeStart',
            'rowRangeEnd',
            'codFeeRate',
            'codVatRate',
            'company'
        ));
    }
}
