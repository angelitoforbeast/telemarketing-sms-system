<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <x-alert type="success">{{ session('success') }}</x-alert>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <x-stat-card title="Total Shipments" :value="number_format($totalShipments)" color="indigo" />
                <x-stat-card title="Unassigned" :value="number_format($unassignedCount)" color="yellow" />
                <x-stat-card title="SMS Sent Today" :value="number_format($todaySmsSent)" color="green" />
                <x-stat-card title="Calls Today" :value="number_format($todayCalls)" color="blue" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Shipments by Status</h3>
                        <div class="space-y-3">
                            @forelse($statusCounts as $statusName => $count)
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">{{ $statusName }}</span>
                                    <span class="text-sm font-semibold text-gray-900">{{ number_format($count) }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No shipments yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Shipments by Courier</h3>
                        <div class="space-y-3">
                            @forelse($courierCounts as $courier => $count)
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">{{ strtoupper($courier) }}</span>
                                    <span class="text-sm font-semibold text-gray-900">{{ number_format($count) }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No shipments yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Recent Imports</h3>
                        @can('import.upload')
                            <a href="{{ route('import.create') }}" class="text-sm text-indigo-600 hover:text-indigo-900">Upload New</a>
                        @endcan
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">File</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Courier</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rows</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uploaded By</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($recentImports as $import)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $import->original_filename }}</td>
                                        <td class="px-4 py-3 text-sm"><x-badge :color="$import->courier === 'jnt' ? 'red' : 'orange'">{{ strtoupper($import->courier) }}</x-badge></td>
                                        <td class="px-4 py-3 text-sm">
                                            <x-badge :color="match($import->status) { 'completed' => 'green', 'processing' => 'blue', 'failed' => 'red', default => 'gray' }">{{ ucfirst($import->status) }}</x-badge>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ number_format($import->total_rows ?? 0) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $import->user?->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $import->created_at->format('M d, Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No imports yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
