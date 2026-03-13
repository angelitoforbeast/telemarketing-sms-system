<x-app-layout>
    <x-slot name="title">Call Logs</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Call Logs</h2>
            <a href="{{ route('telemarketing.dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md bg-gray-100 hover:bg-gray-200 transition">&larr; Back to Dashboard</a>
        </div>
    </x-slot>

    <div class="py-6" x-data="callLogColumns()">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Stats Cards --}}
            @php
                $totalLogs = $allLogs->flatten()->count();
                $totalRecordings = $allLogs->flatten()->filter(fn($l) => $l->hasRecording())->count();
                $totalAnalyzed = $allLogs->flatten()->filter(fn($l) => $l->isFullyAnalyzed())->count();
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
                            <label class="block text-xs font-medium text-gray-500 mb-1">Recording</label>
                            <select name="recording" class="text-sm border-gray-300 rounded-md shadow-sm">
                                <option value="all" {{ ($filterDefaults['recording'] ?? 'with') == 'all' ? 'selected' : '' }}>All Calls</option>
                                <option value="with" {{ ($filterDefaults['recording'] ?? 'with') == 'with' ? 'selected' : '' }}>With Recordings</option>
                                <option value="without" {{ ($filterDefaults['recording'] ?? 'with') == 'without' ? 'selected' : '' }}>Without Recordings</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                            <input type="date" name="date_from" value="{{ $filterDefaults['date_from'] ?? request('date_from') }}" class="text-sm border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}" class="text-sm border-gray-300 rounded-md shadow-sm">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition">Filter</button>
                        <a href="{{ route('telemarketing.call-logs') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-md hover:bg-gray-300 transition">Reset</a>
                    </form>

                    <div class="flex items-center gap-2">
                        {{-- Column Settings Gear (CEO/Owner only) --}}
                        @if(auth()->user()->hasRole('Company Owner') || auth()->user()->hasRole('CEO') || auth()->user()->hasRole('Platform Admin'))
                            <div class="relative">
                                <button @click="showPanel = !showPanel" class="p-2 bg-gray-100 text-gray-600 rounded-md hover:bg-gray-200 transition" title="Column Settings">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>

                                {{-- Column Settings Panel --}}
                                <div x-show="showPanel" @click.away="showPanel = false" x-transition
                                     class="absolute right-0 top-full mt-2 w-72 bg-white rounded-lg shadow-xl border border-gray-200 z-50 p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-sm font-semibold text-gray-800">Column Settings</h3>
                                        <button @click="showPanel = false" class="text-gray-400 hover:text-gray-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-400 mb-3">Toggle visibility and drag to reorder</p>

                                    <div id="column-sortable-list">
                                        <template x-for="(col, index) in columns" :key="col.key">
                                            <div class="flex items-center gap-2 py-1.5 px-2 mb-1 bg-gray-50 rounded cursor-grab hover:bg-gray-100 transition"
                                                 draggable="true"
                                                 @dragstart="dragStart(index, $event)"
                                                 @dragover.prevent="dragOver(index, $event)"
                                                 @dragend="dragEnd()"
                                                 :class="{ 'opacity-50': draggingIndex === index, 'border-t-2 border-indigo-400': dropTarget === index }">
                                                <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
                                                </svg>
                                                <label class="flex items-center gap-2 flex-1 cursor-pointer select-none">
                                                    <input type="checkbox" x-model="col.visible"
                                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5"
                                                           :disabled="col.key === 'expand' || col.key === 'actions'">
                                                    <span class="text-xs font-medium text-gray-700" x-text="col.label"></span>
                                                </label>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-200">
                                        <button @click="saveColumns()" class="flex-1 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700 transition"
                                                :disabled="saving" x-text="saving ? 'Saving...' : 'Save Settings'"></button>
                                        <button @click="resetColumns()" class="px-3 py-1.5 bg-gray-200 text-gray-600 text-xs font-medium rounded-md hover:bg-gray-300 transition">Reset</button>
                                    </div>
                                    <div x-show="saveSuccess" x-transition class="mt-2 text-xs text-green-600 text-center font-medium">Settings saved! Reloading...</div>
                                </div>
                            </div>
                        @endif

                        {{-- Analyze All Button (CEO/Owner only) --}}
                        @if(auth()->user()->hasRole('Company Owner') || auth()->user()->hasRole('CEO') || auth()->user()->hasRole('Platform Admin'))
                            <button onclick="analyzeAllUnanalyzed()" id="analyze-all-btn" class="px-4 py-2 bg-purple-600 text-white text-sm rounded-md hover:bg-purple-700 transition flex items-center gap-2 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                Analyze All Unanalyzed (<span id="unanalyzed-count">{{ $totalUnanalyzed }}</span>)
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Call Logs Table --}}
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <template x-for="col in visibleColumns" :key="col.key">
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                        :class="{
                                            'w-8': col.key === 'expand',
                                            'text-right': col.key === 'cod',
                                            'text-center': ['sentiment','intent','score','calls','actions'].includes(col.key)
                                        }"
                                        x-text="col.key === 'expand' ? '' : col.label"></th>
                                </template>
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
                                    <td x-show="isVisible('expand')" class="px-3 py-3">
                                        <svg class="w-4 h-4 text-gray-400 transform transition-transform expand-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </td>
                                    {{-- Waybill --}}
                                    <td x-show="isVisible('waybill')" class="px-3 py-3">
                                        <a href="{{ route('telemarketing.call', $shipment) }}" class="text-sm font-mono font-semibold text-indigo-600 hover:text-indigo-900" onclick="event.stopPropagation()">{{ $shipment->waybill_no }}</a>
                                    </td>
                                    {{-- Customer --}}
                                    <td x-show="isVisible('customer')" class="px-3 py-3">
                                        <p class="text-sm font-medium text-gray-900 truncate max-w-[140px]">{{ $shipment->consignee_name }}</p>
                                        <p class="text-xs text-gray-500 font-mono">{{ $shipment->consignee_phone_1 }}</p>
                                    </td>
                                    {{-- COD --}}
                                    <td x-show="isVisible('cod')" class="px-3 py-3 text-right">
                                        <span class="text-sm font-semibold text-gray-900">&#8369;{{ number_format($shipment->cod_amount, 2) }}</span>
                                    </td>
                                    {{-- Disposition --}}
                                    <td x-show="isVisible('disposition')" class="px-3 py-3">
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
                                    {{-- AI Disposition --}}
                                    <td x-show="isVisible('ai_disposition')" class="px-3 py-3">
                                        @if($analyzedLog && $analyzedLog->aiDisposition)
                                            <x-badge :color="$analyzedLog->aiDisposition->color ?? 'blue'">{{ $analyzedLog->aiDisposition->name }}</x-badge>
                                            @if($shipment->lastDisposition && $analyzedLog->aiDisposition->id !== $shipment->lastDisposition->id)
                                                <span class="text-red-500 text-xs flex items-center gap-0.5 mt-0.5" title="Mismatch with agent disposition">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                                    Mismatch
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-xs text-gray-300 italic">-</span>
                                        @endif
                                    </td>
                                    {{-- AI Summary --}}
                                    <td x-show="isVisible('summary')" class="px-3 py-3">
                                        @if($analyzedLog && $analyzedLog->ai_summary)
                                            <p class="text-xs text-gray-600 truncate max-w-[200px]" title="{{ $analyzedLog->ai_summary }}">{{ $analyzedLog->ai_summary }}</p>
                                        @else
                                            <span class="text-xs text-gray-300 italic">-</span>
                                        @endif
                                    </td>
                                    {{-- Sentiment --}}
                                    <td x-show="isVisible('sentiment')" class="px-3 py-3 text-center">
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
                                    <td x-show="isVisible('intent')" class="px-3 py-3 text-center">
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
                                    <td x-show="isVisible('score')" class="px-3 py-3 text-center">
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
                                    <td x-show="isVisible('issues')" class="px-3 py-3">
                                        @if($analyzedLog && $analyzedLog->ai_key_issues && strtolower($analyzedLog->ai_key_issues) !== 'none')
                                            <p class="text-xs text-orange-600 truncate max-w-[150px]" title="{{ $analyzedLog->ai_key_issues }}">{{ $analyzedLog->ai_key_issues }}</p>
                                        @else
                                            <span class="text-xs text-gray-300">-</span>
                                        @endif
                                    </td>
                                    {{-- Calls Count --}}
                                    <td x-show="isVisible('calls')" class="px-3 py-3 text-center">
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
                                    <td x-show="isVisible('actions')" class="px-3 py-3 text-center">
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
                                    <td :colspan="visibleColumns.length" class="p-0">
                                        <div class="bg-gray-50 border-t border-b border-gray-200">
                                            @foreach($logs as $log)
                                                <div class="border-b border-gray-100 last:border-b-0">
                                                    {{-- Call Attempt Header --}}
                                                    <div class="px-5 py-2.5 flex items-center justify-between {{ $log->status === 'draft' ? 'bg-yellow-50' : 'bg-white' }}">
                                                        <div class="flex items-center gap-4 text-sm flex-wrap">
                                                            <span class="font-semibold text-gray-400 w-6">#{{ $log->attempt_no ?? '-' }}</span>
                                                            <span class="text-gray-500 text-xs">{{ $log->created_at->format('M d, Y H:i') }}</span>
                                                            <span class="font-medium text-gray-700">{{ $log->user?->name ?? '-' }}</span>
                                                            <span class="font-mono text-gray-500 text-xs">{{ $log->phone_called ?? '-' }}</span>
                                                            @if($log->disposition)
                                                                <x-badge :color="$log->disposition->color ?? 'gray'">{{ $log->disposition->name }}</x-badge>
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
                                                                <div class="flex-1 min-w-[200px]">
                                                                    <span class="font-semibold text-indigo-600">Summary:</span>
                                                                    <span class="text-gray-700">{{ $log->ai_summary }}</span>
                                                                </div>
                                                                @if($log->ai_sentiment)
                                                                    <div>
                                                                        <span class="font-semibold text-gray-500">Sentiment:</span>
                                                                        @php $sc = ['positive' => 'text-green-600', 'neutral' => 'text-gray-600', 'negative' => 'text-red-600']; @endphp
                                                                        <span class="font-medium {{ $sc[$log->ai_sentiment] ?? 'text-gray-600' }}">{{ ucfirst($log->ai_sentiment) }}</span>
                                                                    </div>
                                                                @endif
                                                                @if($log->ai_customer_intent)
                                                                    <div>
                                                                        <span class="font-semibold text-gray-500">Intent:</span>
                                                                        <span class="text-gray-700">{{ ucfirst($log->ai_customer_intent) }}</span>
                                                                    </div>
                                                                @endif
                                                                @if($log->ai_agent_score)
                                                                    <div>
                                                                        <span class="font-semibold text-gray-500">Score:</span>
                                                                        <span class="font-bold {{ $log->ai_agent_score >= 8 ? 'text-green-600' : ($log->ai_agent_score >= 5 ? 'text-yellow-600' : 'text-red-600') }}">{{ $log->ai_agent_score }}/10</span>
                                                                    </div>
                                                                @endif
                                                                @if($log->ai_key_issues && strtolower($log->ai_key_issues) !== 'none')
                                                                    <div>
                                                                        <span class="font-semibold text-orange-500">Issues:</span>
                                                                        <span class="text-gray-700">{{ $log->ai_key_issues }}</span>
                                                                    </div>
                                                                @endif
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
                                                        <div class="px-5 py-1.5 bg-yellow-50/50 border-t border-yellow-100">
                                                            <span class="text-xs font-semibold text-yellow-600">Notes:</span>
                                                            <span class="text-xs text-gray-600">{{ $log->notes }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td :colspan="visibleColumns.length" class="px-6 py-8 text-center text-sm text-gray-500">No call logs found.</td>
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
        function callLogColumns() {
            const defaultColumns = [
                { key: 'expand', label: '', visible: true, order: 0 },
                { key: 'waybill', label: 'Waybill', visible: true, order: 1 },
                { key: 'customer', label: 'Customer', visible: true, order: 2 },
                { key: 'cod', label: 'COD', visible: true, order: 3 },
                { key: 'disposition', label: 'Disposition', visible: true, order: 4 },
                { key: 'ai_disposition', label: 'AI Disposition', visible: true, order: 5 },
                { key: 'summary', label: 'AI Summary', visible: true, order: 6 },
                { key: 'sentiment', label: 'Sentiment', visible: true, order: 7 },
                { key: 'intent', label: 'Intent', visible: true, order: 8 },
                { key: 'score', label: 'Score', visible: true, order: 9 },
                { key: 'issues', label: 'Issues', visible: true, order: 10 },
                { key: 'calls', label: 'Calls', visible: true, order: 11 },
                { key: 'actions', label: 'Actions', visible: true, order: 12 },
            ];

            // Load saved config from server
            const savedConfig = @json($columnConfig ?? null);
            let columns = defaultColumns;

            if (savedConfig && Array.isArray(savedConfig) && savedConfig.length > 0) {
                // Merge saved config with defaults (in case new columns were added)
                const savedMap = {};
                savedConfig.forEach(c => { savedMap[c.key] = c; });

                columns = [];
                // First add saved columns in their order
                savedConfig.forEach(sc => {
                    const def = defaultColumns.find(d => d.key === sc.key);
                    if (def) {
                        columns.push({ ...def, visible: sc.visible, order: sc.order });
                    }
                });
                // Insert any new default columns at their natural position (after the column that precedes them in defaults)
                defaultColumns.forEach((dc, defaultIdx) => {
                    if (!savedMap[dc.key]) {
                        // Find the preceding default column that exists in saved config
                        let insertAt = columns.length;
                        for (let i = defaultIdx - 1; i >= 0; i--) {
                            const prevKey = defaultColumns[i].key;
                            const prevIdx = columns.findIndex(c => c.key === prevKey);
                            if (prevIdx !== -1) {
                                insertAt = prevIdx + 1;
                                break;
                            }
                        }
                        columns.splice(insertAt, 0, { ...dc });
                    }
                });
                // Re-assign order values based on final position
                columns.forEach((c, i) => { c.order = i; });

                columns.sort((a, b) => a.order - b.order);
            }

            return {
                columns: columns,
                showPanel: false,
                saving: false,
                saveSuccess: false,
                draggingIndex: null,
                dropTarget: null,

                get visibleColumns() {
                    return this.columns.filter(c => c.visible);
                },

                isVisible(key) {
                    const col = this.columns.find(c => c.key === key);
                    return col ? col.visible : true;
                },

                dragStart(index, event) {
                    this.draggingIndex = index;
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', index);
                },

                dragOver(index, event) {
                    if (this.draggingIndex === null || this.draggingIndex === index) {
                        this.dropTarget = null;
                        return;
                    }
                    this.dropTarget = index;
                },

                dragEnd() {
                    if (this.draggingIndex !== null && this.dropTarget !== null && this.draggingIndex !== this.dropTarget) {
                        const item = this.columns.splice(this.draggingIndex, 1)[0];
                        this.columns.splice(this.dropTarget, 0, item);
                        // Update order values
                        this.columns.forEach((c, i) => c.order = i);
                    }
                    this.draggingIndex = null;
                    this.dropTarget = null;
                },

                saveColumns() {
                    this.saving = true;
                    this.saveSuccess = false;

                    fetch('{{ route("api.call-log-columns.save") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ columns: this.columns }),
                    })
                    .then(r => r.json())
                    .then(data => {
                        this.saving = false;
                        if (data.success) {
                            this.saveSuccess = true;
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            alert('Failed to save: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(() => {
                        this.saving = false;
                        alert('Network error. Please try again.');
                    });
                },

                resetColumns() {
                    this.columns = [
                        { key: 'expand', label: '', visible: true, order: 0 },
                        { key: 'waybill', label: 'Waybill', visible: true, order: 1 },
                        { key: 'customer', label: 'Customer', visible: true, order: 2 },
                        { key: 'cod', label: 'COD', visible: true, order: 3 },
                        { key: 'disposition', label: 'Disposition', visible: true, order: 4 },
                        { key: 'summary', label: 'AI Summary', visible: true, order: 5 },
                        { key: 'sentiment', label: 'Sentiment', visible: true, order: 6 },
                        { key: 'intent', label: 'Intent', visible: true, order: 7 },
                        { key: 'score', label: 'Score', visible: true, order: 8 },
                        { key: 'issues', label: 'Issues', visible: true, order: 9 },
                        { key: 'calls', label: 'Calls', visible: true, order: 10 },
                        { key: 'actions', label: 'Actions', visible: true, order: 11 },
                    ];
                },
            };
        }

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
