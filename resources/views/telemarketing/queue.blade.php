<x-app-layout>
    <x-slot name="title">Telemarketing Queue</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                @if(auth()->user()->hasRole('Telemarketer'))
                    My Call Queue
                @else
                    Call Queue {{ isset($telemarketers) && request('telemarketer_id') ? '- ' . $telemarketers->firstWhere('id', request('telemarketer_id'))?->name : '(All Agents)' }}
                @endif
            </h2>
            <div class="flex space-x-2">
                @if(auth()->user()->hasRole('Telemarketer'))
                    <a href="{{ route('telemarketing.next-call') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition shadow-sm">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        Next Call
                    </a>
                @endif
                <a href="{{ route('telemarketing.dashboard') }}" class="inline-flex items-center px-3 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition">
                    Dashboard
                </a>
            </div>
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

            {{-- Filters --}}
            <div class="bg-white shadow rounded-lg p-4 mb-4">
                <form method="GET" action="{{ route('telemarketing.queue') }}" class="flex flex-wrap gap-3 items-end">
                    {{-- Manager: telemarketer filter --}}
                    @if(isset($telemarketers))
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Agent</label>
                            <select name="telemarketer_id" class="text-sm border-gray-300 rounded-md shadow-sm">
                                <option value="">All Agents</option>
                                @foreach($telemarketers as $tm)
                                    <option value="{{ $tm->id }}" {{ request('telemarketer_id') == $tm->id ? 'selected' : '' }}>{{ $tm->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <select name="status_id" class="text-sm border-gray-300 rounded-md shadow-sm">
                            <option value="">All Statuses</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->id }}" {{ request('status_id') == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Courier</label>
                        <select name="courier" class="text-sm border-gray-300 rounded-md shadow-sm">
                            <option value="">All</option>
                            <option value="jnt" {{ request('courier') === 'jnt' ? 'selected' : '' }}>JNT</option>
                            <option value="flash" {{ request('courier') === 'flash' ? 'selected' : '' }}>Flash</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Waybill, name, phone..." class="text-sm border-gray-300 rounded-md shadow-sm w-48">
                    </div>

                    <div class="flex items-center space-x-3">
                        <label class="flex items-center text-sm text-gray-600">
                            <input type="checkbox" name="callbacks_only" value="1" {{ request('callbacks_only') ? 'checked' : '' }} class="mr-1 rounded border-gray-300 text-indigo-600">
                            Callbacks Due
                        </label>
                        <label class="flex items-center text-sm text-gray-600">
                            <input type="checkbox" name="never_contacted" value="1" {{ request('never_contacted') ? 'checked' : '' }} class="mr-1 rounded border-gray-300 text-indigo-600">
                            Never Contacted
                        </label>
                    </div>

                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition">Filter</button>
                    <a href="{{ route('telemarketing.queue') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-md hover:bg-gray-300 transition">Reset</a>
                </form>
            </div>

            {{-- Queue Table --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waybill</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Courier</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Consignee</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Disposition</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Attempts</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Contact</th>
                                @if(!auth()->user()->hasRole('Telemarketer'))
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agent</th>
                                @endif
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($shipments as $shipment)
                                @php
                                    $isCallbackDue = $shipment->callback_scheduled_at && $shipment->callback_scheduled_at->isPast();
                                    $isNeverContacted = $shipment->telemarketing_attempt_count === 0;
                                @endphp
                                <tr class="hover:bg-gray-50 {{ $isCallbackDue ? 'bg-orange-50' : ($isNeverContacted ? 'bg-blue-50' : '') }}">
                                    <td class="px-4 py-3 text-sm">
                                        @if($isCallbackDue)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800" title="Callback due: {{ $shipment->callback_scheduled_at->format('M d, H:i') }}">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>
                                                CB
                                            </span>
                                        @elseif($isNeverContacted)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">NEW</span>
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $shipment->waybill_no }}</td>
                                    <td class="px-4 py-3 text-sm"><x-badge :color="$shipment->courier === 'jnt' ? 'red' : 'orange'">{{ strtoupper($shipment->courier) }}</x-badge></td>
                                    <td class="px-4 py-3 text-sm"><x-badge color="blue">{{ $shipment->status?->name ?? 'Unknown' }}</x-badge></td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ Str::limit($shipment->consignee_name, 20) }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        @if($shipment->consignee_phone_1)
                                            <a href="tel:{{ $shipment->consignee_phone_1 }}" class="font-mono text-green-700 hover:text-green-900 hover:underline" title="Click to call">
                                                <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                                {{ $shipment->consignee_phone_1 }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        @if($shipment->lastDisposition)
                                            <x-badge :color="$shipment->lastDisposition->color ?? 'gray'">{{ $shipment->lastDisposition->name }}</x-badge>
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-600">{{ $shipment->telemarketing_attempt_count }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $shipment->last_contacted_at?->format('M d, H:i') ?? 'Never' }}</td>
                                    @if(!auth()->user()->hasRole('Telemarketer'))
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $shipment->assignedTo?->name ?? '-' }}</td>
                                    @endif
                                    <td class="px-4 py-3 text-sm">
                                        <a href="{{ route('telemarketing.call', $shipment) }}" class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs rounded-md hover:bg-green-700 transition shadow-sm">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                            Call
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ auth()->user()->hasRole('Telemarketer') ? 10 : 11 }}" class="px-4 py-8 text-center text-sm text-gray-500">
                                    @if(auth()->user()->hasRole('Telemarketer'))
                                        No shipments in your queue. Ask your manager to assign shipments to you.
                                    @else
                                        No shipments found matching the filters.
                                    @endif
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t">{{ $shipments->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
