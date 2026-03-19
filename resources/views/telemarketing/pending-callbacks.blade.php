@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h5 class="mb-0 font-weight-bold text-primary">Pending Callbacks</h5>
                    <a href="{{ route('telemarketing.dashboard') }}" class="btn btn-outline-secondary btn-sm rounded-pill">← Back to Dashboard</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase font-weight-bold">
                                <tr>
                                    <th class="px-4">Customer / Waybill</th>
                                    <th>Assigned Agent</th>
                                    <th>Last Call Date</th>
                                    <th>Last Disposition</th>
                                    <th>Scheduled Callback</th>
                                    <th class="text-end px-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($callbacks as $shipment)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="d-flex flex-column">
                                            <span class="font-weight-bold text-dark">{{ $shipment->consignee_name }}</span>
                                            <small class="text-primary font-weight-bold">{{ $shipment->waybill_no }}</small>
                                            <small class="text-muted">{{ $shipment->consignee_phone_1 }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        @if($shipment->assignedTo)
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-xs bg-info text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 10px;">
                                                    {{ strtoupper(substr($shipment->assignedTo->name, 0, 2)) }}
                                                </div>
                                                <span class="small">{{ $shipment->assignedTo->name }}</span>
                                            </div>
                                        @else
                                            <span class="badge bg-light text-muted border">Unassigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="small text-muted">{{ $shipment->last_contacted_at ? $shipment->last_contacted_at->format('M d, Y h:i A') : 'Never' }}</span>
                                    </td>
                                    <td>
                                        @if($shipment->lastDisposition)
                                            <span class="badge rounded-pill px-2 py-1 small" style="background-color: {{ $shipment->lastDisposition->color }}; color: #fff;">
                                                {{ $shipment->lastDisposition->name }}
                                            </span>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $isOverdue = $shipment->callback_scheduled_at && $shipment->callback_scheduled_at->isPast();
                                        @endphp
                                        <div class="d-flex flex-column">
                                            <span class="font-weight-bold {{ $isOverdue ? 'text-danger' : 'text-success' }}">
                                                {{ $shipment->callback_scheduled_at ? $shipment->callback_scheduled_at->format('M d, Y h:i A') : '-' }}
                                            </span>
                                            @if($isOverdue)
                                                <small class="badge bg-danger-soft text-danger py-0 px-1 rounded border border-danger" style="width: fit-content; font-size: 9px;">OVERDUE</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-end px-4">
                                        <a href="{{ route('telemarketing.call', $shipment->id) }}" class="btn btn-sm btn-primary rounded-pill px-3">Call Now</a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="d-flex flex-column align-items-center text-muted">
                                            <i class="fas fa-calendar-check fa-3x mb-3 opacity-25"></i>
                                            <p class="mb-0">No pending callbacks found.</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top bg-light">
                        {{ $callbacks->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-danger-soft { background-color: #fff5f5; }
    .font-weight-bold { font-weight: 600 !important; }
    .card { border-radius: 12px; }
    .table thead th { border-top: none; }
</style>
@endsection
