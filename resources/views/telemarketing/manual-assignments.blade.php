@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 font-weight-bold">Manual Assignment & Logs</h5>
                    <a href="{{ route('telemarketing.dashboard') }}" class="btn btn-outline-secondary btn-sm">← Back to Dashboard</a>
                </div>
                <div class="card-body">
                    <form action="{{ route('telemarketing.manual-assignments.assign') }}" method="POST" class="row g-3 align-items-end mb-4">
                        @csrf
                        <div class="col-md-2">
                            <label class="form-label small font-weight-bold">Agent</label>
                            <select name="telemarketer_id" class="form-select form-select-sm" required>
                                <option value="">Select Agent...</option>
                                @foreach($telemarketers as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small font-weight-bold">Status</label>
                            <select name="status_id" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->id }}">{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small font-weight-bold">Date From</label>
                            <input type="date" name="date_from" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small font-weight-bold">Date To</label>
                            <input type="date" name="date_to" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small font-weight-bold">Limit</label>
                            <input type="number" name="limit" value="100" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm w-100 font-weight-bold">Assign Shipments</button>
                        </div>
                    </form>

                    <hr>

                    <h6 class="mt-4 mb-3 font-weight-bold">Assignment History & Stats</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle small">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Assigned To</th>
                                    <th>Filters Used</th>
                                    <th>Total Assigned</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                @php
                                    // Calculate stats for this batch
                                    $completedCount = \App\Models\Shipment::whereIn('id', $log->shipment_ids ?? [])
                                        ->where('telemarketing_attempt_count', '>', 0)
                                        ->count();
                                    $percent = $log->shipment_count > 0 ? round(($completedCount / $log->shipment_count) * 100) : 0;
                                @endphp
                                <tr>
                                    <td>{{ $log->assigned_at->format('M d, Y h:i A') }}</td>
                                    <td><strong>{{ $log->assignedTo->name }}</strong></td>
                                    <td class="text-muted">{{ $log->status_filters }}</td>
                                    <td><span class="badge bg-secondary">{{ $log->shipment_count }} items</span></td>
                                    <td>
                                        <div class="progress" style="height: 15px;">
                                            <div class="progress-bar {{ $percent == 100 ? 'bg-success' : 'bg-info' }}" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">{{ $percent }}%</div>
                                        </div>
                                        <small class="text-muted">{{ $completedCount }} / {{ $log->shipment_count }} called</small>
                                    </td>
                                    <td>
                                        @if($percent == 100)
                                            <span class="badge bg-success">Completed</span>
                                        @else
                                            <span class="badge bg-warning text-dark">In Progress</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No assignment logs found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="mt-3">
                            {{ $logs->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
