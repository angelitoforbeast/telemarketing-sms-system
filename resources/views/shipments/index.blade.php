<x-app-layout>
    <x-slot name="title">Shipments</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Shipments</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <x-alert type="success">{{ session('success') }}</x-alert>
            @endif

            {{-- Filters --}}
            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-4 py-4">
                    <form method="GET" action="{{ route('shipments.index') }}" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        <div>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search waybill, name, phone..."
                                class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <select name="courier" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Couriers</option>
                                <option value="jnt" {{ request('courier') === 'jnt' ? 'selected' : '' }}>JNT</option>
                                <option value="flash" {{ request('courier') === 'flash' ? 'selected' : '' }}>Flash</option>
                            </select>
                        </div>
                        <div>
                            <select name="status_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Statuses</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->id }}" {{ request('status_id') == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <select name="assigned" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Assignment</option>
                                <option value="unassigned" {{ request('assigned') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                                <option value="assigned" {{ request('assigned') === 'assigned' ? 'selected' : '' }}>Assigned</option>
                            </select>
                        </div>
                        <div>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="From" />
                        </div>
                        <div class="flex gap-2">
                            <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="To" />
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Bulk Actions --}}
            @can('shipments.assign')
            <div x-data="bulkActions()" class="mb-4">
                <div class="flex flex-wrap gap-3 items-center">
                    <form method="POST" action="{{ route('shipments.bulk-assign') }}" id="bulkAssignForm">
                        @csrf
                        <div class="flex gap-2 items-center">
                            <select name="user_id" class="border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">Assign to...</option>
                                @foreach($telemarketers as $tm)
                                    <option value="{{ $tm->id }}">{{ $tm->name }}</option>
                                @endforeach
                            </select>
                            <div id="bulkAssignIds"></div>
                            <button type="submit" class="px-3 py-2 bg-green-600 text-white text-xs rounded-md hover:bg-green-700">Assign Selected</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('shipments.bulk-unassign') }}" id="bulkUnassignForm">
                        @csrf
                        <div id="bulkUnassignIds"></div>
                        <button type="submit" class="px-3 py-2 bg-red-600 text-white text-xs rounded-md hover:bg-red-700">Unassign Selected</button>
                    </form>

                    @can('shipments.auto-assign')
                    <form method="POST" action="{{ route('shipments.auto-assign') }}" class="flex gap-2 items-center">
                        @csrf
                        <input type="number" name="limit" placeholder="Limit" class="w-24 border-gray-300 rounded-md shadow-sm text-sm" />
                        <button type="submit" class="px-3 py-2 bg-purple-600 text-white text-xs rounded-md hover:bg-purple-700">Auto-Assign</button>
                    </form>
                    @endcan
                </div>
            </div>
            @endcan

            {{-- Table --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left"><input type="checkbox" id="selectAll" onclick="toggleAll(this)" class="rounded border-gray-300" /></th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waybill</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Courier</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Consignee</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Province</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assigned To</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Calls</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($shipments as $shipment)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-3"><input type="checkbox" name="shipment_ids[]" value="{{ $shipment->id }}" class="shipment-cb rounded border-gray-300" /></td>
                                    <td class="px-3 py-3 text-sm font-mono text-gray-900">
                                        <a href="{{ route('shipments.show', $shipment) }}" class="text-indigo-600 hover:text-indigo-900">{{ $shipment->waybill_no }}</a>
                                    </td>
                                    <td class="px-3 py-3 text-sm"><x-badge :color="$shipment->courier === 'jnt' ? 'red' : 'orange'">{{ strtoupper($shipment->courier) }}</x-badge></td>
                                    <td class="px-3 py-3 text-sm"><x-badge color="blue">{{ $shipment->status?->name ?? 'Unknown' }}</x-badge></td>
                                    <td class="px-3 py-3 text-sm text-gray-900">{{ Str::limit($shipment->consignee_name, 20) }}</td>
                                    <td class="px-3 py-3 text-sm text-gray-600">{{ $shipment->consignee_phone_1 }}</td>
                                    <td class="px-3 py-3 text-sm text-gray-600">{{ Str::limit($shipment->consignee_province, 15) }}</td>
                                    <td class="px-3 py-3 text-sm text-gray-600">{{ $shipment->assignedTo?->name ?? '-' }}</td>
                                    <td class="px-3 py-3 text-sm text-gray-600">{{ $shipment->telemarketing_attempt_count }}</td>
                                    <td class="px-3 py-3 text-sm">
                                        <a href="{{ route('shipments.show', $shipment) }}" class="text-indigo-600 hover:text-indigo-900 text-xs">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="px-4 py-6 text-center text-sm text-gray-500">No shipments found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t">{{ $shipments->links() }}</div>
            </div>
        </div>
    </div>

    <script>
        function toggleAll(source) {
            document.querySelectorAll('.shipment-cb').forEach(cb => cb.checked = source.checked);
            syncBulkIds();
        }
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('shipment-cb')) syncBulkIds();
        });
        function syncBulkIds() {
            const ids = [...document.querySelectorAll('.shipment-cb:checked')].map(cb => cb.value);
            ['bulkAssignIds', 'bulkUnassignIds'].forEach(containerId => {
                const container = document.getElementById(containerId);
                if (container) {
                    container.innerHTML = ids.map(id => `<input type="hidden" name="shipment_ids[]" value="${id}" />`).join('');
                }
            });
        }
    </script>
</x-app-layout>
