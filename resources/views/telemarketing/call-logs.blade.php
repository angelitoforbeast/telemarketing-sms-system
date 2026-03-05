<x-app-layout>
    <x-slot name="title">Call Logs</x-slot>
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
                            @foreach($logs as $log)
                                {{-- Call attempt row --}}
                                <div class="border-b border-gray-200 last:border-b-0">
                                    <div class="px-5 py-3 {{ $log->status === 'draft' ? 'bg-yellow-50' : 'bg-white' }}">
                                        <div class="flex items-center justify-between">
                                            {{-- Left: call info --}}
                                            <div class="flex items-center space-x-6 flex-1 min-w-0">
                                                <span class="text-sm font-semibold text-gray-500 w-8">#{{ $log->attempt_no ?? '-' }}</span>
                                                <span class="text-sm text-gray-500 w-36">{{ $log->created_at->format('M d, Y H:i') }}</span>
                                                <span class="text-sm font-medium text-gray-900 w-28">{{ $log->user?->name ?? '-' }}</span>
                                                <span class="text-sm font-mono text-gray-600 w-28">{{ $log->phone_called ?? '-' }}</span>
                                                <span class="text-sm w-40">
                                                    @if($log->disposition)
                                                        <x-badge :color="$log->disposition->color ?? 'gray'">{{ $log->disposition->name }}</x-badge>
                                                    @elseif($log->status === 'draft')
                                                        <span class="text-yellow-600 text-xs italic">Pending save...</span>
                                                    @else
                                                        <span class="text-gray-400 text-xs">-</span>
                                                    @endif
                                                </span>
                                                <span class="text-sm text-center w-20">
                                                    @if($log->status === 'draft')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            <svg class="w-2.5 h-2.5 mr-1 animate-pulse" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                            Live
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            <svg class="w-2.5 h-2.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                            Done
                                                        </span>
                                                    @endif
                                                </span>
                                                <span class="text-sm text-gray-600 w-14 text-center">{{ $log->call_duration_seconds ? gmdate('i:s', $log->call_duration_seconds) : '-' }}</span>
                                            </div>

                                            {{-- Right: action buttons --}}
                                            <div class="flex items-center gap-2 ml-4 flex-shrink-0">
                                                @if($log->hasRecording())
                                                    <button onclick="event.stopPropagation(); toggleAudio(this, '{{ $log->getRecordingPlaybackUrl() }}')" class="inline-flex items-center px-2.5 py-1.5 bg-green-100 text-green-700 text-xs font-medium rounded-md hover:bg-green-200 transition" title="Play recording">
                                                        <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>
                                                        Play
                                                    </button>

                                                    @if($log->ai_analyzed_at)
                                                        <button onclick="event.stopPropagation(); reanalyzeCall(this, {{ $log->id }})" class="inline-flex items-center px-2.5 py-1.5 bg-gray-100 text-gray-500 text-xs font-medium rounded-md hover:bg-indigo-100 hover:text-indigo-600 transition" title="Re-analyze this call">
                                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                            Re-analyze
                                                        </button>
                                                    @else
                                                        <button onclick="event.stopPropagation(); analyzeCall(this, {{ $log->id }})" class="inline-flex items-center px-2.5 py-1.5 bg-indigo-100 text-indigo-700 text-xs font-medium rounded-md hover:bg-indigo-200 transition analyze-btn" title="Analyze with AI">
                                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                                            Analyze
                                                        </button>
                                                    @endif
                                                @else
                                                    @if($log->status === 'draft')
                                                        <span class="text-yellow-500 text-xs italic">Recording in progress...</span>
                                                    @else
                                                        <span class="text-gray-400 text-xs">No recording</span>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Notes (if any) --}}
                                        @if($log->notes)
                                            <div class="mt-2 ml-14">
                                                <p class="text-xs text-gray-500"><span class="font-medium text-gray-600">Notes:</span> {{ $log->notes }}</p>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- AI Analysis Results (inline, always visible if analyzed) --}}
                                    <div id="ai-result-{{ $log->id }}">
                                        @if($log->ai_analyzed_at)
                                            @include('telemarketing.partials.ai-result', ['log' => $log])
                                        @endif
                                    </div>
                                </div>
                            @endforeach
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

            {{-- AI Analysis Loading Modal --}}
            <div id="ai-loading-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
                <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full mx-4 text-center">
                    <div class="mb-4 flex justify-center">
                        <svg class="w-8 h-8 text-indigo-600 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">Analyzing Call</h3>
                    <p class="text-sm text-gray-500 mb-3">Transcribing audio and generating AI summary...</p>
                    <p class="text-xs text-gray-400">This may take 15-30 seconds</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="fixed top-4 right-4 z-50 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-lg" role="alert">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                {{ session('success') }}
            </div>
        </div>
    @endif
    @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 8000)" class="fixed top-4 right-4 z-50 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg shadow-lg" role="alert">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                {{ session('error') }}
            </div>
        </div>
    @endif

    @push('scripts')
    <script>
        function analyzeCall(btn, logId) {
            // Show loading modal
            document.getElementById('ai-loading-modal').classList.remove('hidden');

            // Disable button and show spinner
            btn.disabled = true;
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<svg class="w-3.5 h-3.5 mr-1 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Analyzing...';

            fetch('/telemarketing/analyze-call/' + logId, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('ai-loading-modal').classList.add('hidden');

                if (data.success) {
                    // Inject AI results directly into the page
                    const resultContainer = document.getElementById('ai-result-' + logId);
                    if (resultContainer && data.html) {
                        resultContainer.innerHTML = data.html;
                    }

                    // Change Analyze button to Re-analyze
                    btn.className = 'inline-flex items-center px-2.5 py-1.5 bg-gray-100 text-gray-500 text-xs font-medium rounded-md hover:bg-indigo-100 hover:text-indigo-600 transition';
                    btn.title = 'Re-analyze this call';
                    btn.innerHTML = '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>Re-analyze';
                    btn.disabled = false;
                    btn.onclick = function(e) { e.stopPropagation(); reanalyzeCall(btn, logId); };
                } else {
                    alert('Analysis failed: ' + (data.message || 'Unknown error'));
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            })
            .catch(error => {
                document.getElementById('ai-loading-modal').classList.add('hidden');
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = origHtml;
            });
        }

        function reanalyzeCall(btn, logId) {
            if (!confirm('Re-analyze this call? This will replace the existing analysis.')) return;
            analyzeCall(btn, logId);
        }

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
                btn.innerHTML = '<svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>Play';
                return;
            }

            if (currentPlayingBtn) {
                currentPlayingBtn.innerHTML = '<svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>Play';
            }

            player.src = url;
            player.play();
            container.classList.remove('hidden');
            currentPlayingBtn = btn;
            btn.innerHTML = '<svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>Pause';

            player.onended = function() {
                btn.innerHTML = '<svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>Play';
                currentPlayingBtn = null;
            };
        }

        function closeAudioPlayer() {
            const player = document.getElementById('global-audio-player');
            player.pause();
            document.getElementById('audio-player-container').classList.add('hidden');
            if (currentPlayingBtn) {
                currentPlayingBtn.innerHTML = '<svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>Play';
                currentPlayingBtn = null;
            }
        }
    </script>
    @endpush
</x-app-layout>
