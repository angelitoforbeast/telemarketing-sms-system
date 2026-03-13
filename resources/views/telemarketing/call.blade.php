<x-app-layout>
    <x-slot name="title">Call</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Call: {{ $shipment->waybill_no }}</h2>
                <span class="text-sm text-gray-500">|</span>
                <span class="text-sm text-gray-500">{{ $queueCount }} remaining in queue</span>
            </div>
            <div class="flex items-center space-x-2">
                @if($autoCallSettings->auto_call_enabled)
                    <span class="text-xs font-medium text-indigo-600 bg-indigo-50 px-2 py-1 rounded-full">Auto-Call ON</span>
                @endif
                <a href="{{ route('telemarketing.queue') }}" class="text-sm text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md bg-gray-100 hover:bg-gray-200 transition">&larr; Back to Queue</a>
                <a href="{{ route('telemarketing.next-call', ['exclude' => $shipment->id]) }}" class="text-sm text-white px-3 py-2 rounded-md bg-indigo-600 hover:bg-indigo-700 transition">Skip &rarr;</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    @foreach($errors->all() as $error)
                        {{ $error }}<br>
                    @endforeach
                </div>
            @endif

            <div id="called-today-banner">
            @if($calledToday)
            @if($calledToday->status === 'draft')
            <div class="mb-4 bg-amber-50 border border-amber-300 rounded-lg p-4 flex items-center space-x-3">
                <div class="flex-shrink-0">
                    <svg class="w-6 h-6 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.828a1 1 0 101.415-1.414L11 9.586V6z" clip-rule="evenodd"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-amber-800">Called Today — Awaiting Disposition</p>
                    <p class="text-xs text-amber-600">
                        Called at {{ $calledToday->created_at->format("g:i A") }}
                        by {{ $calledToday->user?->name ?? "N/A" }}
                        @if($calledToday->call_duration_seconds)
                            | Duration: {{ gmdate("i:s", $calledToday->call_duration_seconds) }}
                        @endif
                        — Please submit disposition below
                    </p>
                </div>
            </div>
            @else
            <div class="mb-4 bg-green-50 border border-green-300 rounded-lg p-4 flex items-center space-x-3">
                <div class="flex-shrink-0">
                    <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-green-800">Already Called Today</p>
                    <p class="text-xs text-green-600">
                        Called at {{ $calledToday->created_at->format("g:i A") }}
                        by {{ $calledToday->user?->name ?? "N/A" }}
                        @if($calledToday->disposition)
                            — {{ $calledToday->disposition->name }}
                        @endif
                        @if($calledToday->call_duration_seconds)
                            | Duration: {{ gmdate("i:s", $calledToday->call_duration_seconds) }}
                        @endif
                    </p>
                </div>
            </div>
            @endif
            @endif
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- LEFT: Shipment Info + Click-to-Call --}}
                <div class="lg:col-span-1 space-y-4">

                    {{-- Click-to-Call Panel --}}
                    <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-lg shadow-lg p-5 text-white">
                        <h3 class="text-sm font-medium text-green-100 mb-3">Click to Call</h3>
                        <div class="space-y-3">
                            @if($shipment->consignee_phone_1)
                                <a href="tel:{{ $shipment->consignee_phone_1 }}" id="call-btn-1"
                                   onclick="handleCallClick(event, '{{ $shipment->consignee_phone_1 }}')"
                                   class="flex items-center justify-between p-3 bg-white/20 rounded-lg hover:bg-white/30 transition group">
                                    <div>
                                        <p class="text-xs text-green-100">Primary</p>
                                        <p class="text-lg font-bold font-mono">{{ $shipment->consignee_phone_1 }}</p>
                                    </div>
                                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center group-hover:scale-110 transition">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                    </div>
                                </a>
                            @endif
                            @if($shipment->consignee_phone_2)
                                <a href="tel:{{ $shipment->consignee_phone_2 }}" id="call-btn-2"
                                   onclick="handleCallClick(event, '{{ $shipment->consignee_phone_2 }}')"
                                   class="flex items-center justify-between p-3 bg-white/20 rounded-lg hover:bg-white/30 transition group">
                                    <div>
                                        <p class="text-xs text-green-100">Secondary</p>
                                        <p class="text-lg font-bold font-mono">{{ $shipment->consignee_phone_2 }}</p>
                                    </div>
                                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center group-hover:scale-110 transition">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                    </div>
                                </a>
                            @endif
                        </div>

                        {{-- Call Timer --}}
                        <div id="call-timer" class="mt-3 text-center hidden">
                            <p class="text-xs text-green-200">Call Duration</p>
                            <p id="timer-display" class="text-2xl font-mono font-bold">00:00</p>
                            <button type="button" onclick="stopCallTimer()" class="mt-1 text-xs text-green-200 hover:text-white underline">Stop Timer</button>
                        </div>
                    </div>

                    {{-- Shipment Info --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-5 py-4">
                            <h3 class="text-sm font-medium text-gray-500 mb-3">Shipment Info</h3>
                            <dl class="space-y-2">
                                <div><dt class="text-xs text-gray-400">Waybill</dt><dd class="text-sm font-mono font-semibold">{{ $shipment->waybill_no }}</dd></div>
                                <div><dt class="text-xs text-gray-400">Courier</dt><dd><x-badge :color="$shipment->courier === 'jnt' ? 'red' : 'orange'">{{ strtoupper($shipment->courier) }}</x-badge></dd></div>
                                <div><dt class="text-xs text-gray-400">Status</dt><dd><x-badge color="blue">{{ $shipment->status?->name ?? 'Unknown' }}</x-badge></dd></div>
                                <div><dt class="text-xs text-gray-400">COD Amount</dt><dd class="text-sm font-semibold">{{ $shipment->cod_amount ? '₱' . number_format($shipment->cod_amount, 2) : '-' }}</dd></div>
                                <div><dt class="text-xs text-gray-400">Item</dt><dd class="text-sm">{{ Str::limit($shipment->item_description, 50) ?? '-' }}</dd></div>
                                @if($shipment->submission_time)
                                <div><dt class="text-xs text-gray-400">Submitted</dt><dd class="text-sm">{{ $shipment->submission_time->format('M d, Y') }}</dd></div>
                                @endif
                                @if($shipment->signing_time)
                                <div><dt class="text-xs text-gray-400">Delivered</dt><dd class="text-sm">{{ $shipment->signing_time->format('M d, Y') }}</dd></div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    {{-- Consignee Info --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-5 py-4">
                            <h3 class="text-sm font-medium text-gray-500 mb-3">Consignee</h3>
                            <dl class="space-y-2">
                                <div><dt class="text-xs text-gray-400">Name</dt><dd class="text-sm font-semibold">{{ $shipment->consignee_name }}</dd></div>
                                <div><dt class="text-xs text-gray-400">Address</dt><dd class="text-sm">{{ $shipment->consignee_address ?? '-' }}</dd></div>
                                <div><dt class="text-xs text-gray-400">Province / City</dt><dd class="text-sm">{{ $shipment->consignee_province ?? '-' }} / {{ $shipment->consignee_city ?? '-' }}</dd></div>
                                @if($shipment->consignee_barangay)
                                <div><dt class="text-xs text-gray-400">Barangay</dt><dd class="text-sm">{{ $shipment->consignee_barangay }}</dd></div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>

                {{-- RIGHT: Call Form + History --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- Log Call Form --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-6 py-5">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                Log Call
                                <span class="text-sm font-normal text-gray-500">(Attempt #{{ $shipment->telemarketing_attempt_count + 1 }})</span>
                            </h3>
                            <form method="POST" action="{{ route('telemarketing.log-call', $shipment) }}" id="call-form" enctype="multipart/form-data">
                                @csrf

                                {{-- Disposition Button Cards --}}
                                <div class="mb-4">
                                    <x-input-label value="Disposition *" class="mb-2" />
                                    <input type="hidden" id="disposition_id" name="disposition_id" value="{{ old('disposition_id', '') }}" required>

                                    @php
                                        $grouped = $dispositions->groupBy('category');
                                        $categoryLabels = \App\Models\TelemarketingDisposition::CATEGORY_LABELS;
                                        $categoryOrder = ['answered', 'not_reached', 'invalid', 'other'];
                                        $colorMap = [
                                            'green'   => ['bg' => 'bg-green-50 border-green-300 hover:bg-green-100', 'active' => 'bg-green-500 border-green-600 text-white ring-2 ring-green-300', 'dot' => 'bg-green-500', 'text' => 'text-green-800'],
                                            'blue'    => ['bg' => 'bg-blue-50 border-blue-300 hover:bg-blue-100', 'active' => 'bg-blue-500 border-blue-600 text-white ring-2 ring-blue-300', 'dot' => 'bg-blue-500', 'text' => 'text-blue-800'],
                                            'red'     => ['bg' => 'bg-red-50 border-red-300 hover:bg-red-100', 'active' => 'bg-red-500 border-red-600 text-white ring-2 ring-red-300', 'dot' => 'bg-red-500', 'text' => 'text-red-800'],
                                            'orange'  => ['bg' => 'bg-orange-50 border-orange-300 hover:bg-orange-100', 'active' => 'bg-orange-500 border-orange-600 text-white ring-2 ring-orange-300', 'dot' => 'bg-orange-500', 'text' => 'text-orange-800'],
                                            'yellow'  => ['bg' => 'bg-yellow-50 border-yellow-300 hover:bg-yellow-100', 'active' => 'bg-yellow-500 border-yellow-600 text-white ring-2 ring-yellow-300', 'dot' => 'bg-yellow-500', 'text' => 'text-yellow-800'],
                                            'purple'  => ['bg' => 'bg-purple-50 border-purple-300 hover:bg-purple-100', 'active' => 'bg-purple-500 border-purple-600 text-white ring-2 ring-purple-300', 'dot' => 'bg-purple-500', 'text' => 'text-purple-800'],
                                            'emerald' => ['bg' => 'bg-emerald-50 border-emerald-300 hover:bg-emerald-100', 'active' => 'bg-emerald-500 border-emerald-600 text-white ring-2 ring-emerald-300', 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-800'],
                                            'gray'    => ['bg' => 'bg-gray-50 border-gray-300 hover:bg-gray-100', 'active' => 'bg-gray-500 border-gray-600 text-white ring-2 ring-gray-300', 'dot' => 'bg-gray-500', 'text' => 'text-gray-800'],
                                        ];
                                    @endphp

                                    <div class="space-y-3">
                                        @foreach($categoryOrder as $cat)
                                            @if($grouped->has($cat))
                                                <div>
                                                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">{{ $categoryLabels[$cat] ?? ucfirst($cat) }}</p>
                                                    <div class="flex flex-wrap gap-2">
                                                        @foreach($grouped[$cat] as $d)
                                                            @php $c = $colorMap[$d->color] ?? $colorMap['gray']; @endphp
                                                            <button type="button"
                                                                    data-disposition-id="{{ $d->id }}"
                                                                    data-requires-callback="{{ $d->requires_callback ? '1' : '0' }}"
                                                                    data-color="{{ $d->color }}"
                                                                    data-is-final="{{ $d->is_final ? '1' : '0' }}"
                                                                    data-triggers-order="{{ $d->triggers_order ? '1' : '0' }}"
                                                                    data-name="{{ $d->name }}"
                                                                    data-active-class="{{ $c['active'] }}"
                                                                    data-default-class="{{ $c['bg'] }} {{ $c['text'] }}"
                                                                    onclick="selectDisposition(this)"
                                                                    class="disposition-btn inline-flex items-center px-3 py-1.5 border rounded-full text-xs font-medium transition-all duration-150 cursor-pointer {{ $c['bg'] }} {{ $c['text'] }} {{ old('disposition_id') == $d->id ? $c['active'] : '' }}">
                                                                <span class="w-2 h-2 rounded-full {{ $c['dot'] }} mr-1.5 flex-shrink-0"></span>
                                                                {{ $d->name }}@if($d->is_final)<span class="ml-1 opacity-60 text-[10px]">(F)</span>@endif
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>

                                    <div id="disposition-required-msg" class="hidden mt-2 text-xs text-red-500">Please select a disposition</div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <x-input-label for="phone_called" value="Phone Called" />
                                        <select id="phone_called" name="phone_called"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="{{ $shipment->consignee_phone_1 }}">{{ $shipment->consignee_phone_1 }} (Primary)</option>
                                            @if($shipment->consignee_phone_2)
                                                <option value="{{ $shipment->consignee_phone_2 }}">{{ $shipment->consignee_phone_2 }} (Secondary)</option>
                                            @endif
                                        </select>
                                    </div>
                                    <div id="callback-field" class="hidden">
                                        <x-input-label for="callback_at" value="Schedule Callback *" />
                                        <input type="datetime-local" id="callback_at" name="callback_at" value="{{ old('callback_at') }}"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <x-input-label for="notes" value="Notes" />
                                    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Any notes about the call...">{{ old('notes') }}</textarea>
                                </div>

                                {{-- Recording Status (shown when using Android app) --}}
                                @if(($recordingMode ?? 'both') !== 'manual')
                                <div id="recording-status" class="mb-4 hidden">
                                    <div class="flex items-center space-x-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                                        <svg class="w-5 h-5 text-green-600 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7 4a3 3 0 016 0v4a3 3 0 11-6 0V4zm4 10.93A7.001 7.001 0 0017 8a1 1 0 10-2 0A5 5 0 015 8a1 1 0 00-2 0 7.001 7.001 0 006 6.93V17H6a1 1 0 100 2h8a1 1 0 100 0h-3v-2.07z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span id="recording-status-text" class="text-sm text-green-700 font-medium">Call recording will be auto-uploaded</span>
                                    </div>
                                </div>
                                @endif

                                {{-- Hidden call duration field --}}
                                <input type="hidden" name="call_duration_seconds" id="call_duration_seconds" value="">

                                {{-- Manual Recording Upload --}}
                                <div id="manual-upload-section" class="mb-4 p-4 bg-orange-50 border-2 border-orange-300 rounded-lg @if(($recordingMode ?? "both") === "auto") hidden @endif">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-sm font-semibold text-orange-800">
                                            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7 4a3 3 0 016 0v4a3 3 0 11-6 0V4zm4 10.93A7.001 7.001 0 0017 8a1 1 0 10-2 0A5 5 0 015 8a1 1 0 00-2 0 7.001 7.001 0 006 6.93V17H6a1 1 0 100 2h8a1 1 0 100 0h-3v-2.07z" clip-rule="evenodd"></path></svg>
                                            Upload Call Recording
                                        </h4>
                                        <span class="text-xs text-orange-600">Optional</span>
                                    </div>
                                    <p class="text-xs text-orange-600 mb-3">Attach the call recording file (.mp3, .m4a, .wav, .amr, etc.)</p>
                                    <div class="flex items-center space-x-2">
                                        <label for="call_recording" class="flex-1 cursor-pointer">
                                            <div id="recording-drop-zone" class="flex items-center justify-center px-4 py-3 border-2 border-dashed border-orange-300 rounded-lg hover:border-orange-500 hover:bg-orange-100 transition">
                                                <svg class="w-5 h-5 text-orange-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                                <span id="recording-file-name" class="text-sm text-orange-600">Choose recording file...</span>
                                            </div>
                                            <input type="file" id="call_recording" name="call_recording" accept="audio/*,.mp3,.m4a,.amr,.wav,.ogg,.3gp,.aac,.opus,.webm" class="hidden" onchange="handleRecordingFileSelect(this)">
                                        </label>
                                    </div>
                                    <div id="recording-file-info" class="hidden mt-2 p-2 bg-green-50 border border-green-200 rounded-lg">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-2">
                                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                <span id="recording-selected-name" class="text-sm text-green-700 font-medium"></span>
                                            </div>
                                            <button type="button" onclick="clearRecordingFile()" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end space-x-3">
                                    <button type="submit" name="action" value="save" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition">
                                        Save & Back to Queue
                                    </button>
                                    <button type="submit" name="action" value="save_next" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition shadow-sm">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                                        Save & Next Call
                                    </button>
                                </div>

                                {{-- Auto-Call Countdown Overlay --}}
                                @if($autoCallSettings->auto_call_enabled)
                                <div id="auto-call-overlay" class="hidden mt-4 p-4 bg-indigo-50 border-2 border-indigo-300 rounded-lg text-center">
                                    <p class="text-sm text-indigo-700 font-medium mb-2">Auto-calling next number in...</p>
                                    <p id="auto-call-countdown" class="text-4xl font-bold text-indigo-600 font-mono">{{ $autoCallSettings->auto_call_delay }}</p>
                                    <p class="text-xs text-indigo-500 mt-1">seconds</p>
                                    <div class="mt-3 flex justify-center space-x-3">
                                        <button type="button" onclick="cancelAutoCall()" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                                            Cancel
                                        </button>
                                        <button type="button" onclick="skipAutoCallCountdown()" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                            Call Now
                                        </button>
                                    </div>
                                </div>
                                @endif
                            </form>
                        </div>
                    </div>

                    {{-- New Order Form (shown when triggers_order disposition selected) --}}
                    @include('telemarketing.partials.order-form')

                    {{-- Call History --}}
                    <div id="call-history-container">
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-6 py-5">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Previous Calls ({{ $callHistory->count() }})</h3>
                            <div class="space-y-3">
                                @forelse($callHistory as $log)
                                    <div class="border rounded-lg p-3 {{ $log->status === 'draft' ? 'border-amber-300 bg-amber-50' : ($loop->first ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200') }}">
                                        @if($log->status === 'draft')
                                        <div class="flex items-center space-x-1 mb-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Pending Disposition</span>
                                        </div>
                                        @endif
                                        <div class="flex justify-between items-start mb-1">
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm font-semibold text-gray-900">Attempt #{{ $log->attempt_no }}</span>
                                                <x-badge :color="$log->disposition?->color ?? 'gray'">{{ $log->disposition?->name ?? 'N/A' }}</x-badge>
                                            </div>
                                            <span class="text-xs text-gray-500">{{ $log->created_at->format('M d, Y H:i') }}</span>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            By: {{ $log->user?->name ?? 'N/A' }} |
                                            Phone: {{ $log->phone_called ?? '-' }}
                                            @if($log->call_duration_seconds)
                                                | Duration: {{ gmdate('i:s', $log->call_duration_seconds) }}
                                            @endif
                                        </p>
                                        @if($log->notes)
                                            <p class="text-sm text-gray-700 mt-1">{{ $log->notes }}</p>
                                        @endif
                                        @if($log->hasRecording())
                                            <div class="mt-2 bg-gray-50 rounded-md p-2">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>
                                                    <audio controls preload="none" class="h-8 w-full max-w-xs">
                                                        <source src="{{ $log->getRecordingPlaybackUrl() }}">
                                                        Your browser does not support audio playback.
                                                    </audio>
                                                    <span class="text-xs text-green-600 font-medium whitespace-nowrap">Recorded</span>
                                                </div>
                                            </div>
                                        @endif
                                        @if($log->callback_at)
                                            <p class="text-xs text-orange-600 mt-1">
                                                <svg class="w-3 h-3 inline" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>
                                                Callback: {{ $log->callback_at->format('M d, Y H:i') }}
                                            </p>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500">No previous calls for this shipment.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let callTimerInterval = null;
        let callStartTime = null;
        let currentDraftLogId = null; // Stores the draft log ID for recording linking

        /**
         * Handle call button click: create draft log row FIRST, then start timer and dial.
         * This ensures the log_id exists before the call recording starts.
         */
        function handleCallClick(event, phoneNumber) {
            // Start the timer immediately for UX
            startCallTimer();

            // Create draft log via AJAX (fire-and-forget, don't block dialing)
            createDraftLog(phoneNumber);

            // The default href="tel:..." will handle the actual dialing
            // No need to prevent default — let the <a> tag work normally
        }

        /**
         * Create a draft telemarketing log row via API.
         * This gives us a log_id that the Android auto-upload can use.
         */
        function createDraftLog(phoneNumber) {
            var shipmentId = '{{ $shipment->id }}';

            fetch('/api/telemarketing/create-draft-log', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    shipment_id: shipmentId,
                    phone_number: phoneNumber
                })
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data.log_id) {
                    currentDraftLogId = data.log_id;
                    console.log('Draft log created/reused: log_id=' + data.log_id + ' reused=' + data.reused);

                    // Notify Android app about the draft log_id for recording upload
                    if (typeof TeleSMSBridge !== 'undefined') {
                        try {
                            TeleSMSBridge.setDraftLogId(String(data.log_id));
                            console.log('Sent draft log_id to Android: ' + data.log_id);
                        } catch(e) {
                            console.log('TeleSMSBridge.setDraftLogId not available:', e);
                        }
                    }

                    // Update recording status text
                    var statusText = document.getElementById('recording-status-text');
                    if (statusText) {
                        statusText.textContent = 'Draft log #' + data.log_id + ' ready — recording will auto-link';
                    }
                } else {
                    console.error('Failed to create draft log:', data);
                }
            })
            .catch(function(err) {
                console.error('Error creating draft log:', err);
            });
        }

        function startCallTimer() {
            callStartTime = new Date();
            document.getElementById('call-timer').classList.remove('hidden');
            clearInterval(callTimerInterval);
            callTimerInterval = setInterval(updateTimerDisplay, 1000);
        }

        function stopCallTimer() {
            if (callTimerInterval) {
                clearInterval(callTimerInterval);
                callTimerInterval = null;
            }
            if (callStartTime) {
                const seconds = Math.floor((new Date() - callStartTime) / 1000);
                document.getElementById('call_duration_seconds').value = seconds;
            }
        }

        function updateTimerDisplay() {
            if (!callStartTime) return;
            const seconds = Math.floor((new Date() - callStartTime) / 1000);
            const mins = String(Math.floor(seconds / 60)).padStart(2, '0');
            const secs = String(seconds % 60).padStart(2, '0');
            document.getElementById('timer-display').textContent = mins + ':' + secs;
            document.getElementById('call_duration_seconds').value = seconds;
        }

        /**
         * Select a disposition via button card click.
         */
        function selectDisposition(btn) {
            var id = btn.dataset.dispositionId;
            var requiresCallback = btn.dataset.requiresCallback === '1';

            // Update hidden input
            document.getElementById('disposition_id').value = id;

            // Reset all buttons to default style
            document.querySelectorAll('.disposition-btn').forEach(function(b) {
                b.className = 'disposition-btn inline-flex items-center px-3 py-1.5 border rounded-full text-xs font-medium transition-all duration-150 cursor-pointer ' + b.dataset.defaultClass;
            });

            // Activate clicked button
            btn.className = 'disposition-btn inline-flex items-center px-3 py-1.5 border rounded-full text-xs font-medium transition-all duration-150 cursor-pointer ' + btn.dataset.activeClass;

            // Toggle callback field
            var callbackField = document.getElementById('callback-field');
            if (requiresCallback) {
                callbackField.classList.remove('hidden');
                document.getElementById('callback_at').required = true;
            } else {
                callbackField.classList.add('hidden');
                document.getElementById('callback_at').required = false;
            }

            // Hide validation message
            document.getElementById('disposition-required-msg').classList.add('hidden');

            // Toggle New Order form based on triggers_order flag
            var triggersOrder = btn.dataset.triggersOrder === '1';
            var orderSection = document.getElementById('new-order-section');
            if (orderSection) {
                if (triggersOrder) {
                    orderSection.classList.remove('hidden');
                } else {
                    orderSection.classList.add('hidden');
                }
            }
        }

        // Auto-stop timer on form submit + validate disposition
        document.getElementById('call-form').addEventListener('submit', function(e) {
            var dispVal = document.getElementById('disposition_id').value;
            if (!dispVal) {
                e.preventDefault();
                document.getElementById('disposition-required-msg').classList.remove('hidden');
                return false;
            }
            stopCallTimer();
        });

        // Recording file selection handling
        function handleRecordingFileSelect(input) {
            if (input.files && input.files[0]) {
                var file = input.files[0];
                var sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                document.getElementById('recording-file-name').textContent = file.name;
                document.getElementById('recording-selected-name').textContent = file.name + ' (' + sizeMB + ' MB)';
                document.getElementById('recording-file-info').classList.remove('hidden');
                document.getElementById('recording-drop-zone').classList.add('border-green-400', 'bg-green-50');
                document.getElementById('recording-drop-zone').classList.remove('border-orange-300');
            }
        }

        function clearRecordingFile() {
            document.getElementById('call_recording').value = '';
            document.getElementById('recording-file-name').textContent = 'Choose recording file...';
            document.getElementById('recording-file-info').classList.add('hidden');
            document.getElementById('recording-drop-zone').classList.remove('border-green-400', 'bg-green-50');
            document.getElementById('recording-drop-zone').classList.add('border-orange-300');
        }

        // Detect if running inside TeleSMS Android app
        var recordingMode = '{{ $recordingMode ?? "both" }}';
        if (typeof TeleSMSBridge !== 'undefined' && recordingMode !== 'manual') {
            var recordingStatusEl = document.getElementById('recording-status');
            if (recordingStatusEl) recordingStatusEl.classList.remove('hidden');

            // Notify the Android app about the current shipment for recording matching
            try {
                TeleSMSBridge.setCurrentShipment('{{ $shipment->id }}', '{{ $shipment->consignee_phone_1 }}');
            } catch(e) {
                console.log('Bridge setCurrentShipment not available:', e);
            }
        }

        // ── Auto-Call Mode ──
        var autoCallEnabled = {{ $autoCallSettings->auto_call_enabled ? 'true' : 'false' }};
        var autoCallDelay = {{ $autoCallSettings->auto_call_delay }};
        var autoCallTimer = null;
        var autoCallCountdownValue = 0;
        var autoCallTriggered = false;

        // Check URL param to see if we should auto-call (coming from save_next)
        var urlParams = new URLSearchParams(window.location.search);
        var fromAutoCall = urlParams.get('auto') === '1';

        if (autoCallEnabled && fromAutoCall && '{{ $shipment->consignee_phone_1 }}') {
            // Start auto-call countdown
            startAutoCallCountdown();
        }

        function startAutoCallCountdown() {
            var overlay = document.getElementById('auto-call-overlay');
            if (!overlay) return;

            overlay.classList.remove('hidden');
            autoCallCountdownValue = autoCallDelay;
            document.getElementById('auto-call-countdown').textContent = autoCallCountdownValue;

            autoCallTimer = setInterval(function() {
                autoCallCountdownValue--;
                document.getElementById('auto-call-countdown').textContent = autoCallCountdownValue;

                if (autoCallCountdownValue <= 0) {
                    clearInterval(autoCallTimer);
                    triggerAutoCall();
                }
            }, 1000);
        }

        function cancelAutoCall() {
            if (autoCallTimer) {
                clearInterval(autoCallTimer);
                autoCallTimer = null;
            }
            var overlay = document.getElementById('auto-call-overlay');
            if (overlay) overlay.classList.add('hidden');
        }

        function skipAutoCallCountdown() {
            if (autoCallTimer) {
                clearInterval(autoCallTimer);
                autoCallTimer = null;
            }
            triggerAutoCall();
        }

        function triggerAutoCall() {
            if (autoCallTriggered) return;
            autoCallTriggered = true;

            var overlay = document.getElementById('auto-call-overlay');
            if (overlay) overlay.classList.add('hidden');

            var phone = '{{ $shipment->consignee_phone_1 }}';
            if (!phone) return;

            // Start timer and create draft
            handleCallClick(null, phone);

            // Trigger the actual call via tel: link
            window.location.href = 'tel:' + phone;
        }

        // ── Auto-refresh Previous Calls every 15 seconds ──
        var callHistoryInterval = setInterval(function() {
            fetch('/api/telemarketing/call-history/{{ $shipment->id }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                // Update call history
                var container = document.getElementById('call-history-container');
                if (container && data.html) {
                    var inner = container.querySelector('.px-6.py-5');
                    if (inner) {
                        inner.innerHTML = '<h3 class="text-lg font-medium text-gray-900 mb-4">Previous Calls (' + data.count + ')</h3><div class="space-y-3">' + data.html + '</div>';
                    }
                }

                // Update the banner
                var bannerContainer = document.getElementById('called-today-banner');
                if (bannerContainer && data.calledToday) {
                    var ct = data.calledToday;
                    var bannerHtml = '';
                    if (ct.status === 'draft') {
                        bannerHtml = '<div class="mb-4 bg-amber-50 border border-amber-300 rounded-lg p-4 flex items-center space-x-3">'
                            + '<div class="flex-shrink-0"><svg class="w-6 h-6 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.828a1 1 0 101.415-1.414L11 9.586V6z" clip-rule="evenodd"></path></svg></div>'
                            + '<div><p class="text-sm font-semibold text-amber-800">Called Today — Awaiting Disposition</p>'
                            + '<p class="text-xs text-amber-600">Called at ' + ct.time + ' by ' + ct.user
                            + (ct.duration ? ' | Duration: ' + ct.duration : '')
                            + ' — Please submit disposition below</p></div></div>';
                    } else {
                        bannerHtml = '<div class="mb-4 bg-green-50 border border-green-300 rounded-lg p-4 flex items-center space-x-3">'
                            + '<div class="flex-shrink-0"><svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg></div>'
                            + '<div><p class="text-sm font-semibold text-green-800">Already Called Today</p>'
                            + '<p class="text-xs text-green-600">Called at ' + ct.time + ' by ' + ct.user
                            + (ct.disposition ? ' — ' + ct.disposition : '')
                            + (ct.duration ? ' | Duration: ' + ct.duration : '')
                            + '</p></div></div>';
                    }
                    bannerContainer.innerHTML = bannerHtml;
                } else if (bannerContainer && !data.calledToday) {
                    bannerContainer.innerHTML = '';
                }
            })
            .catch(function(err) { console.log('Call history poll error:', err); });
        }, 15000);
    </script>
    <script>
        // ── Recording Enforcement Logic ──
        var requireRecording = {{ $requireRecording ? 'true' : 'false' }};
        var recordingUploadTimeout = {{ $recordingUploadTimeout ?? 30 }};
        var exemptDispositions = @json($exemptDispositions ?? []);
        var enforcementActive = false;
        var skipEnforcement = false;
        var recordingCheckInterval = null;
        var enforcementTimeoutTimer = null;
        var recordingFound = false;
        var manualFileSelected = false;

        if (requireRecording) {
            // Override form submit to check recording enforcement
            document.getElementById('call-form').addEventListener('submit', function(e) {
                if (!requireRecording) return;

                var dispVal = document.getElementById('disposition_id').value;
                if (!dispVal) return;

                // Check if disposition is exempt
                var isExempt = exemptDispositions.map(String).indexOf(String(dispVal)) !== -1;
                if (isExempt) {
                    hideEnforcementOverlay();
                    return;
                }

                // Check if recording exists or enforcement was skipped
                if (recordingFound || manualFileSelected || skipEnforcement) {
                    hideEnforcementOverlay();
                    return;
                }

                // Block save
                e.preventDefault();
                e.stopImmediatePropagation();
                showEnforcementOverlay();
            }, true);

            // Monitor manual file input
            var callRecordingInput = document.getElementById('call_recording');
            if (callRecordingInput) {
                callRecordingInput.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        manualFileSelected = true;
                        hideEnforcementOverlay();
                        enableSaveButtons();
                    } else {
                        manualFileSelected = false;
                    }
                });
            }
        }

        function showEnforcementOverlay() {
            enforcementActive = true;
            var overlay = document.getElementById('enforcement-overlay');
            if (!overlay) {
                createEnforcementOverlay();
                overlay = document.getElementById('enforcement-overlay');
            }
            overlay.classList.remove('hidden');
            disableSaveButtons();
            startRecordingCheck();
            startEnforcementTimeout();
        }

        function hideEnforcementOverlay() {
            enforcementActive = false;
            var overlay = document.getElementById('enforcement-overlay');
            if (overlay) overlay.classList.add('hidden');
            stopRecordingCheck();
            stopEnforcementTimeout();
        }

        function createEnforcementOverlay() {
            var formEl = document.getElementById('call-form');
            var div = document.createElement('div');
            div.id = 'enforcement-overlay';
            div.className = 'mt-4 p-4 bg-amber-50 border-2 border-amber-400 rounded-lg';
            div.innerHTML = '<div class="flex items-start">'
                + '<svg class="w-6 h-6 text-amber-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>'
                + '<div class="flex-1">'
                + '<p class="text-sm font-semibold text-amber-800">Recording Required</p>'
                + '<p id="enforcement-status-text" class="text-sm text-amber-700 mt-1">Waiting for auto-upload to complete...</p>'
                + '<div id="enforcement-countdown" class="mt-2">'
                + '<div class="flex items-center">'
                + '<div class="w-full bg-amber-200 rounded-full h-2 mr-3">'
                + '<div id="enforcement-progress" class="bg-amber-500 h-2 rounded-full transition-all duration-1000" style="width: 100%"></div>'
                + '</div>'
                + '<span id="enforcement-timer" class="text-xs font-mono text-amber-600 whitespace-nowrap">' + recordingUploadTimeout + 's</span>'
                + '</div>'
                + '</div>'
                + '<div id="enforcement-manual-prompt" class="hidden mt-3">'
                + '<p class="text-sm text-red-700 font-medium">Auto-upload timed out. Please upload the recording manually or save without recording.</p>'
                + '<button type="button" id="save-without-recording-btn" onclick="saveWithoutRecording()" class="mt-3 inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition shadow-sm">'
                + '<svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>'
                + 'Save Without Recording'
                + '</button>'
                + '</div>'
                + '</div>'
                + '</div>';

            var saveButtons = formEl.querySelector('.flex.justify-end.space-x-3');
            if (saveButtons) {
                saveButtons.parentNode.insertBefore(div, saveButtons);
            } else {
                formEl.appendChild(div);
            }
        }

        function disableSaveButtons() {
            var buttons = document.querySelectorAll('#call-form button[type="submit"]');
            buttons.forEach(function(btn) {
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            });
        }

        function enableSaveButtons() {
            var buttons = document.querySelectorAll('#call-form button[type="submit"]');
            buttons.forEach(function(btn) {
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            });
        }

        function startRecordingCheck() {
            stopRecordingCheck();
            recordingCheckInterval = setInterval(function() {
                fetch('/api/telemarketing/check-recording/{{ $shipment->id }}', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin'
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.has_recording) {
                        recordingFound = true;
                        hideEnforcementOverlay();
                        enableSaveButtons();
                    }
                })
                .catch(function(err) {
                    console.log('Recording check error:', err);
                });
            }, 3000);
        }

        function stopRecordingCheck() {
            if (recordingCheckInterval) {
                clearInterval(recordingCheckInterval);
                recordingCheckInterval = null;
            }
        }

        var enforcementSecondsLeft = 0;

        function startEnforcementTimeout() {
            stopEnforcementTimeout();
            enforcementSecondsLeft = recordingUploadTimeout;
            updateEnforcementTimer();

            enforcementTimeoutTimer = setInterval(function() {
                enforcementSecondsLeft--;
                updateEnforcementTimer();

                if (enforcementSecondsLeft <= 0) {
                    stopEnforcementTimeout();
                    showManualUploadPrompt();
                }
            }, 1000);
        }

        function stopEnforcementTimeout() {
            if (enforcementTimeoutTimer) {
                clearInterval(enforcementTimeoutTimer);
                enforcementTimeoutTimer = null;
            }
        }

        function updateEnforcementTimer() {
            var timerEl = document.getElementById('enforcement-timer');
            var progressEl = document.getElementById('enforcement-progress');
            if (timerEl) timerEl.textContent = enforcementSecondsLeft + 's';
            if (progressEl) {
                var pct = (enforcementSecondsLeft / recordingUploadTimeout) * 100;
                progressEl.style.width = pct + '%';
            }
        }

        function saveWithoutRecording() {
            skipEnforcement = true;
            // Add a hidden input to flag that recording was skipped
            var hiddenInput = document.getElementById('missing_recording_flag');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'missing_recording';
                hiddenInput.id = 'missing_recording_flag';
                hiddenInput.value = '1';
                document.getElementById('call-form').appendChild(hiddenInput);
            }
            hideEnforcementOverlay();
            enableSaveButtons();
            // Auto-click the save & next call button
            var saveNextBtn = document.querySelector('button[name="action"][value="save_next"]');
            if (saveNextBtn) {
                saveNextBtn.click();
            }
        }
        function showManualUploadPrompt() {
            var statusText = document.getElementById('enforcement-status-text');
            if (statusText) statusText.textContent = 'Auto-upload timed out or failed.';

            var countdown = document.getElementById('enforcement-countdown');
            if (countdown) countdown.classList.add('hidden');

            var manualPrompt = document.getElementById('enforcement-manual-prompt');
            if (manualPrompt) manualPrompt.classList.remove('hidden');

            // Make the manual upload section visible if it was hidden (auto mode)
            var uploadSections = document.querySelectorAll('#call_recording');
            uploadSections.forEach(function(el) {
                var section = el.closest('.mb-4');
                if (section) section.classList.remove('hidden');
                // Also show parent containers
                var grandparent = section ? section.parentElement : null;
                if (grandparent) grandparent.classList.remove('hidden');
            });

            // Enable save buttons so agent can upload and save
            enableSaveButtons();
        }

    </script>
    <script>
    // ── New Order Form (Alpine.js component) ──
    function orderForm() {
        return {
            orderTypeId: null,
            customerName: @json($shipment->consignee_name ?? ''),
            customerPhone: @json($shipment->consignee_phone_1 ?? ''),
            province: '',
            city: '',
            barangay: '',
            addressDetails: '',
            provinces: [],
            cities: [],
            barangays: [],
            items: [{ item_name: '', quantity: 1, unit_price: 0 }],
            todayStr: new Date().toISOString().split('T')[0],
            processDate: new Date().toISOString().split('T')[0],
            orderNotes: '',
            submitting: false,

            get totalAmount() {
                return this.items.reduce(function(sum, item) {
                    return sum + (item.quantity * item.unit_price);
                }, 0);
            },

            get isValid() {
                return this.orderTypeId
                    && this.province
                    && this.city
                    && this.barangay
                    && this.processDate
                    && this.items.length > 0
                    && this.items.every(function(i) { return i.item_name && i.quantity > 0 && i.unit_price >= 0; });
            },

            init() {
                this.loadProvinces();
            },

            loadProvinces() {
                var self = this;
                fetch('/api/address/provinces', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                .then(function(r) { return r.json(); })
                .then(function(data) { self.provinces = data; })
                .catch(function(err) { console.error('Load provinces error:', err); });
            },

            loadCities() {
                this.city = '';
                this.barangay = '';
                this.cities = [];
                this.barangays = [];
                if (!this.province) return;
                var self = this;
                fetch('/api/address/cities?province=' + encodeURIComponent(this.province), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                .then(function(r) { return r.json(); })
                .then(function(data) { self.cities = data; })
                .catch(function(err) { console.error('Load cities error:', err); });
            },

            loadBarangays() {
                this.barangay = '';
                this.barangays = [];
                if (!this.province || !this.city) return;
                var self = this;
                fetch('/api/address/barangays?province=' + encodeURIComponent(this.province) + '&city=' + encodeURIComponent(this.city), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                .then(function(r) { return r.json(); })
                .then(function(data) { self.barangays = data; })
                .catch(function(err) { console.error('Load barangays error:', err); });
            },

            useExistingAddress() {
                var existingProvince = @json($shipment->consignee_province ?? '');
                var existingCity = @json($shipment->consignee_city ?? '');
                var existingBarangay = @json($shipment->consignee_barangay ?? '');
                var existingAddress = @json($shipment->consignee_address ?? '');

                if (existingProvince) {
                    this.province = existingProvince.toUpperCase();
                    var self = this;
                    fetch('/api/address/cities?province=' + encodeURIComponent(this.province), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin'
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        self.cities = data;
                        var matchCity = existingCity.toUpperCase();
                        var found = data.find(function(c) { return c.toUpperCase() === matchCity || c.toUpperCase().replace(/-/g, ' ') === matchCity.replace(/-/g, ' '); });
                        if (found) {
                            self.city = found;
                            return fetch('/api/address/barangays?province=' + encodeURIComponent(self.province) + '&city=' + encodeURIComponent(self.city), {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                credentials: 'same-origin'
                            });
                        }
                    })
                    .then(function(r) { if (r) return r.json(); })
                    .then(function(data) {
                        if (data) {
                            self.barangays = data;
                            var matchBrgy = existingBarangay.toUpperCase();
                            var found = data.find(function(b) { return b.toUpperCase() === matchBrgy; });
                            if (found) self.barangay = found;
                        }
                    })
                    .catch(function(err) { console.error('Use existing address error:', err); });
                }
                if (existingAddress) {
                    this.addressDetails = existingAddress;
                }
            },

            addItem() {
                this.items.push({ item_name: '', quantity: 1, unit_price: 0 });
            },

            removeItem(index) {
                if (this.items.length > 1) {
                    this.items.splice(index, 1);
                }
            },

            resetForm() {
                this.orderTypeId = null;
                this.province = '';
                this.city = '';
                this.barangay = '';
                this.addressDetails = '';
                this.cities = [];
                this.barangays = [];
                this.items = [{ item_name: '', quantity: 1, unit_price: 0 }];
                this.processDate = this.todayStr;
                this.orderNotes = '';
                document.getElementById('order-status-msg').textContent = '';
                document.getElementById('order-success-area').classList.add('hidden');
            },

            submitOrder() {
                if (this.submitting || !this.isValid) return;
                this.submitting = true;
                var statusMsg = document.getElementById('order-status-msg');
                var successArea = document.getElementById('order-success-area');
                statusMsg.textContent = '';
                statusMsg.className = 'text-sm';
                successArea.classList.add('hidden');

                var self = this;
                var payload = {
                    shipment_id: {{ $shipment->id }},
                    telemarketing_log_id: (typeof currentDraftLogId !== 'undefined') ? currentDraftLogId : null,
                    order_type_id: this.orderTypeId,
                    customer_phone: this.customerPhone,
                    customer_name: this.customerName,
                    province: this.province,
                    city: this.city,
                    barangay: this.barangay,
                    address_details: this.addressDetails,
                    process_date: this.processDate,
                    notes: this.orderNotes,
                    items: this.items
                };

                fetch('/api/orders', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                })
                .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
                .then(function(result) {
                    self.submitting = false;
                    if (result.ok && result.data.success) {
                        successArea.classList.remove('hidden');
                        document.getElementById('order-success-text').textContent = result.data.message;
                        self.resetForm();
                        successArea.classList.remove('hidden');
                    } else {
                        statusMsg.textContent = result.data.message || 'Failed to create order.';
                        statusMsg.className = 'text-sm text-red-600 font-medium';
                    }
                })
                .catch(function(err) {
                    self.submitting = false;
                    statusMsg.textContent = 'Network error. Please try again.';
                    statusMsg.className = 'text-sm text-red-600 font-medium';
                    console.error('Order submit error:', err);
                });
            }
        };
    }
    </script>
    @endpush
</x-app-layout>
