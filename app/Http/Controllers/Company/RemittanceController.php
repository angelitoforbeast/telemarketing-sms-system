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
     * POOL: all shipments where submission_time is within
     *       (selected_start − 30 days) to (selected_end).
     *
     * Each row = one submission_time date, showing from that pool:
     *   - Delivered count + COD Sum  (only delivered shipments in pool on that date)
     *   - COD Fee = COD Sum × COD Fee Rate
     *   - COD Fee VAT = COD Fee × VAT Rate
     *   - Parcels Picked Up (all shipments in pool on that date)
     *   - Total Shipping Cost (sum of shipping_cost for that date)
     *   - Remittance = COD Sum − COD Fee − COD Fee VAT − Shipping Cost
     *
     * Default: This Month.  Preset: last_month.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $company = $user->company;
        $companyId = $user->company_id;
        $timezone = 'Asia/Manila';

        // ── Selected Date Range ──
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

        // Pool range: selected start − 30 days to selected end
        $poolStart = (clone $periodStart)->subDays(30);
        $poolEnd   = $periodEnd;

        // Company COD fee settings
        $codFeeRate = (float) ($company->cod_fee_rate ?? 0.015);   // default 1.5%
        $codVatRate = (float) ($company->cod_vat_rate ?? 0.12);    // default 12%

        // Get the "delivered" status ID
        $deliveredStatusId = ShipmentStatus::where('code', 'delivered')->value('id');

        // ── Query the pool: all shipments with submission_time in pool range ──
        // Group by submission_time date, compute per-date stats
        $rows = Shipment::forCompany($companyId)
            ->whereNotNull('submission_time')
            ->whereBetween('submission_time', [$poolStart, $poolEnd])
            ->select(
                DB::raw("DATE(submission_time) as date"),
                // All parcels picked up on that date
                DB::raw("COUNT(*) as picked"),
                // Shipping cost for all parcels on that date
                DB::raw("SUM(shipping_cost) as ship_cost"),
                // Only delivered parcels: count
                DB::raw("SUM(CASE WHEN normalized_status_id = {$deliveredStatusId} THEN 1 ELSE 0 END) as delivered"),
                // Only delivered parcels: COD sum
                DB::raw("SUM(CASE WHEN normalized_status_id = {$deliveredStatusId} THEN cod_amount ELSE 0 END) as cod_sum")
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($row) use ($codFeeRate, $codVatRate) {
                $codSum    = (float) $row->cod_sum;
                $codFee    = $codSum * $codFeeRate;
                $codFeeVat = $codFee * $codVatRate;
                $shipCost  = (float) $row->ship_cost;
                $remittance = $codSum - $codFee - $codFeeVat - $shipCost;

                return [
                    'date'        => $row->date,
                    'delivered'   => (int) $row->delivered,
                    'cod_sum'     => $codSum,
                    'cod_fee'     => $codFee,
                    'cod_fee_vat' => $codFeeVat,
                    'picked'      => (int) $row->picked,
                    'ship_cost'   => $shipCost,
                    'remittance'  => $remittance,
                ];
            })
            ->toArray();

        // ── Compute totals ──
        $totals = [
            'delivered'   => 0,
            'cod_sum'     => 0,
            'cod_fee'     => 0,
            'cod_fee_vat' => 0,
            'picked'      => 0,
            'ship_cost'   => 0,
            'remittance'  => 0,
        ];

        foreach ($rows as $r) {
            $totals['delivered']   += $r['delivered'];
            $totals['cod_sum']     += $r['cod_sum'];
            $totals['cod_fee']     += $r['cod_fee'];
            $totals['cod_fee_vat'] += $r['cod_fee_vat'];
            $totals['picked']      += $r['picked'];
            $totals['ship_cost']   += $r['ship_cost'];
            $totals['remittance']  += $r['remittance'];
        }

        return view('remittance.index', compact(
            'rows',
            'totals',
            'periodStart',
            'periodEnd',
            'poolStart',
            'poolEnd',
            'codFeeRate',
            'codVatRate',
            'company'
        ));
    }
}
