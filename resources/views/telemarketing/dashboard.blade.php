<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Dashboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Top Stats Cards --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                <x-stat-card title="Total Assigned" :value="$stats['total_assigned']" color="indigo" />
                <x-stat-card title="Pending" :value="$stats['pending_in_queue']" color="yellow" />
                <x-stat-card title="Calls Today" :value="$stats['calls_today']" color="blue" />
                <x-stat-card title="Confirmed Today" :value="$stats['confirmed_today']" color="green" />
                <x-stat-card title="Callbacks Due" :value="$stats['callbacks_due']" color="orange" />
                <x-stat-card title="Never Contacted" :value="$stats['never_contacted']" color="purple" />
            </div>

            {{-- Daily Progress Bar --}}
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-semibold text-gray-800">Today's Progress</h3>
                    <span class="text-sm text-gray-500">{{ $stats['calls_today'] }} calls made</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-4">
                    <div class="h-4 rounded-full transition-all duration-500 {{ $stats['progress_percent'] >= 80 ? 'bg-green-500' : ($stats['progress_percent'] >= 50 ? 'bg-blue-500' : 'bg-yellow-500') }}"
                         style="width: {{ $stats['progress_percent'] }}%"></div>
                </div>
                <div class="flex items-center justify-between mt-2">
                    <span class="text-xs text-gray-500">{{ $stats['progress_percent'] }}% of daily queue</span>
                    <span class="text-xs text-gray-500">{{ $stats['pending_in_queue'] }} remaining</span>
                </div>
            </div>

            {{-- Performance Summary Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {{-- Confirmation Rate --}}
                <div class="bg-white shadow rounded-lg p-6 text-center">
                    <div class="text-3xl font-bold {{ $stats['confirmation_rate'] >= 50 ? 'text-green-600' : ($stats['confirmation_rate'] >= 30 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ $stats['confirmation_rate'] }}%
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Confirmation Rate Today</p>
                    <p class="text-xs text-gray-400">{{ $stats['confirmed_today'] }} confirmed / {{ $stats['calls_today'] }} calls</p>
                </div>

                {{-- Average Calls Per Day --}}
                <div class="bg-white shadow rounded-lg p-6 text-center">
                    <div class="text-3xl font-bold text-blue-600">
                        {{ $stats['avg_calls_per_day'] }}
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Avg. Calls Per Day</p>
                    <p class="text-xs text-gray-400">{{ $stats['total_calls_this_week'] }} calls this week</p>
                </div>

                {{-- Completed Today --}}
                <div class="bg-white shadow rounded-lg p-6 text-center">
                    <div class="text-3xl font-bold text-green-600">
                        {{ $stats['completed_today'] }}
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Completed Today</p>
                    <p class="text-xs text-gray-400">Final dispositions logged</p>
                </div>
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
