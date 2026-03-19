<?php

namespace App\Http\Controllers;

use App\Models\TelemarketingAssignmentLog;
use App\Models\ShipmentStatus;
use App\Models\User;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelemarketingAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        // Get telemarketers for this company
        $telemarketers = User::where('company_id', $companyId)
            ->where('is_active', true)
            ->role('Telemarketer')
            ->get();

        // Get shipment statuses for the dropdown
        $statuses = ShipmentStatus::orderBy('sort_order')->get();

        // Get assignment logs for this company
        $logs = TelemarketingAssignmentLog::where('company_id', $companyId)
            ->with(['assignedBy', 'assignedTo'])
            ->orderByDesc('assigned_at')
            ->paginate(15);

        return view('telemarketing.manual-assignments', compact('telemarketers', 'statuses', 'logs'));
    }

    public function assign(Request $request)
    {
        $request->validate([
            'telemarketer_id' => 'required|exists:users,id',
            'status_id' => 'nullable|exists:shipment_statuses,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'limit' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $companyId = $user->company_id;
        $agent = User::findOrFail($request->telemarketer_id);

        // Build query for unassigned shipments
        $query = Shipment::where('company_id', $companyId)
            ->whereNull('assigned_to_user_id');

        if ($request->filled('status_id')) {
            $query->where('normalized_status_id', $request->status_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $shipmentsToAssign = $query->limit($request->limit)->get();

        if ($shipmentsToAssign->isEmpty()) {
            return redirect()->back()->with('error', 'No matching unassigned shipments found.');
        }

        // Assign shipments in bulk
        $shipmentIds = $shipmentsToAssign->pluck('id')->toArray();
        Shipment::whereIn('id', $shipmentIds)->update([
            'assigned_to_user_id' => $agent->id,
            'assigned_at' => now(),
        ]);

        // Build status filter label
        $statusLabel = 'All Statuses';
        if ($request->filled('status_id')) {
            $status = ShipmentStatus::find($request->status_id);
            $statusLabel = $status ? $status->name : 'Unknown';
        }
        $dateFilter = '';
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $dateFilter = ' | Date: ' . ($request->date_from ?? '...') . ' to ' . ($request->date_to ?? '...');
        }

        // Log the assignment
        TelemarketingAssignmentLog::create([
            'company_id' => $companyId,
            'assigned_by_user_id' => $user->id,
            'assigned_to_user_id' => $agent->id,
            'shipment_count' => count($shipmentIds),
            'shipment_ids' => $shipmentIds,
            'status_filters' => $statusLabel . $dateFilter,
            'assigned_at' => now(),
        ]);

        return redirect()->back()->with('success', count($shipmentIds) . ' shipments assigned to ' . $agent->name . ' successfully!');
    }

    public function pendingCallbacks(Request $request)
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        // Get shipments with pending callbacks
        $callbacks = Shipment::where('company_id', $companyId)
            ->whereNotNull('callback_scheduled_at')
            ->with(['assignedTo', 'lastDisposition'])
            ->orderBy('callback_scheduled_at', 'asc')
            ->paginate(20);

        return view('telemarketing.pending-callbacks', compact('callbacks'));
    }
}
