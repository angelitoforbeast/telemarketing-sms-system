<?php

namespace App\Http\Controllers;

use App\Models\TelemarketingAssignment;
use App\Models\TelemarketingLog;
use App\Models\TelemarketingDisposition;
use App\Models\User;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelemarketingAssignmentController extends Controller
{
    public function index(Request $request)
    {
        Log::info('TelemarketingAssignmentController@index called');
        $agents = collect();
        $statuses = collect();
        $logs = collect();

        return view("telemarketing.manual-assignments", compact("agents", "statuses", "logs"));
    }

    public function assign(Request $request)
    {
        $request->validate([
            "agent_id" => "required|exists:users,id",
            "status_filter" => "nullable|string",
            "date_range_filter" => "nullable|string",
            "limit_filter" => "nullable|integer|min:1",
            "disposition_date" => "nullable|date",
        ]);

        $company = Auth::user()->company;
        $agent = User::find($request->agent_id);

        $query = Shipment::where("company_id", $company->id)
            ->whereNull("assigned_to_user_id");

        if ($request->filled("status_filter")) {
            $query->where("status", $request->status_filter);
        }

        if ($request->filled("date_range_filter")) {
            list($from, $to) = explode(" to ", $request->date_range_filter);
            $query->whereBetween(DB::raw("DATE(created_at)"), [$from, $to]);
        }

        $totalShipments = $query->count();
        $limit = $request->limit_filter ?? $totalShipments;

        $shipmentsToAssign = $query->limit($limit)->get();

        foreach ($shipmentsToAssign as $shipment) {
            $shipment->assigned_to_user_id = $agent->id;
            $shipment->assigned_at = now();
            $shipment->save();
        }

        TelemarketingAssignment::create([
            "user_id" => Auth::id(),
            "assigned_to_user_id" => $agent->id,
            "status_filter" => $request->status_filter,
            "date_range_filter" => $request->date_range_filter,
            "limit_filter" => $limit,
            "total_shipments" => $totalShipments,
            "assigned_shipments" => $shipmentsToAssign->count(),
            "disposition_date" => $request->disposition_date,
            "shipment_ids" => $shipmentsToAssign->pluck("id")->toArray(),
        ]);

        return redirect()->back()->with("success", "Shipments assigned successfully!");
    }

    public function pendingCallbacks(Request $request)
    {
        $company = Auth::user()->company;

        $pendingCallbacks = TelemarketingLog::where("company_id", $company->id)
            ->whereNotNull("callback_at")
            ->where("callback_at", "<=", now()->addDay())
            ->with(["shipment", "user", "disposition"])
            ->latest("callback_at")
            ->paginate(10);

        return view("telemarketing.pending-callbacks", compact("pendingCallbacks"));
    }
}
