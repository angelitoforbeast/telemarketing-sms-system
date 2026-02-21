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
     * Remittance report — exactly mirrors the proven working logic:
     *
     *  1. Delivered query  → GROUP BY DATE(signing_time)  → delivered_count + cod_sum
     *  2. Picked-up query  → GROUP BY DATE(submission_time) → picked_count + shipping_cost_sum
     *  3. Merge by date    → compute COD Fee, COD Fee VAT, Remittance per row
     *
     * Both queries use the SAME selected date range (no −30 days extension).
     * Default: This Month.  Preset: last_month.
     */
    public function index(Request $request)
    {
        $user      = $request->user();
        $company   = $user->company;
        $companyId = $user->company_id;
        $tz        = 'Asia/Manila';

        // ── Date Range ──────────────────────────────────────────────
        $start = $request->input('start_date');
        $end   = $request->input('end_date');

        if ($request->input('preset') === 'last_month') {
            $start = Carbon::now($tz)->subMonth()->startOfMonth()->toDateString();
            $end   = Carbon::now($tz)->subMonth()->endOfMonth()->toDateString();
        } elseif (!$start && !$end) {
            // Default: This Month
            $start = Carbon::now($tz)->startOfMonth()->toDateString();
            $end   = Carbon::now($tz)->toDateString();
        } else {
            if ($start && !$end)  $end   = $start;
            if (!$start && $end)  $start = $end;
        }

        // ── Company COD settings ────────────────────────────────────
        $codFeeRate = (float) ($company->cod_fee_rate ?? 0.015);
        $codVatRate = (float) ($company->cod_vat_rate ?? 0.12);

        // ── Delivered status ID ─────────────────────────────────────
        $deliveredStatusId = ShipmentStatus::where('code', 'delivered')->value('id');

        // ── Query 1: Delivered by signing_time ──────────────────────
        $delivered = Shipment::forCompany($companyId)
            ->where('normalized_status_id', $deliveredStatusId)
            ->whereNotNull('signing_time')
            ->whereBetween(DB::raw('DATE(signing_time)'), [$start, $end])
            ->select(
                DB::raw('DATE(signing_time) as d'),
                DB::raw('COUNT(*) as delivered_count'),
                DB::raw('COALESCE(SUM(cod_amount), 0) as cod_sum')
            )
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        // ── Query 2: Picked up by submission_time ───────────────────
        $picked = Shipment::forCompany($companyId)
            ->whereNotNull('submission_time')
            ->whereBetween(DB::raw('DATE(submission_time)'), [$start, $end])
            ->select(
                DB::raw('DATE(submission_time) as d'),
                DB::raw('COUNT(*) as picked_count'),
                DB::raw('COALESCE(SUM(shipping_cost), 0) as ship_cost_sum')
            )
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        // ── Merge by date ───────────────────────────────────────────
        $byDate = [];

        foreach ($delivered as $r) {
            $d = $r->d;
            $byDate[$d] = $byDate[$d] ?? ['date' => $d, 'delivered' => 0, 'cod_sum' => 0.0, 'picked' => 0, 'ship_cost' => 0.0];
            $byDate[$d]['delivered'] = (int) $r->delivered_count;
            $byDate[$d]['cod_sum']   = (float) $r->cod_sum;
        }

        foreach ($picked as $r) {
            $d = $r->d;
            $byDate[$d] = $byDate[$d] ?? ['date' => $d, 'delivered' => 0, 'cod_sum' => 0.0, 'picked' => 0, 'ship_cost' => 0.0];
            $byDate[$d]['picked']    = (int) $r->picked_count;
            $byDate[$d]['ship_cost'] = (float) $r->ship_cost_sum;
        }

        // ── Compute per-row values + totals ─────────────────────────
        $rows   = [];
        $totals = [
            'delivered'   => 0,
            'cod_sum'     => 0.0,
            'cod_fee'     => 0.0,
            'cod_fee_vat' => 0.0,
            'picked'      => 0,
            'ship_cost'   => 0.0,
            'remittance'  => 0.0,
        ];

        foreach ($byDate as $d => $vals) {
            $deliveredCnt = (int)   $vals['delivered'];
            $codSum       = (float) $vals['cod_sum'];
            $pickedCnt    = (int)   $vals['picked'];
            $shipCost     = (float) $vals['ship_cost'];

            $codFee    = round($codSum * $codFeeRate, 2);
            $codFeeVat = round($codFee * $codVatRate, 2);
            $remit     = round($codSum - $codFee - $codFeeVat - $shipCost, 2);

            $rows[] = [
                'date'        => $d,
                'delivered'   => $deliveredCnt,
                'cod_sum'     => $codSum,
                'cod_fee'     => $codFee,
                'cod_fee_vat' => $codFeeVat,
                'picked'      => $pickedCnt,
                'ship_cost'   => $shipCost,
                'remittance'  => $remit,
            ];

            $totals['delivered']   += $deliveredCnt;
            $totals['cod_sum']     += $codSum;
            $totals['cod_fee']     += $codFee;
            $totals['cod_fee_vat'] += $codFeeVat;
            $totals['picked']      += $pickedCnt;
            $totals['ship_cost']   += $shipCost;
            $totals['remittance']  += $remit;
        }

        // Sort by date ascending
        usort($rows, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return view('remittance.index', [
            'rows'       => $rows,
            'totals'     => $totals,
            'start'      => $start,
            'end'        => $end,
            'codFeeRate' => $codFeeRate,
            'codVatRate' => $codVatRate,
            'company'    => $company,
        ]);
    }
}
