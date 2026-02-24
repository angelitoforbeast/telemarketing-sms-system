<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Call: {{ $shipment->waybill_no }}</h2>
                <span class="text-sm text-gray-500">|</span>
                <span class="text-sm text-gray-500">{{ $queueCount }} remaining in queue</span>
            </div>
            <div class="flex space-x-2">
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- LEFT: Shipment Info + Click-to-Call --}}
                <div class="lg:col-span-1 space-y-4">

                    {{-- Click-to-Call Panel --}}
                    <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-lg shadow-lg p-5 text-white">
                        <h3 class="text-sm font-medium text-green-100 mb-3">Click to Call</h3>
                        <div class="space-y-3">
                            @if($shipment->consignee_phone_1)
                                <a href="tel:{{ $shipment->consignee_phone_1 }}" id="call-btn-1"
                                   onclick="startCallTimer()"
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
                                   onclick="startCallTimer()"
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
                            <form method="POST" action="{{ route('telemarketing.log-call', $shipment) }}" id="call-form">
                                @csrf

                                {{-- Disposition Grid --}}
                                <div class="mb-4">
                                    <x-input-label value="Disposition *" class="mb-2" />
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2" id="disposition-grid">
                                        @foreach($dispositions as $d)
                                            <label class="relative cursor-pointer">
                                                <input type="radio" name="disposition_id" value="{{ $d->id }}" class="peer sr-only" required
                                                       data-requires-callback="{{ $d->requires_callback ? '1' : '0' }}"
                                                       onchange="handleDispositionChange(this)">
                                                <div class="p-3 border-2 rounded-lg text-center transition
                                                            peer-checked:border-{{ $d->color }}-500 peer-checked:bg-{{ $d->color }}-50
                                                            border-gray-200 hover:border-gray-300">
                                                    <span class="w-2 h-2 rounded-full inline-block bg-{{ $d->color }}-500 mr-1"></span>
                                                    <span class="text-sm font-medium text-gray-700">{{ $d->name }}</span>
                                                    @if($d->is_final)
                                                        <span class="block text-xs text-gray-400 mt-0.5">Final</span>
                                                    @endif
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
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
                                <div id="recording-status" class="mb-4 hidden">
                                    <div class="flex items-center space-x-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                                        <svg class="w-5 h-5 text-green-600 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7 4a3 3 0 016 0v4a3 3 0 11-6 0V4zm4 10.93A7.001 7.001 0 0017 8a1 1 0 10-2 0A5 5 0 015 8a1 1 0 00-2 0 7.001 7.001 0 006 6.93V17H6a1 1 0 100 2h8a1 1 0 100 0h-3v-2.07z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span id="recording-status-text" class="text-sm text-green-700 font-medium">Call recording will be auto-uploaded</span>
                                    </div>
                                </div>

                                {{-- Hidden call duration field --}}
                                <input type="hidden" name="call_duration_seconds" id="call_duration_seconds" value="">

                                <div class="flex justify-end space-x-3">
                                    <button type="submit" name="action" value="save" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition">
                                        Save & Back to Queue
                                    </button>
                                    <button type="submit" name="action" value="save_next" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition shadow-sm">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                                        Save & Next Call
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Call History --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-6 py-5">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Previous Calls ({{ $callHistory->count() }})</h3>
                            <div class="space-y-3">
                                @forelse($callHistory as $log)
                                    <div class="border rounded-lg p-3 {{ $loop->first ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200' }}">
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

    @push('scripts')
    <script>
        let callTimerInterval = null;
        let callStartTime = null;

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

        function handleDispositionChange(el) {
            const requiresCallback = el.dataset.requiresCallback === '1';
            const callbackField = document.getElementById('callback-field');
            if (requiresCallback) {
                callbackField.classList.remove('hidden');
                document.getElementById('callback_at').required = true;
            } else {
                callbackField.classList.add('hidden');
                document.getElementById('callback_at').required = false;
            }
        }

        // Auto-stop timer on form submit
        document.getElementById('call-form').addEventListener('submit', function() {
            stopCallTimer();
        });

        // Detect if running inside TeleSMS Android app
        if (typeof TeleSMSBridge !== 'undefined') {
            document.getElementById('recording-status').classList.remove('hidden');

            // Notify the Android app about the current shipment for recording matching
            try {
                TeleSMSBridge.setCurrentShipment('{{ $shipment->id }}', '{{ $shipment->consignee_phone_1 }}');
            } catch(e) {
                console.log('Bridge setCurrentShipment not available:', e);
            }
        }
    </script>
    @endpush
</x-app-layout>
