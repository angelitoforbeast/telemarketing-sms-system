<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Telemarketing Dashboard</h2>
            <a href="{{ route('telemarketing.next-call') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition shadow-sm">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                Start Calling
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
            @endif
            @if(session('info'))
                <div class="mb-4 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg">{{ session('info') }}</div>
            @endif

            {{-- Assigned Statuses Badge --}}
            @if(!empty($stats['assigned_statuses']))
                <div class="mb-4 bg-indigo-50 border border-indigo-200 px-4 py-3 rounded-lg flex items-center">
                    <svg class="w-5 h-5 text-indigo-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                    <span class="text-sm text-indigo-700">
                        <strong>Your assigned statuses:</strong>
                        @foreach($stats['assigned_statuses'] as $statusName)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-200 text-indigo-800 ml-1">{{ $statusName }}</span>
                        @endforeach
                    </span>
                </div>
            @endif

            {{-- Stats Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <x-stat-card title="Pending Queue" :value="$stats['total_assigned']" color="indigo" />
                <x-stat-card title="Calls Today" :value="$stats['calls_today']" color="blue" />
                <x-stat-card title="Completed Today" :value="$stats['completed_today']" color="green" />
                <x-stat-card title="Callbacks Due" :value="$stats['callbacks_due']" color="orange" />
                <x-stat-card title="Never Contacted" :value="$stats['never_contacted']" color="purple" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Today's Disposition Breakdown --}}
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Today's Call Results</h3>
                    @if($stats['disposition_breakdown']->isEmpty())
                        <p class="text-sm text-gray-500">No calls logged today. Start calling to see your stats!</p>
                    @else
                        <div class="space-y-3">
                            @foreach($stats['disposition_breakdown'] as $item)
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="w-3 h-3 rounded-full mr-3 bg-{{ $item->color }}-500"></span>
                                        <span class="text-sm text-gray-700">{{ $item->name }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900">{{ $item->count }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Quick Actions --}}
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="{{ route('telemarketing.next-call') }}" class="flex items-center p-3 bg-green-50 rounded-lg hover:bg-green-100 transition">
                            <div class="flex-shrink-0 w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-green-800">Start Next Call</p>
                                <p class="text-xs text-green-600">Auto-advance to the highest priority shipment</p>
                            </div>
                        </a>

                        <a href="{{ route('telemarketing.queue') }}" class="flex items-center p-3 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                            <div class="flex-shrink-0 w-10 h-10 bg-indigo-500 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-indigo-800">View Full Queue</p>
                                <p class="text-xs text-indigo-600">Browse and filter all assigned shipments</p>
                            </div>
                        </a>

                        <a href="{{ route('telemarketing.queue', ['callbacks_only' => 1]) }}" class="flex items-center p-3 bg-orange-50 rounded-lg hover:bg-orange-100 transition">
                            <div class="flex-shrink-0 w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-orange-800">Callbacks Due ({{ $stats['callbacks_due'] }})</p>
                                <p class="text-xs text-orange-600">Shipments with scheduled callbacks that are due now</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
