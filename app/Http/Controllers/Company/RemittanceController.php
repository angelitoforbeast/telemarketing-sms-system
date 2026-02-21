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
     * Remittance report — single unified table.
     *
     * Each row = one date, showing:
     *   - Delivered count + COD Sum  (by signing_time)
     *   - COD Fee, COD Fee VAT       (computed from COD Sum)
     *   - Parcels Picked Up + SF     (by submission_time)
     *   - Remittance = COD Sum − COD Fee − COD Fee VAT − SF
     *
     * Date range for rows:
     *   (lowest selected date − 30 days) to (highest selected date)
     *   based on whichever date appears (signing_time OR submission_time).
     *
     * Default: This Month.  Preset: last_month.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $company = $user->company;
        $companyId = $user->company_id;
        $timezone = 'Asia/Manila';

        // ── Date Range ──
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

        // Extended range for row listing: start − 30 days
        $rowRangeStart = (clone $periodStart)->subDays(30);
        $rowRangeEnd   = $periodEnd;

        // Company COD fee settings
        $codFeeRate = (float) ($company->cod_fee_rate ?? 0.015);   // default 1.5%
        $codVatRate = (float) ($company->cod_vat_rate ?? 0.12);    // default 12%

        // Get the "delivered" status ID
        $deliveredStatusId = ShipmentStatus::where('code', 'delivered')->value('id');

        // ── 1. Delivered data grouped by signing_time date ──
        $deliveredByDate = Shipment::forCompany($companyId)
            ->where('normalized_status_id', $deliveredStatusId)
            ->whereNotNull('signing_time')
            ->whereBetween('signing_time', [$rowRangeStart, $rowRangeEnd])
            ->select(
                DB::raw("DATE(signing_time) as the_date"),
                DB::raw("COUNT(*) as delivered"),
                DB::raw("SUM(cod_amount) as cod_sum")
            )
            ->groupBy('the_date')
            ->get()
            ->keyBy('the_date');

        // ── 2. Picked-up data grouped by submission_time date ──
        $pickedByDate = Shipment::forCompany($companyId)
            ->whereNotNull('submission_time')
            ->whereBetween('submission_time', [$rowRangeStart, $rowRangeEnd])
            ->select(
                DB::raw("DATE(submission_time) as the_date"),
                DB::raw("COUNT(*) as picked"),
                DB::raw("SUM(shipping_cost) as ship_cost")
            )
            ->groupBy('the_date')
            ->get()
            ->keyBy('the_date');

        // ── 3. Merge into unified rows per date ──
        $allDates = $deliveredByDate->keys()->merge($pickedByDate->keys())->unique()->sort()->reverse();

        $rows = [];
        $totals = [
            'delivered'   => 0,
            'cod_sum'     => 0,
            'cod_fee'     => 0,
            'cod_fee_vat' => 0,
            'picked'      => 0,
            'ship_cost'   => 0,
            'remittance'  => 0,
        ];

        foreach ($allDates as $date) {
            $del = $deliveredByDate->get($date);
            $pic = $pickedByDate->get($date);

            $delivered = $del ? (int) $del->delivered : 0;
            $codSum    = $del ? (float) $del->cod_sum : 0;
            $picked    = $pic ? (int) $pic->picked : 0;
            $shipCost  = $pic ? (float) $pic->ship_cost : 0;

            $codFee    = $codSum * $codFeeRate;
            $codFeeVat = $codFee * $codVatRate;
            $remittance = $codSum - $codFee - $codFeeVat - $shipCost;

            $rows[] = [
                'date'        => $date,
                'delivered'   => $delivered,
                'cod_sum'     => $codSum,
                'cod_fee'     => $codFee,
                'cod_fee_vat' => $codFeeVat,
                'picked'      => $picked,
                'ship_cost'   => $shipCost,
                'remittance'  => $remittance,
            ];

            $totals['delivered']   += $delivered;
            $totals['cod_sum']     += $codSum;
            $totals['cod_fee']     += $codFee;
            $totals['cod_fee_vat'] += $codFeeVat;
            $totals['picked']      += $picked;
            $totals['ship_cost']   += $shipCost;
            $totals['remittance']  += $remittance;
        }

        return view('remittance.index', compact(
            'rows',
            'totals',
            'periodStart',
            'periodEnd',
            'codFeeRate',
            'codVatRate',
            'company'
        ));
    }
}
