<?php

namespace App\Http\Controllers\Telemarketing;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\TelemarketingDisposition;
use App\Services\Telemarketing\TelemarketingService;
use Illuminate\Http\Request;

class TelemarketingController extends Controller
{
    public function __construct(
        protected TelemarketingService $telemarketingService
    ) {}

    /**
     * Show the telemarketer's call queue.
     */
    public function queue(Request $request)
    {
        $user = $request->user();

        $shipments = $this->telemarketingService->getQueue(
            $user->id,
            $user->company_id,
            $request->all()
        );

        return view('telemarketing.queue', compact('shipments'));
    }

    /**
     * Show the call form for a specific shipment.
     */
    public function callForm(Shipment $shipment)
    {
        $this->authorizeAssignment($shipment);

        $shipment->load(['status', 'telemarketingLogs.disposition', 'telemarketingLogs.user']);

        $dispositions = TelemarketingDisposition::forCompany(auth()->user()->company_id)
            ->orderBy('sort_order')
            ->get();

        $callHistory = $this->telemarketingService->getCallHistory($shipment->id);

        return view('telemarketing.call', compact('shipment', 'dispositions', 'callHistory'));
    }

    /**
     * Log a call attempt.
     */
    public function logCall(Request $request, Shipment $shipment)
    {
        $this->authorizeAssignment($shipment);

        $request->validate([
            'disposition_id' => 'required|integer|exists:telemarketing_dispositions,id',
            'notes' => 'nullable|string|max:1000',
            'callback_at' => 'nullable|date|after:now',
            'phone_called' => 'nullable|string|max:20',
        ]);

        $this->telemarketingService->logCall(
            $shipment->id,
            $request->user()->id,
            $request->disposition_id,
            $request->notes,
            $request->callback_at,
            $request->phone_called
        );

        return redirect()->route('telemarketing.queue')
            ->with('success', 'Call logged successfully for ' . $shipment->waybill_no);
    }

    protected function authorizeAssignment(Shipment $shipment): void
    {
        $user = auth()->user();

        if ($shipment->company_id !== $user->company_id) {
            abort(403);
        }

        // Telemarketers can only access their own assigned shipments
        if ($user->hasRole('Telemarketer') && $shipment->assigned_to_user_id !== $user->id) {
            abort(403, 'This shipment is not assigned to you.');
        }
    }
}
