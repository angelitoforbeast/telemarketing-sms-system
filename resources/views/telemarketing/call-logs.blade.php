<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Call Logs</h2>
            <a href="{{ route('telemarketing.dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md bg-gray-100 hover:bg-gray-200 transition">&larr; Back to Dashboard</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Filters --}}
            <div class="bg-white shadow rounded-lg p-4 mb-4">
                <form method="GET" action="{{ route('telemarketing.call-logs') }}" class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <select name="status" class="text-sm border-gray-300 rounded-md shadow-sm">
                            <option value="all" {{ request('status', 'all') == 'all' ? 'selected' : '' }}>All Status</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>In-Progress (Draft)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Agent</label>
                        <select name="telemarketer_id" class="text-sm border-gray-300 rounded-md shadow-sm">
                            <option value="">All Agents</option>
                            @foreach($telemarketers as $tm)
                                <option value="{{ $tm->id }}" {{ request('telemarketer_id') == $tm->id ? 'selected' : '' }}>{{ $tm->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Disposition</label>
                        <select name="disposition_id" class="text-sm border-gray-300 rounded-md shadow-sm">
                            <option value="">All Dispositions</option>
                            @foreach($dispositions as $d)
                                <option value="{{ $d->id }}" {{ request('disposition_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="text-sm border-gray-300 rounded-md shadow-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="text-sm border-gray-300 rounded-md shadow-sm">
                    </div>

                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition">Filter</button>
                    <a href="{{ route('telemarketing.call-logs') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-md hover:bg-gray-300 transition">Reset</a>
                </form>
            </div>

            {{-- Shipment-grouped Logs --}}
            <div class="space-y-3">
                @forelse($shipments as $shipment)
                    @php
                        $logs = $allLogs->get($shipment->id, collect());
                        $hasInProgress = $logs->where('status', 'draft')->count() > 0;
                        $recordingCount = $logs->filter(fn($l) => $l->hasRecording())->count();
                    @endphp
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        {{-- Main row: one per waybill --}}
                        <div class="px-5 py-4 cursor-pointer hover:bg-gray-50 transition" onclick="toggleHistory('history-{{ $shipment->id }}', this)">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4 flex-1 min-w-0">
                                    {{-- Expand arrow --}}
                                    <svg class="w-5 h-5 text-gray-400 transform transition-transform expand-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>

                                    {{-- Waybill --}}
                                    <div class="min-w-0">
                                        <a href="{{ route('telemarketing.call', $shipment) }}" class="text-sm font-mono font-semibold text-indigo-600 hover:text-indigo-900" onclick="event.stopPropagation()">{{ $shipment->waybill_no }}</a>
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $shipment->courier }}</p>
                                    </div>

                                    {{-- Consignee --}}
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $shipment->consignee_name }}</p>
                                        <p class="text-xs text-gray-500 font-mono">{{ $shipment->consignee_phone_1 }}</p>
                                    </div>

                                    {{-- COD --}}
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-gray-900">₱{{ number_format($shipment->cod_amount, 2) }}</p>
                                        <p class="text-xs text-gray-500">COD</p>
                                    </div>

                                    {{-- Last Disposition --}}
                                    <div>
                                        @if($shipment->lastDisposition)
                                            <x-badge :color="$shipment->lastDisposition->color ?? 'gray'">{{ $shipment->lastDisposition->name }}</x-badge>
                                        @elseif($hasInProgress)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <svg class="w-3 h-3 mr-1 animate-pulse" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                In-Progress
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Stats badges --}}
                                <div class="flex items-center space-x-3 ml-4 flex-shrink-0">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800" title="Total calls">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                        {{ $logs->count() }} {{ Str::plural('call', $logs->count()) }}
                                    </span>
                                    @if($recordingCount > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800" title="Recordings">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
                                            {{ $recordingCount }} {{ Str::plural('recording', $recordingCount) }}
                                        </span>
                                    @endif
                                    @if($hasInProgress)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 animate-pulse">
                                            Live
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Expandable call history --}}
                        <div id="history-{{ $shipment->id }}" class="hidden border-t bg-gray-50">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 uppercase">Attempt</th>
                                        <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                                        <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 uppercase">Caller</th>
                                        <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 uppercase">Phone Called</th>
                                        <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 uppercase">Disposition</th>
                                        <th class="px-5 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-5 py-2 text-center text-xs font-medium text-gray-500 uppercase">Duration</th>
                                        <th class="px-5 py-2 text-center text-xs font-medium text-gray-500 uppercase">Recording</th>
                                        <th class="px-5 py-2 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($logs as $log)
                                        <tr class="{{ $log->status === 'draft' ? 'bg-yellow-50' : 'bg-white' }} hover:bg-gray-100">
                                            <td class="px-5 py-2.5 text-sm text-gray-600 font-medium">#{{ $log->attempt_no ?? '-' }}</td>
                                            <td class="px-5 py-2.5 text-sm text-gray-500">{{ $log->created_at->format('M d, Y H:i') }}</td>
                                            <td class="px-5 py-2.5 text-sm text-gray-900 font-medium">{{ $log->user?->name ?? '-' }}</td>
                                            <td class="px-5 py-2.5 text-sm font-mono text-gray-600">{{ $log->phone_called ?? '-' }}</td>
                                            <td class="px-5 py-2.5 text-sm">
                                                @if($log->disposition)
                                                    <x-badge :color="$log->disposition->color ?? 'gray'">{{ $log->disposition->name }}</x-badge>
                                                @elseif($log->status === 'draft')
                                                    <span class="text-yellow-600 text-xs italic">Pending save...</span>
                                                @else
                                                    <span class="text-gray-400 text-xs">N/A</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-2.5 text-sm text-center">
                                                @if($log->status === 'draft')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        <svg class="w-2.5 h-2.5 mr-1 animate-pulse" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                        In-Progress
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <svg class="w-2.5 h-2.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                        Done
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-2.5 text-sm text-center text-gray-600">
                                                {{ $log->call_duration_seconds ? gmdate('i:s', $log->call_duration_seconds) : '-' }}
                                            </td>
                                            <td class="px-5 py-2.5 text-sm text-center">
                                                @if($log->hasRecording())
                                                    <button onclick="event.stopPropagation(); toggleAudio(this, '{{ $log->getRecordingPlaybackUrl() }}')" class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 text-xs rounded-md hover:bg-green-200 transition" title="Play recording">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>
                                                        Play
                                                    </button>
                                                @else
                                                    @if($log->status === 'draft')
                                                        <span class="text-yellow-500 text-xs italic">Waiting...</span>
                                                    @else
                                                        <span class="text-gray-400 text-xs">-</span>
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="px-5 py-2.5 text-sm text-gray-600">{{ Str::limit($log->notes, 50) ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <div class="bg-white shadow rounded-lg p-8 text-center text-sm text-gray-500">
                        No call logs found.
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            <div class="mt-4">{{ $shipments->links() }}</div>

            {{-- Hidden audio player --}}
            <div id="audio-player-container" class="hidden fixed bottom-4 right-4 bg-white shadow-xl rounded-lg p-4 border z-50">
                <div class="flex items-center space-x-3">
                    <audio id="global-audio-player" controls class="h-8"></audio>
                    <button onclick="closeAudioPlayer()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function toggleHistory(id, row) {
            const el = document.getElementById(id);
            const arrow = row.querySelector('.expand-arrow');
            if (el.classList.contains('hidden')) {
                el.classList.remove('hidden');
                arrow.classList.add('rotate-90');
            } else {
                el.classList.add('hidden');
                arrow.classList.remove('rotate-90');
            }
        }

        let currentPlayingBtn = null;

        function toggleAudio(btn, url) {
            const player = document.getElementById('global-audio-player');
            const container = document.getElementById('audio-player-container');

            if (currentPlayingBtn === btn && !player.paused) {
                player.pause();
                btn.innerHTML = '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>Play';
                return;
            }

            if (currentPlayingBtn) {
                currentPlayingBtn.innerHTML = '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>Play';
            }

            player.src = url;
            player.play();
            container.classList.remove('hidden');
            currentPlayingBtn = btn;
            btn.innerHTML = '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>Pause';

            player.onended = function() {
                btn.innerHTML = '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>Play';
                currentPlayingBtn = null;
            };
        }

        function closeAudioPlayer() {
            const player = document.getElementById('global-audio-player');
            player.pause();
            document.getElementById('audio-player-container').classList.add('hidden');
            if (currentPlayingBtn) {
                currentPlayingBtn.innerHTML = '<svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>Play';
                currentPlayingBtn = null;
            }
        }
    </script>
    @endpush
</x-app-layout>
