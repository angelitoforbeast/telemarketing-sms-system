<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Call Queue</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <x-alert type="success">{{ session('success') }}</x-alert>
            @endif

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waybill</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Courier</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Consignee</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Province</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attempts</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Contact</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($shipments as $shipment)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $shipment->waybill_no }}</td>
                                    <td class="px-4 py-3 text-sm"><x-badge :color="$shipment->courier === 'jnt' ? 'red' : 'orange'">{{ strtoupper($shipment->courier) }}</x-badge></td>
                                    <td class="px-4 py-3 text-sm"><x-badge color="blue">{{ $shipment->status?->name ?? 'Unknown' }}</x-badge></td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ Str::limit($shipment->consignee_name, 20) }}</td>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ $shipment->consignee_phone_1 }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ Str::limit($shipment->consignee_province, 15) }}</td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-600">{{ $shipment->telemarketing_attempt_count }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $shipment->last_contacted_at?->format('M d, H:i') ?? 'Never' }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <a href="{{ route('telemarketing.call', $shipment) }}" class="inline-flex items-center px-3 py-1 bg-green-600 text-white text-xs rounded-md hover:bg-green-700">
                                            Call
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-4 py-6 text-center text-sm text-gray-500">No shipments in your queue. Ask your manager to assign shipments to you.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t">{{ $shipments->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
