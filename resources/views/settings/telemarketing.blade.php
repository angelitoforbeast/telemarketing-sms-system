<x-app-layout>
    <x-slot name="title">Telemarketing Settings</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Telemarketing Settings</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Auto-Call Settings --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Auto-Call Mode</h3>
                <p class="text-sm text-gray-500 mb-4">When enabled, the system will automatically dial the next number after an agent saves a call disposition.</p>

                <form method="POST" action="{{ route('settings.telemarketing.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg mb-4">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Enable Auto-Call</p>
                            <p class="text-xs text-gray-500">Agents will see a countdown before the next call is auto-dialed</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="auto_call_enabled" value="0">
                            <input type="checkbox" name="auto_call_enabled" value="1"
                                   class="sr-only peer"
                                   {{ $settings->auto_call_enabled ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>

                    <div class="mb-4">
                        <label for="auto_call_delay" class="block text-sm font-medium text-gray-700 mb-1">Auto-Call Delay (seconds)</label>
                        <p class="text-xs text-gray-500 mb-2">How many seconds to wait before auto-dialing the next number. Agent can cancel during countdown.</p>
                        <select name="auto_call_delay" id="auto_call_delay" class="border-gray-300 rounded-md shadow-sm text-sm w-32">
                            @foreach([3, 5, 7, 10, 15] as $sec)
                                <option value="{{ $sec }}" {{ $settings->auto_call_delay == $sec ? 'selected' : '' }}>{{ $sec }} sec</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="queue_mode" class="block text-sm font-medium text-gray-700 mb-1">Queue Mode</label>
                        <p class="text-xs text-gray-500 mb-2">How waybills are distributed to telemarketers. Pre-Assigned divides equally upfront. Shared Queue lets agents grab the next available. Hybrid assigns first, then agents grab from the pool when done.</p>
                        <select name="queue_mode" id="queue_mode" class="border-gray-300 rounded-md shadow-sm text-sm w-64">
                            <option value="pre_assigned" {{ $settings->queue_mode == 'pre_assigned' ? 'selected' : '' }}>Pre-Assigned (Equal Divide)</option>
                            <option value="shared_queue" {{ $settings->queue_mode == 'shared_queue' ? 'selected' : '' }}>Shared Queue (Grab Next)</option>
                            <option value="hybrid" {{ $settings->queue_mode == 'hybrid' ? 'selected' : '' }}>Hybrid (Assigned + Grab)</option>
                        </select>
                        <div id="queue-mode-warning" class="mt-2 hidden bg-amber-50 border border-amber-300 rounded-lg p-3">
                            <p class="text-sm font-semibold text-amber-800">
                                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                Warning: Changing Queue Mode
                            </p>
                            <p class="text-xs text-amber-700 mt-1" id="queue-mode-warning-text"></p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition">
                            Save Auto-Call Settings
                        </button>
                    </div>
                </form>
            </div>

            {{-- Call Recording Mode --}}
            @can('telemarketing.manage-recording-mode')
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Call Recording Mode</h3>
                <p class="text-sm text-gray-500 mb-4">Control how call recordings are captured. Auto mode uses the Android app to automatically record and upload. Manual mode lets agents upload recordings themselves. Both mode enables both options.</p>

                <form method="POST" action="{{ route('settings.telemarketing.update') }}">
                    @csrf
                    @method('PUT')

                    {{-- Pass existing settings so they don't get wiped --}}
                    <input type="hidden" name="auto_call_enabled" value="{{ $settings->auto_call_enabled ? '1' : '0' }}">
                    <input type="hidden" name="auto_call_delay" value="{{ $settings->auto_call_delay }}">
                    <input type="hidden" name="queue_mode" value="{{ $settings->queue_mode }}">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        {{-- Auto --}}
                        <label class="relative flex flex-col items-center p-4 border-2 rounded-lg cursor-pointer transition
                            {{ $settings->recording_mode == 'auto' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}">
                            <input type="radio" name="recording_mode" value="auto" class="sr-only peer" {{ $settings->recording_mode == 'auto' ? 'checked' : '' }}>
                            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full {{ $settings->recording_mode == 'auto' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-500' }}">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-900">Auto</p>
                            <p class="text-xs text-gray-500 text-center mt-1">Android app auto-records and uploads calls</p>
                            <div class="absolute top-2 right-2 {{ $settings->recording_mode == 'auto' ? '' : 'hidden' }}" id="check-auto">
                                <svg class="w-5 h-5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            </div>
                        </label>

                        {{-- Manual --}}
                        <label class="relative flex flex-col items-center p-4 border-2 rounded-lg cursor-pointer transition
                            {{ $settings->recording_mode == 'manual' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}">
                            <input type="radio" name="recording_mode" value="manual" class="sr-only peer" {{ $settings->recording_mode == 'manual' ? 'checked' : '' }}>
                            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full {{ $settings->recording_mode == 'manual' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-500' }}">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-900">Manual</p>
                            <p class="text-xs text-gray-500 text-center mt-1">Agents manually upload recording files after calls</p>
                            <div class="absolute top-2 right-2 {{ $settings->recording_mode == 'manual' ? '' : 'hidden' }}" id="check-manual">
                                <svg class="w-5 h-5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            </div>
                        </label>

                        {{-- Both --}}
                        <label class="relative flex flex-col items-center p-4 border-2 rounded-lg cursor-pointer transition
                            {{ $settings->recording_mode == 'both' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}">
                            <input type="radio" name="recording_mode" value="both" class="sr-only peer" {{ $settings->recording_mode == 'both' ? 'checked' : '' }}>
                            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full {{ $settings->recording_mode == 'both' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-500' }}">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-900">Both</p>
                            <p class="text-xs text-gray-500 text-center mt-1">Auto-record via app + manual upload option available</p>
                            <div class="absolute top-2 right-2 {{ $settings->recording_mode == 'both' ? '' : 'hidden' }}" id="check-both">
                                <svg class="w-5 h-5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            </div>
                        </label>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition">
                            Save Recording Mode
                        </button>
                    </div>
                </form>
            </div>
            @endcan

            {{-- Recording Enforcement --}}
            @can('telemarketing.manage-recording-mode')
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Recording Enforcement</h3>
                <p class="text-sm text-gray-500 mb-4">When enabled, agents must have a call recording attached before they can save a call log. Dispositions marked as exempt (e.g., No Answer) can be saved without a recording.</p>
                <form method="POST" action="{{ route('settings.telemarketing.update') }}">
                    @csrf
                    @method('PUT')
                    {{-- Pass existing settings so they don't get wiped --}}
                    <input type="hidden" name="auto_call_enabled" value="{{ $settings->auto_call_enabled ? '1' : '0' }}">
                    <input type="hidden" name="auto_call_delay" value="{{ $settings->auto_call_delay }}">
                    <input type="hidden" name="queue_mode" value="{{ $settings->queue_mode }}">
                    <input type="hidden" name="recording_mode" value="{{ $settings->recording_mode }}">

                    {{-- Toggle --}}
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg mb-6">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Require Recording Before Save</p>
                            <p class="text-xs text-gray-500">Agents cannot save a call log without an attached recording (unless disposition is exempt)</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="require_recording" value="0">
                            <input type="checkbox" name="require_recording" value="1" class="sr-only peer" id="require_recording_toggle" {{ $settings->require_recording ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>

                    {{-- Enforcement Details (shown when toggle is on) --}}
                    <div id="enforcement-details" class="{{ $settings->require_recording ? '' : 'hidden' }}">
                        {{-- Upload Timeout --}}
                        <div class="mb-6">
                            <label for="recording_upload_timeout" class="block text-sm font-medium text-gray-700 mb-1">Auto-Upload Timeout</label>
                            <p class="text-xs text-gray-500 mb-2">How long to wait for the auto-upload to complete before showing the manual upload option.</p>
                            <select name="recording_upload_timeout" id="recording_upload_timeout" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="15" {{ $settings->recording_upload_timeout == 15 ? 'selected' : '' }}>15 seconds</option>
                                <option value="30" {{ $settings->recording_upload_timeout == 30 ? 'selected' : '' }}>30 seconds</option>
                                <option value="45" {{ $settings->recording_upload_timeout == 45 ? 'selected' : '' }}>45 seconds</option>
                                <option value="60" {{ $settings->recording_upload_timeout == 60 ? 'selected' : '' }}>60 seconds</option>
                            </select>
                        </div>

                        {{-- Exempt Dispositions --}}
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Exempt Dispositions</label>
                            <p class="text-xs text-gray-500 mb-3">Select dispositions that do NOT require a recording (e.g., when there was no actual conversation).</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @php
                                    $exemptIds = $settings->recording_exempt_dispositions ?? [];
                                @endphp
                                @foreach($systemDispositions as $disp)
                                <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                                    <input type="checkbox"
                                           name="recording_exempt_dispositions[]"
                                           value="{{ $disp->id }}"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                                           {{ in_array($disp->id, $exemptIds) ? 'checked' : '' }}>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-800">{{ $disp->name }}</p>
                                        @if($disp->is_final)
                                        <span class="text-[10px] text-gray-400">(Final)</span>
                                        @endif
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition">
                            Save Recording Enforcement
                        </button>
                    </div>
                </form>
            </div>
            @endcan

            {{-- Disposition Mapping --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Disposition Mapping</h3>
                        <p class="text-sm text-gray-500 mt-1">Configure which dispositions are available per shipment status. Uncheck to hide a disposition for that status.</p>
                    </div>
                    <div class="flex space-x-2">
                        <button type="button" onclick="resetToDefaults()" class="text-xs text-gray-500 hover:text-gray-700 px-3 py-1.5 border border-gray-300 rounded-md hover:bg-gray-50 transition">
                            Reset to Defaults
                        </button>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.telemarketing.update-mapping') }}" id="mapping-form">
                    @csrf
                    @method('PUT')

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="border-b-2 border-gray-200">
                                    <th class="text-left py-2 px-2 font-semibold text-gray-700 sticky left-0 bg-white min-w-[160px]">Disposition</th>
                                    @foreach($statuses as $status)
                                        <th class="text-center py-2 px-1 font-semibold text-gray-700 min-w-[80px]">
                                            <span class="block">{{ $status->name }}</span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dispositions as $disposition)
                                    @php
                                        $colorDot = [
                                            'green' => 'bg-green-500', 'blue' => 'bg-blue-500', 'red' => 'bg-red-500',
                                            'orange' => 'bg-orange-500', 'yellow' => 'bg-yellow-500', 'purple' => 'bg-purple-500',
                                            'emerald' => 'bg-emerald-500', 'gray' => 'bg-gray-500',
                                        ][$disposition->color] ?? 'bg-gray-500';
                                    @endphp
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-2 px-2 sticky left-0 bg-white">
                                            <div class="flex items-center space-x-1.5">
                                                <span class="w-2 h-2 rounded-full {{ $colorDot }} flex-shrink-0"></span>
                                                <span class="font-medium text-gray-800">{{ $disposition->name }}</span>
                                                @if($disposition->is_final)
                                                    <span class="text-[9px] text-gray-400">(F)</span>
                                                @endif
                                            </div>
                                        </td>
                                        @foreach($statuses as $status)
                                            @php
                                                $isChecked = isset($currentMapping[$status->id]) && in_array($disposition->id, $currentMapping[$status->id]);
                                                $isDefault = isset($defaultMapping[$status->id]) && in_array($disposition->id, $defaultMapping[$status->id]);
                                            @endphp
                                            <td class="py-2 px-1 text-center">
                                                <input type="checkbox"
                                                       name="mapping[{{ $status->id }}][]"
                                                       value="{{ $disposition->id }}"
                                                       data-default="{{ $isDefault ? '1' : '0' }}"
                                                       class="mapping-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                                                       {{ $isChecked ? 'checked' : '' }}>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <p class="text-xs text-gray-400">
                            <span class="font-medium">(F)</span> = Final disposition (shipment won't be called again)
                        </p>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition">
                            Save Disposition Mapping
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    @push('scripts')
    <script>

        // Queue mode change warning
        var originalQueueMode = document.getElementById('queue_mode').value;
        var warnings = {
            'pre_assigned': 'Switching to Pre-Assigned mode. Only waybills assigned to each telemarketer will be visible to them. Unassigned waybills will not appear in anyone\'s queue. Consider running Auto-Assign after saving.',
            'shared_queue': 'Switching to Shared Queue mode. ALL telemarketers will see ALL available waybills. Existing assignments will remain but won\'t restrict visibility. Waybills will auto-assign when a telemarketer opens them.',
            'hybrid': 'Switching to Hybrid mode. Telemarketers will see their assigned waybills first, then unassigned waybills from the shared pool. Existing assignments are preserved.'
        };
        document.getElementById('queue_mode').addEventListener('change', function() {
            var warningDiv = document.getElementById('queue-mode-warning');
            var warningText = document.getElementById('queue-mode-warning-text');
            if (this.value !== originalQueueMode) {
                warningText.textContent = warnings[this.value] || '';
                warningDiv.classList.remove('hidden');
            } else {
                warningDiv.classList.add('hidden');
            }
        });
        // Recording mode radio button visual toggle
        document.querySelectorAll('input[name="recording_mode"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                // Reset all cards
                document.querySelectorAll('input[name="recording_mode"]').forEach(function(r) {
                    var label = r.closest('label');
                    var icon = label.querySelector('[class*="w-12"]');
                    label.classList.remove('border-indigo-500', 'bg-indigo-50');
                    label.classList.add('border-gray-200');
                    icon.classList.remove('bg-indigo-100', 'text-indigo-600');
                    icon.classList.add('bg-gray-100', 'text-gray-500');
                });
                document.getElementById('check-auto').classList.add('hidden');
                document.getElementById('check-manual').classList.add('hidden');
                document.getElementById('check-both').classList.add('hidden');
                // Highlight selected
                var selected = this.closest('label');
                var selectedIcon = selected.querySelector('[class*="w-12"]');
                selected.classList.remove('border-gray-200');
                selected.classList.add('border-indigo-500', 'bg-indigo-50');
                selectedIcon.classList.remove('bg-gray-100', 'text-gray-500');
                selectedIcon.classList.add('bg-indigo-100', 'text-indigo-600');
                document.getElementById('check-' + this.value).classList.remove('hidden');
            });
        });


        // Recording enforcement toggle
        var reqToggle = document.getElementById("require_recording_toggle");
        if (reqToggle) {
            reqToggle.addEventListener("change", function() {
                var details = document.getElementById("enforcement-details");
                if (this.checked) {
                    details.classList.remove("hidden");
                } else {
                    details.classList.add("hidden");
                }
            });
        }
        function resetToDefaults() {
            if (!confirm('Reset disposition mapping to system defaults? Your custom mapping will be removed.')) return;

            document.querySelectorAll('.mapping-checkbox').forEach(function(cb) {
                cb.checked = cb.dataset.default === '1';
            });
        }
    </script>
    @endpush
</x-app-layout>
