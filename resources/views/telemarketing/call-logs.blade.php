<x-app-layout>
    <x-slot name="title">Call Logs</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Call Logs</h2>
            <a href="{{ route('telemarketing.dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md bg-gray-100 hover:bg-gray-200 transition">&larr; Back to Dashboard</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Stats Cards --}}
            @php
                $totalLogs = $allLogs->flatten()->count();
                $totalRecordings = $allLogs->flatten()->filter(fn($l) => $l->hasRecording())->count();
                $totalAnalyzed = $allLogs->flatten()->filter(fn($l) => $l->ai_analyzed_at)->count();
                $totalUnanalyzed = $totalRecordings - $totalAnalyzed;
            @endphp
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase">Total Calls</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $totalLogs }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase">Recordings</p>
                    <p class="text-2xl font-bold text-green-600">{{ $totalRecordings }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase">Analyzed</p>
                    <p class="text-2xl font-bold text-indigo-600">{{ $totalAnalyzed }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase">Unanalyzed</p>
                    <p class="text-2xl font-bold text-orange-500">{{ $totalUnanalyzed }}</p>
                </div>
            </div>

            {{-- Filters + Analyze All --}}
            <div class="bg-white shadow rounded-lg p-4 mb-4">
                <div class="flex flex-wrap gap-3 items-end justify-between">
                    <form method="GET" action="{{ route('telemarketing.call-logs') }}" class="flex flex-wrap gap-3 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                            <select name="status" class="text-sm border-gray-300 rounded-md shadow-sm">
                                <option value="all" {{ request('status', 'all') == 'all' ? 'selected' : '' }}>All Status</option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>In-Progress</option>
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

                    {{-- Analyze All Button (CEO/Owner only) --}}
                    @if(auth()->user()->hasRole('Company Owner') || auth()->user()->hasRole('CEO') || auth()->user()->hasRole('Platform Admin'))
                        <button onclick="analyzeAllUnanalyzed()" id="analyze-all-btn" class="px-4 py-2 bg-purple-600 text-white text-sm rounded-md hover:bg-purple-700 transition flex items-center gap-2 whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                            Analyze All Unanalyzed (<span id="unanalyzed-count">{{ $totalUnanalyzed }}</span>)
                        </button>
                    @endif
                </div>
            </div>

            {{-- Call Logs Table --}}
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-8"></th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waybill</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">COD</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Disposition</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">AI Summary</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Sentiment</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Intent</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issues</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Calls</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($shipments as $shipment)
                                @php
                                    $logs = $allLogs->get($shipment->id, collect());
                                    $latestLog = $logs->first();
                                    $hasInProgress = $logs->where('status', 'draft')->count() > 0;
                                    $recordingCount = $logs->filter(fn($l) => $l->hasRecording())->count();
                                    $analyzedLog = $logs->firstWhere(fn($l) => $l->ai_analyzed_at !== null);
                                @endphp
                                {{-- Main Row --}}
                                <tr class="hover:bg-gray-50 cursor-pointer transition" onclick="toggleExpand('expand-{{ $shipment->id }}', this)">
                                    {{-- Expand Arrow --}}
                                    <td class="px-3 py-3">
                                        <svg class="w-4 h-4 text-gray-400 transform transition-transform expand-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </td>
                                    {{-- Waybill --}}
                                    <td class="px-3 py-3">
                                        <a href="{{ route('telemarketing.call', $shipment) }}" class="text-sm font-mono font-semibold text-indigo-600 hover:text-indigo-900" onclick="event.stopPropagation()">{{ $shipment->waybill_no }}</a>
                                    </td>
                                    {{-- Customer --}}
                                    <td class="px-3 py-3">
                                        <p class="text-sm font-medium text-gray-900 truncate max-w-[140px]">{{ $shipment->consignee_name }}</p>
                                        <p class="text-xs text-gray-500 font-mono">{{ $shipment->consignee_phone_1 }}</p>
                                    </td>
                                    {{-- COD --}}
                                    <td class="px-3 py-3 text-right">
                                        <span class="text-sm font-semibold text-gray-900">₱{{ number_format($shipment->cod_amount, 2) }}</span>
                                    </td>
                                    {{-- Disposition --}}
                                    <td class="px-3 py-3">
                                        @if($shipment->lastDisposition)
                                            <x-badge :color="$shipment->lastDisposition->color ?? 'gray'">{{ $shipment->lastDisposition->name }}</x-badge>
                                        @elseif($hasInProgress)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <svg class="w-2.5 h-2.5 mr-1 animate-pulse" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                Live
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </td>
                                    {{-- AI Summary --}}
                                    <td class="px-3 py-3">
                                        @if($analyzedLog && $analyzedLog->ai_summary)
                                            <p class="text-xs text-gray-600 truncate max-w-[200px]" title="{{ $analyzedLog->ai_summary }}">{{ $analyzedLog->ai_summary }}</p>
                                        @else
                                            <span class="text-xs text-gray-300 italic">-</span>
                                        @endif
                                    </td>
                                    {{-- Sentiment --}}
                                    <td class="px-3 py-3 text-center">
                                        @if($analyzedLog && $analyzedLog->ai_sentiment)
                                            @php
                                                $sentimentColors = [
                                                    'positive' => 'bg-green-100 text-green-700',
                                                    'neutral' => 'bg-gray-100 text-gray-600',
                                                    'negative' => 'bg-red-100 text-red-700',
                                                ];
                                                $sentimentIcons = [
                                                    'positive' => '&#9650;',
                                                    'neutral' => '&#9679;',
                                                    'negative' => '&#9660;',
                                                ];
                                            @endphp
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $sentimentColors[$analyzedLog->ai_sentiment] ?? 'bg-gray-100 text-gray-600' }}">
                                                {!! $sentimentIcons[$analyzedLog->ai_sentiment] ?? '' !!} {{ ucfirst($analyzedLog->ai_sentiment) }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-300">-</span>
                                        @endif
                                    </td>
                                    {{-- Customer Intent --}}
                                    <td class="px-3 py-3 text-center">
                                        @if($analyzedLog && $analyzedLog->ai_customer_intent)
                                            @php
                                                $intentColors = [
                                                    'reorder' => 'bg-green-100 text-green-700',
                                                    'acceptance' => 'bg-emerald-100 text-emerald-700',
                                                    'inquiry' => 'bg-blue-100 text-blue-700',
                                                    'callback' => 'bg-yellow-100 text-yellow-700',
                                                    'complaint' => 'bg-orange-100 text-orange-700',
                                                    'refusal' => 'bg-red-100 text-red-700',
                                                    'other' => 'bg-gray-100 text-gray-600',
                                                ];
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $intentColors[$analyzedLog->ai_customer_intent] ?? 'bg-gray-100 text-gray-600' }}">
                                                {{ ucfirst($analyzedLog->ai_customer_intent) }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-300">-</span>
                                        @endif
                                    </td>
                                    {{-- Agent Score --}}
                                    <td class="px-3 py-3 text-center">
                                        @if($analyzedLog && $analyzedLog->ai_agent_score)
                                            @php
                                                $score = $analyzedLog->ai_agent_score;
                                                $scoreColor = $score >= 8 ? 'text-green-600 bg-green-50' : ($score >= 5 ? 'text-yellow-600 bg-yellow-50' : 'text-red-600 bg-red-50');
                                            @endphp
                                            <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-bold {{ $scoreColor }}">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                {{ $score }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-300">-</span>
                                        @endif
                                    </td>
                                    {{-- Key Issues --}}
                                    <td class="px-3 py-3">
                                        @if($analyzedLog && $analyzedLog->ai_key_issues && strtolower($analyzedLog->ai_key_issues) !== 'none')
                                            <p class="text-xs text-orange-600 truncate max-w-[150px]" title="{{ $analyzedLog->ai_key_issues }}">{{ $analyzedLog->ai_key_issues }}</p>
                                        @else
                                            <span class="text-xs text-gray-300">-</span>
                                        @endif
                                    </td>
                                    {{-- Calls Count --}}
                                    <td class="px-3 py-3 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">{{ $logs->count() }}</span>
                                            @if($recordingCount > 0)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-50 text-green-700">
                                                    <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
                                                    {{ $recordingCount }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    {{-- Actions --}}
                                    <td class="px-3 py-3 text-center">
                                        @if($latestLog && $latestLog->hasRecording())
                                            <div class="flex items-center justify-center gap-1">
                                                <button onclick="event.stopPropagation(); toggleAudio(this, '{{ $latestLog->getRecordingPlaybackUrl() }}')" class="p-1.5 bg-green-100 text-green-700 rounded hover:bg-green-200 transition" title="Play">
                                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
                                                </button>
                                                @if(!$latestLog->ai_analyzed_at)
                                                    <button onclick="event.stopPropagation(); analyzeCall(this, {{ $latestLog->id }})" class="p-1.5 bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 transition" title="Analyze">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                                    </button>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-300">-</span>
                                        @endif
                                    </td>
                                </tr>

                                {{-- Expandable Row: Call Attempts + Transcriptions --}}
                                <tr id="expand-{{ $shipment->id }}" class="hidden">
                                    <td colspan="12" class="p-0">
                                        <div class="bg-gray-50 border-t border-b border-gray-200">
                                            @foreach($logs as $log)
                                                <div class="border-b border-gray-100 last:border-b-0">
                                                    {{-- Call Attempt Header --}}
                                                    <div class="px-5 py-2.5 flex items-center justify-between {{ $log->status === 'draft' ? 'bg-yellow-50' : 'bg-white' }}">
                                                        <div class="flex items-center gap-4 text-sm">
                                                            <span class="font-semibold text-gray-400 w-6">#{{ $log->attempt_no ?? '-' }}</span>
                                                            <span class="text-gray-500 text-xs">{{ $log->created_at->format('M d, Y H:i') }}</span>
                                                            <span class="font-medium text-gray-700">{{ $log->user?->name ?? '-' }}</span>
                                                            <span class="font-mono text-gray-500 text-xs">{{ $log->phone_called ?? '-' }}</span>
                                                            @if($log->disposition)
                                                                <x-badge :color="$log->disposition->color ?? 'gray'">{{ $log->disposition->name }}</x-badge>
                                                            @elseif($log->status === 'draft')
                                                                <span class="text-yellow-600 text-xs italic">Pending...</span>
                                                            @endif
                                                            @if($log->ai_disposition_id && $log->disposition)
                                                                @php $match = ($log->aiDisposition && $log->aiDisposition->id === $log->disposition->id); @endphp
                                                                @if($match)
                                                                    <span class="text-green-500 text-xs flex items-center gap-0.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>AI Match</span>
                                                                @else
                                                                    <span class="text-red-500 text-xs flex items-center gap-0.5" title="AI suggested: {{ $log->aiDisposition?->name }}"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>AI: {{ $log->aiDisposition?->name }}</span>
                                                                @endif
                                                            @endif
                                                            <span class="text-gray-400 text-xs">{{ $log->call_duration_seconds ? gmdate('i:s', $log->call_duration_seconds) : '-' }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            @if($log->hasRecording())
                                                                <button onclick="event.stopPropagation(); toggleAudio(this, '{{ $log->getRecordingPlaybackUrl() }}')" class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 text-xs rounded hover:bg-green-200 transition">
                                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>Play
                                                                </button>
                                                                @if($log->ai_analyzed_at)
                                                                    <button onclick="event.stopPropagation(); reanalyzeCall(this, {{ $log->id }})" class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-500 text-xs rounded hover:bg-indigo-100 hover:text-indigo-600 transition" title="Re-analyze">
                                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>Re-analyze
                                                                    </button>
                                                                @else
                                                                    <button onclick="event.stopPropagation(); analyzeCall(this, {{ $log->id }})" class="inline-flex items-center px-2 py-1 bg-indigo-100 text-indigo-700 text-xs rounded hover:bg-indigo-200 transition">
                                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>Analyze
                                                                    </button>
                                                                @endif
                                                            @endif
                                                        </div>
                                                    </div>

                                                    {{-- AI Analysis Data (inline, compact) --}}
                                                    @if($log->ai_analyzed_at)
                                                        <div class="px-5 py-2 bg-indigo-50/50 border-t border-indigo-100">
                                                            <div class="flex flex-wrap items-start gap-4 text-xs">
                                                                {{-- Summary --}}
                                                                <div class="flex-1 min-w-[200px]">
                                                                    <span class="font-semibold text-indigo-600">Summary:</span>
                                                                    <span class="text-gray-700">{{ $log->ai_summary }}</span>
                                                                </div>
                                                                {{-- Sentiment --}}
                                                                @if($log->ai_sentiment)
                                                                    <div>
                                                                        <span class="font-semibold text-gray-500">Sentiment:</span>
                                                                        @php
                                                                            $sc = ['positive' => 'text-green-600', 'neutral' => 'text-gray-600', 'negative' => 'text-red-600'];
                                                                        @endphp
                                                                        <span class="font-medium {{ $sc[$log->ai_sentiment] ?? 'text-gray-600' }}">{{ ucfirst($log->ai_sentiment) }}</span>
                                                                    </div>
                                                                @endif
                                                                {{-- Intent --}}
                                                                @if($log->ai_customer_intent)
                                                                    <div>
                                                                        <span class="font-semibold text-gray-500">Intent:</span>
                                                                        <span class="text-gray-700">{{ ucfirst($log->ai_customer_intent) }}</span>
                                                                    </div>
                                                                @endif
                                                                {{-- Score --}}
                                                                @if($log->ai_agent_score)
                                                                    <div>
                                                                        <span class="font-semibold text-gray-500">Score:</span>
                                                                        <span class="font-bold {{ $log->ai_agent_score >= 8 ? 'text-green-600' : ($log->ai_agent_score >= 5 ? 'text-yellow-600' : 'text-red-600') }}">{{ $log->ai_agent_score }}/10</span>
                                                                    </div>
                                                                @endif
                                                                {{-- Key Issues --}}
                                                                @if($log->ai_key_issues && strtolower($log->ai_key_issues) !== 'none')
                                                                    <div>
                                                                        <span class="font-semibold text-orange-500">Issues:</span>
                                                                        <span class="text-gray-700">{{ $log->ai_key_issues }}</span>
                                                                    </div>
                                                                @endif
                                                                {{-- Action Items --}}
                                                                @if($log->ai_action_items && strtolower($log->ai_action_items) !== 'none')
                                                                    <div>
                                                                        <span class="font-semibold text-blue-500">Action:</span>
                                                                        <span class="text-gray-700">{{ $log->ai_action_items }}</span>
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            {{-- Transcription Toggle --}}
                                                            @if($log->transcription)
                                                                <div class="mt-2">
                                                                    <button onclick="event.stopPropagation(); toggleTranscription('trans-{{ $log->id }}')" class="text-xs text-purple-600 hover:text-purple-800 font-medium flex items-center gap-1">
                                                                        <svg class="w-3 h-3 transform transition-transform" id="trans-arrow-{{ $log->id }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                                        Show Transcription
                                                                    </button>
                                                                    <div id="trans-{{ $log->id }}" class="hidden mt-2 text-sm text-gray-600 bg-white rounded-lg p-3 border border-gray-200 max-h-72 overflow-y-auto leading-relaxed">
                                                                        @foreach(explode("\n", $log->transcription) as $line)
                                                                            @php $line = trim($line); @endphp
                                                                            @if(empty($line)) @continue @endif
                                                                            <div class="mb-1 last:mb-0">
                                                                                @if(str_starts_with($line, 'AGENT:'))
                                                                                    <span class="font-semibold text-indigo-600">AGENT:</span><span class="text-gray-700">{{ substr($line, 6) }}</span>
                                                                                @elseif(str_starts_with($line, 'CUSTOMER:'))
                                                                                    <span class="font-semibold text-green-600">CUSTOMER:</span><span class="text-gray-700">{{ substr($line, 9) }}</span>
                                                                                @elseif(str_starts_with($line, 'UNKNOWN:'))
                                                                                    <span class="font-semibold text-gray-500">UNKNOWN:</span><span class="text-gray-700">{{ substr($line, 8) }}</span>
                                                                                @else
                                                                                    <span class="text-gray-700">{{ $line }}</span>
                                                                                @endif
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    {{-- Notes --}}
                                                    @if($log->notes)
                                                        <div class="px-5 py-1.5 bg-gray-50 border-t border-gray-100">
                                                            <p class="text-xs text-gray-500"><span class="font-medium text-gray-600">Notes:</span> {{ $log->notes }}</p>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="px-6 py-8 text-center text-sm text-gray-500">No call logs found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">{{ $shipments->links() }}</div>

            {{-- Hidden audio player --}}
            <div id="audio-player-container" class="hidden fixed bottom-4 right-4 bg-white shadow-xl rounded-lg p-4 border z-50">
                <div class="flex items-center space-x-3">
                    <audio id="global-audio-player" controls class="h-8"></audio>
                    <button onclick="closeAudioPlayer()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
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
                    <h3 class="text-lg font-semibold text-gray-800 mb-1" id="ai-modal-title">Analyzing Call</h3>
                    <p class="text-sm text-gray-500 mb-3" id="ai-modal-desc">Transcribing audio and generating AI analysis...</p>
                    <p class="text-xs text-gray-400" id="ai-modal-sub">This may take 15-30 seconds</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="fixed top-4 right-4 z-50 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                {{ session('success') }}
            </div>
        </div>
    @endif
    @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 8000)" class="fixed top-4 right-4 z-50 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg shadow-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                {{ session('error') }}
            </div>
        </div>
    @endif

    @push('scripts')
    <script>
        function toggleExpand(id, row) {
            const el = document.getElementById(id);
            const arrow = row.querySelector('.expand-arrow');
            if (el.classList.contains('hidden')) {
                el.classList.remove('hidden');
                if (arrow) arrow.classList.add('rotate-90');
            } else {
                el.classList.add('hidden');
                if (arrow) arrow.classList.remove('rotate-90');
            }
        }

        function toggleTranscription(id) {
            const el = document.getElementById(id);
            const arrow = document.getElementById('trans-arrow-' + id.replace('trans-', ''));
            if (el.classList.contains('hidden')) {
                el.classList.remove('hidden');
                if (arrow) arrow.classList.add('rotate-90');
            } else {
                el.classList.add('hidden');
                if (arrow) arrow.classList.remove('rotate-90');
            }
        }

        function analyzeCall(btn, logId) {
            document.getElementById('ai-loading-modal').classList.remove('hidden');
            document.getElementById('ai-modal-title').textContent = 'Analyzing Call';
            document.getElementById('ai-modal-desc').textContent = 'Transcribing audio and generating AI analysis...';
            document.getElementById('ai-modal-sub').textContent = 'This may take 15-30 seconds';

            btn.disabled = true;
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>';

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
                    // Reload page to show updated data
                    window.location.reload();
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

        function analyzeAllUnanalyzed() {
            const count = document.getElementById('unanalyzed-count').textContent;
            if (!confirm('Analyze all ' + count + ' unanalyzed recordings? This will queue them for background processing.')) return;

            const btn = document.getElementById('analyze-all-btn');
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Queueing...';

            fetch('/telemarketing/analyze-all-unanalyzed', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> ' + data.queued + ' queued';
                    btn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                    btn.classList.add('bg-green-600');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    alert('Failed: ' + (data.message || 'Unknown error'));
                    btn.disabled = false;
                    btn.innerHTML = 'Analyze All Unanalyzed (' + count + ')';
                }
            })
            .catch(error => {
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = 'Analyze All Unanalyzed (' + count + ')';
            });
        }

        let currentPlayingBtn = null;

        function toggleAudio(btn, url) {
            const player = document.getElementById('global-audio-player');
            const container = document.getElementById('audio-player-container');

            if (currentPlayingBtn === btn && !player.paused) {
                player.pause();
                return;
            }

            player.src = url;
            player.play();
            container.classList.remove('hidden');
            currentPlayingBtn = btn;

            player.onended = function() {
                currentPlayingBtn = null;
            };
        }

        function closeAudioPlayer() {
            const player = document.getElementById('global-audio-player');
            player.pause();
            document.getElementById('audio-player-container').classList.add('hidden');
            currentPlayingBtn = null;
        }
    </script>
    @endpush
</x-app-layout>
