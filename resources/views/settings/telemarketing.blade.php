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
        function resetToDefaults() {
            if (!confirm('Reset disposition mapping to system defaults? Your custom mapping will be removed.')) return;

            document.querySelectorAll('.mapping-checkbox').forEach(function(cb) {
                cb.checked = cb.dataset.default === '1';
            });
        }
    </script>
    @endpush
</x-app-layout>
