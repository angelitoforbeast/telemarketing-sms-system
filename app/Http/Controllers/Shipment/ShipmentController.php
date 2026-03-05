<?php

namespace App\Http\Controllers\Shipment;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\User;
use App\Services\Shipment\ShipmentService;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(
        protected ShipmentService $shipmentService
    ) {}

    /**
     * List shipments with filters.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;

        $shipments = $this->shipmentService->list($companyId, $request->all());
        $statuses = ShipmentStatus::orderBy('sort_order')->get();
        $telemarketers = User::forCompany($companyId)->active()->role('Telemarketer')->get();

        // Get unique shop names and item descriptions for searchable dropdown filters
        $shopNames = Shipment::forCompany($companyId)
            ->whereNotNull('sender_name')
            ->where('sender_name', '!=', '')
            ->distinct()
            ->orderBy('sender_name')
            ->pluck('sender_name');

        $itemDescriptions = Shipment::forCompany($companyId)
            ->whereNotNull('item_description')
            ->where('item_description', '!=', '')
            ->distinct()
            ->orderBy('item_description')
            ->pluck('item_description');

        return view('shipments.index', compact('shipments', 'statuses', 'telemarketers', 'shopNames', 'itemDescriptions'));
    }

    /**
     * Show a single shipment detail.
     */
    public function show(Shipment $shipment)
    {
        $this->authorizeCompany($shipment);

        $shipment->load([
            'status',
            'assignedTo',
            'statusLogs.status',
            'telemarketingLogs.user',
            'telemarketingLogs.disposition',
            'smsSendLogs.campaign',
        ]);

        return view('shipments.show', compact('shipment'));
    }

    /**
     * Bulk assign shipments to a telemarketer.
     */
    public function bulkAssign(Request $request)
    {
        $request->validate([
            'shipment_ids' => 'required|array|min:1',
            'shipment_ids.*' => 'integer|exists:shipments,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = $request->user();
        $count = $this->shipmentService->bulkAssign(
            $request->shipment_ids,
            $request->user_id,
            $user->company_id
        );

        return back()->with('success', "{$count} shipments assigned successfully.");
    }

    /**
     * Auto-assign unassigned shipments round-robin.
     */
    public function autoAssign(Request $request)
    {
        $request->validate([
            'status_id' => 'nullable|integer|exists:shipment_statuses,id',
            'limit' => 'nullable|integer|min:1|max:5000',
        ]);

        $user = $request->user();
        $count = $this->shipmentService->autoAssign(
            $user->company_id,
            $request->status_id,
            $request->limit
        );

        return back()->with('success', "{$count} shipments auto-assigned successfully.");
    }

    /**
     * Bulk unassign shipments.
     */
    public function bulkUnassign(Request $request)
    {
        $request->validate([
            'shipment_ids' => 'required|array|min:1',
            'shipment_ids.*' => 'integer|exists:shipments,id',
        ]);

        $user = $request->user();
        $count = $this->shipmentService->unassign(
            $request->shipment_ids,
            $user->company_id
        );

        return back()->with('success', "{$count} shipments unassigned.");
    }

    protected function authorizeCompany(Shipment $shipment): void
    {
        if ($shipment->company_id !== auth()->user()->company_id) {
            abort(403);
        }
    }
}
