<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RemittanceController extends Controller
{
    /**
     * Remittance report page.
     * Groups delivered shipments by signing_time date.
     * SF = shipping_cost column, COD Fee = sum(COD) * COD Fee Rate, VAT = COD Fee * VAT Rate.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $company = $user->company;
        $companyId = $user->company_id;

        // Date range: default last 30 days including today (Manila time)
        $timezone = 'Asia/Manila';
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'), $timezone)->endOfDay()
            : Carbon::now($timezone)->endOfDay();
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'), $timezone)->startOfDay()
            : Carbon::now($timezone)->subDays(29)->startOfDay();

        // Company COD fee settings
        $codFeeRate = (float) ($company->cod_fee_rate ?? 0.015);   // default 1.5%
        $codVatRate = (float) ($company->cod_vat_rate ?? 0.12);    // default 12%

        // Query: group by signing_time date (delivered shipments only)
        $dailyData = Shipment::forCompany($companyId)
            ->whereNotNull('signing_time')
            ->whereBetween('signing_time', [$startDate, $endDate])
            ->select(
                DB::raw("DATE(signing_time) as delivery_date"),
                DB::raw("COUNT(*) as total_parcels"),
                DB::raw("SUM(cod_amount) as total_cod"),
                DB::raw("SUM(shipping_cost) as total_sf"),
                DB::raw("SUM(valuation_fee) as total_valuation_fee"),
                'courier'
            )
            ->groupBy('delivery_date', 'courier')
            ->orderBy('delivery_date', 'desc')
            ->get()
            ->map(function ($row) use ($codFeeRate, $codVatRate) {
                $codFee = $row->total_cod * $codFeeRate;
                $codVat = $codFee * $codVatRate;
                $totalDeductions = $row->total_sf + $row->total_valuation_fee + $codFee + $codVat;
                $netRemittance = $row->total_cod - $totalDeductions;

                return (object) [
                    'delivery_date'       => $row->delivery_date,
                    'courier'             => $row->courier,
                    'total_parcels'       => $row->total_parcels,
                    'total_cod'           => $row->total_cod,
                    'total_sf'            => $row->total_sf,
                    'total_valuation_fee' => $row->total_valuation_fee,
                    'cod_fee'             => $codFee,
                    'cod_vat'             => $codVat,
                    'total_deductions'    => $totalDeductions,
                    'net_remittance'      => $netRemittance,
                ];
            });

        // Grand totals
        $grandTotals = (object) [
            'total_parcels'       => $dailyData->sum('total_parcels'),
            'total_cod'           => $dailyData->sum('total_cod'),
            'total_sf'            => $dailyData->sum('total_sf'),
            'total_valuation_fee' => $dailyData->sum('total_valuation_fee'),
            'cod_fee'             => $dailyData->sum('cod_fee'),
            'cod_vat'             => $dailyData->sum('cod_vat'),
            'total_deductions'    => $dailyData->sum('total_deductions'),
            'net_remittance'      => $dailyData->sum('net_remittance'),
        ];

        // Also get submission_time based summary
        $submissionData = Shipment::forCompany($companyId)
            ->whereNotNull('submission_time')
            ->whereBetween('submission_time', [$startDate, $endDate])
            ->select(
                DB::raw("DATE(submission_time) as submission_date"),
                DB::raw("COUNT(*) as total_parcels"),
                DB::raw("SUM(cod_amount) as total_cod"),
                DB::raw("SUM(shipping_cost) as total_sf"),
                DB::raw("SUM(valuation_fee) as total_valuation_fee"),
                'courier'
            )
            ->groupBy('submission_date', 'courier')
            ->orderBy('submission_date', 'desc')
            ->get()
            ->map(function ($row) use ($codFeeRate, $codVatRate) {
                $codFee = $row->total_cod * $codFeeRate;
                $codVat = $codFee * $codVatRate;
                $totalDeductions = $row->total_sf + $row->total_valuation_fee + $codFee + $codVat;
                $netRemittance = $row->total_cod - $totalDeductions;

                return (object) [
                    'submission_date'     => $row->submission_date,
                    'courier'             => $row->courier,
                    'total_parcels'       => $row->total_parcels,
                    'total_cod'           => $row->total_cod,
                    'total_sf'            => $row->total_sf,
                    'total_valuation_fee' => $row->total_valuation_fee,
                    'cod_fee'             => $codFee,
                    'cod_vat'             => $codVat,
                    'total_deductions'    => $totalDeductions,
                    'net_remittance'      => $netRemittance,
                ];
            });

        return view('remittance.index', compact(
            'dailyData',
            'submissionData',
            'grandTotals',
            'startDate',
            'endDate',
            'codFeeRate',
            'codVatRate',
            'company'
        ));
    }
}
