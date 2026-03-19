<x-app-layout>
    <x-slot name="title">Shipment Details</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Shipment: {{ $shipment->waybill_no }}</h2>
            <div class="flex items-center gap-4">
                @can('shipments.delete')
                <form method="POST" action="{{ route('shipments.destroy', $shipment) }}"
                      onsubmit="return confirm('Are you sure you want to DELETE this shipment? This action cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-3 py-2 bg-red-600 text-white text-xs rounded-md hover:bg-red-700">
                        Delete Shipment
                    </button>
                </form>
                @endcan
                <a href="{{ route('shipments.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to List</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Shipment Info --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-6 py-5">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Shipment Details</h3>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                            <div><dt class="text-xs text-gray-500">Waybill No.</dt><dd class="text-sm font-mono font-semibold">{{ $shipment->waybill_no }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Courier</dt><dd><x-badge :color="$shipment->courier === 'jnt' ? 'red' : 'orange'">{{ strtoupper($shipment->courier) }}</x-badge></dd></div>
                            <div><dt class="text-xs text-gray-500">Status</dt><dd><x-badge color="blue">{{ $shipment->status?->name ?? 'Unknown' }}</x-badge></dd></div>
                            <div><dt class="text-xs text-gray-500">Payment Method</dt><dd class="text-sm">{{ $shipment->payment_method ?? '-' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">COD Amount</dt><dd class="text-sm font-semibold">{{ $shipment->cod_amount ? '₱' . number_format($shipment->cod_amount, 2) : '-' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Weight (kg)</dt><dd class="text-sm">{{ $shipment->item_weight ?? '-' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Assigned To</dt><dd class="text-sm">{{ $shipment->assignedTo?->name ?? 'Unassigned' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Call Attempts</dt><dd class="text-sm">{{ $shipment->telemarketing_attempt_count }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-6 py-5">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Consignee Information</h3>
                        <dl class="grid grid-cols-1 gap-y-3">
                            <div><dt class="text-xs text-gray-500">Name</dt><dd class="text-sm font-semibold">{{ $shipment->consignee_name }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Phone 1</dt><dd class="text-sm font-mono">{{ $shipment->consignee_phone_1 ?? '-' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Phone 2</dt><dd class="text-sm font-mono">{{ $shipment->consignee_phone_2 ?? '-' }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Address</dt><dd class="text-sm">{{ $shipment->consignee_address }}</dd></div>
                            <div class="grid grid-cols-3 gap-2">
                                <div><dt class="text-xs text-gray-500">Province</dt><dd class="text-sm">{{ $shipment->consignee_province ?? '-' }}</dd></div>
                                <div><dt class="text-xs text-gray-500">City</dt><dd class="text-sm">{{ $shipment->consignee_city ?? '-' }}</dd></div>
                                <div><dt class="text-xs text-gray-500">Barangay</dt><dd class="text-sm">{{ $shipment->consignee_barangay ?? '-' }}</dd></div>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Status History --}}
            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-6 py-5">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Status History</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Source Text</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Import Job</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($shipment->statusLogs as $log)
                                    <tr>
                                        <td class="px-4 py-2 text-sm"><x-badge color="blue">{{ $log->status?->name ?? 'N/A' }}</x-badge></td>
                                        <td class="px-4 py-2 text-sm text-gray-600">{{ $log->source_status_text ?? '-' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600">#{{ $log->import_job_id }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-500">{{ $log->logged_at?->format('M d, H:i') ?? $log->created_at->format('M d, H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-4 text-center text-sm text-gray-500">No status history.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Call History --}}
            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-6 py-5">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Telemarketing Call History</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Agent</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Disposition</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Phone Called</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Callback</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($shipment->telemarketingLogs as $log)
                                    <tr>
                                        <td class="px-4 py-2 text-sm">{{ $log->attempt_no }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $log->user?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-2 text-sm"><x-badge color="purple">{{ $log->disposition?->name ?? 'N/A' }}</x-badge></td>
                                        <td class="px-4 py-2 text-sm font-mono text-gray-600">{{ $log->phone_called ?? '-' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-600">{{ Str::limit($log->notes, 40) }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-500">{{ $log->callback_at?->format('M d, H:i') ?? '-' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-500">{{ $log->created_at->format('M d, H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">No calls logged yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- SMS History --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-6 py-5">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">SMS History</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Campaign</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($shipment->smsSendLogs as $log)
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $log->campaign?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-2 text-sm font-mono text-gray-600">{{ $log->phone_number }}</td>
                                        <td class="px-4 py-2 text-sm">
                                            <x-badge :color="match($log->status) { 'sent' => 'green', 'failed' => 'red', default => 'gray' }">{{ ucfirst($log->status) }}</x-badge>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-500">{{ $log->created_at->format('M d, H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-4 text-center text-sm text-gray-500">No SMS sent yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
