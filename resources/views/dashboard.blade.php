<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Date Range Filter --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                        <input type="text" id="dateRange" name="date_range"
                               class="border-gray-300 rounded-md shadow-sm text-sm w-72"
                               placeholder="Select date range..." readonly>
                        <input type="hidden" name="start_date" id="startDate" value="{{ $startDate->format('Y-m-d') }}">
                        <input type="hidden" name="end_date" id="endDate" value="{{ $endDate->format('Y-m-d') }}">
                    </div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                        Apply Filter
                    </button>
                </form>
                <p class="mt-2 text-xs text-gray-500">
                    Showing data from <strong>{{ $startDate->format('M d, Y') }}</strong> to <strong>{{ $endDate->format('M d, Y') }}</strong>
                </p>
            </div>

            {{-- Key Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <x-stat-card title="Total Shipments" :value="number_format($totalShipments)" color="blue" />
                <x-stat-card title="Unassigned" :value="number_format($unassignedCount)" color="yellow" />
                <x-stat-card title="Imports" :value="number_format($periodImports)" color="green" />
                <x-stat-card title="SMS Sent" :value="number_format($periodSmsSent)" color="purple" />
                <x-stat-card title="Calls Made" :value="number_format($periodCalls)" color="pink" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Status Breakdown --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">By Status</h3>
                    @if($statusCounts->isEmpty())
                        <p class="text-gray-500 text-sm">No shipments in this period.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($statusCounts as $statusName => $count)
                                @php
                                    $percentage = $totalShipments > 0 ? ($count / $totalShipments) * 100 : 0;
                                    $colors = [
                                        'In Transit' => 'bg-blue-500', 'Delivering' => 'bg-yellow-500',
                                        'Delivered' => 'bg-green-500', 'Returned' => 'bg-red-500',
                                        'For Return' => 'bg-orange-500', 'Failed Delivery' => 'bg-red-400',
                                        'Picked Up' => 'bg-indigo-500', 'Closed' => 'bg-gray-500',
                                    ];
                                    $barColor = $colors[$statusName] ?? 'bg-gray-400';
                                @endphp
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="font-medium text-gray-700">{{ $statusName }}</span>
                                        <span class="text-gray-500">{{ number_format($count) }} ({{ number_format($percentage, 1) }}%)</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="{{ $barColor }} h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Courier Breakdown --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">By Courier</h3>
                    @if($courierCounts->isEmpty())
                        <p class="text-gray-500 text-sm">No shipments in this period.</p>
                    @else
                        <div class="space-y-4">
                            @foreach($courierCounts as $courier => $count)
                                @php
                                    $percentage = $totalShipments > 0 ? ($count / $totalShipments) * 100 : 0;
                                @endphp
                                <div class="flex items-center gap-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded text-sm font-bold {{ $courier === 'jnt' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }} w-20 justify-center">
                                        {{ strtoupper($courier) }}
                                    </span>
                                    <div class="flex-1">
                                        <div class="w-full bg-gray-200 rounded-full h-3">
                                            <div class="{{ $courier === 'jnt' ? 'bg-red-500' : 'bg-yellow-500' }} h-3 rounded-full" style="width: {{ $percentage }}%"></div>
                                        </div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 w-32 text-right">{{ number_format($count) }} ({{ number_format($percentage, 1) }}%)</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Recent Imports --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Imports</h3>
                    <a href="{{ route('import.index') }}" class="text-sm text-indigo-600 hover:underline">View All</a>
                </div>
                @if($recentImports->isEmpty())
                    <p class="text-gray-500 text-sm">No imports yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">File</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Courier</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">Rows</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">By</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($recentImports as $import)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('import.show', $import) }}" class="text-indigo-600 hover:underline">
                                                {{ Str::limit($import->original_filename, 40) }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $import->courier === 'jnt' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ strtoupper($import->courier) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @php
                                                $statusColors = ['completed' => 'green', 'processing' => 'blue', 'queued' => 'yellow', 'failed' => 'red'];
                                                $c = $statusColors[$import->status] ?? 'gray';
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-{{ $c }}-100 text-{{ $c }}-800">
                                                {{ ucfirst($import->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">{{ number_format($import->processed_rows ?? 0) }}</td>
                                        <td class="px-4 py-3">{{ $import->user?->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $import->created_at->format('M d, H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>

    @push('scripts')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr('#dateRange', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                defaultDate: ['{{ $startDate->format("Y-m-d") }}', '{{ $endDate->format("Y-m-d") }}'],
                maxDate: 'today',
                onChange: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        const fmt = d => d.toISOString().split('T')[0];
                        document.getElementById('startDate').value = fmt(selectedDates[0]);
                        document.getElementById('endDate').value = fmt(selectedDates[1]);
                    }
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
