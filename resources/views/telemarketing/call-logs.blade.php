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

            {{-- Logs Table --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agent</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waybill</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Consignee</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone Called</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Disposition</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Attempt</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Duration</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Recording</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($logs as $log)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $log->created_at->format('M d, H:i') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $log->user?->name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-900">
                                        <a href="{{ route('telemarketing.call', $log->shipment) }}" class="text-indigo-600 hover:text-indigo-900">{{ $log->shipment?->waybill_no ?? '-' }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ Str::limit($log->shipment?->consignee_name, 20) ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ $log->phone_called ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <x-badge :color="$log->disposition?->color ?? 'gray'">{{ $log->disposition?->name ?? 'N/A' }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-600">#{{ $log->attempt_no }}</td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-600">
                                        {{ $log->call_duration_seconds ? gmdate('i:s', $log->call_duration_seconds) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        @if($log->hasRecording())
                                            <button onclick="toggleAudio(this, '{{ $log->getRecordingPlaybackUrl() }}')" class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 text-xs rounded-md hover:bg-green-200 transition" title="Play recording">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>
                                                Play
                                            </button>
                                        @else
                                            <span class="text-gray-400 text-xs">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ Str::limit($log->notes, 40) ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="px-4 py-8 text-center text-sm text-gray-500">No call logs found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t">{{ $logs->links() }}</div>
            </div>

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
