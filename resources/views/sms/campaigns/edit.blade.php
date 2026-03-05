<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Campaign: {{ $campaign->name }}</h2>
            <a href="{{ route('sms.campaigns.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                    @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('sms.campaigns.update', $campaign) }}" id="campaignForm">
                @csrf
                @method('PUT')

                {{-- Campaign Name --}}
                <div class="bg-white shadow rounded-lg p-6 mb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Campaign Details</h3>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Campaign Name *</label>
                        <input type="text" name="name" value="{{ old('name', $campaign->name) }}" required
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g., For Return Reminder - Week 8">
                    </div>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $campaign->is_active) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600">Active</span>
                    </label>
                </div>

                {{-- SMS Template with Merge Tags --}}
                <div class="bg-white shadow rounded-lg p-6 mb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">SMS Message</h3>
                    <p class="text-xs text-gray-500 mb-3">Type <kbd class="px-1.5 py-0.5 bg-gray-100 border rounded text-xs font-mono">{</kbd> to insert custom fields.</p>

                    <div class="relative">
                        <textarea id="smsTemplate" name="sms_template" rows="5" required
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                            placeholder="Hi {consignee_name}, your parcel {waybill_no} worth P{cod_amount} is currently {status}.">{{ old('sms_template', $campaign->sms_template) }}</textarea>

                        <div id="tagDropdown" class="hidden absolute z-50 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto w-72"
                            style="left: 0; top: 0;">
                            @foreach($mergeTags as $tag => $label)
                                <button type="button" class="tag-option w-full text-left px-3 py-2 hover:bg-indigo-50 flex items-center gap-2 text-sm"
                                    data-tag="{{ $tag }}">
                                    <span class="text-indigo-600 font-mono text-xs bg-indigo-50 px-1.5 py-0.5 rounded">{{ '{' . $tag . '}' }}</span>
                                    <span class="text-gray-600">{{ $label }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach($mergeTags as $tag => $label)
                            <button type="button" class="quick-tag text-xs px-2 py-1 bg-gray-100 hover:bg-indigo-100 text-gray-600 hover:text-indigo-700 rounded transition"
                                data-tag="{{ $tag }}" title="{{ $label }}">{{ '{' . $tag . '}' }}</button>
                        @endforeach
                    </div>
                    <div class="mt-2 text-right">
                        <span id="charCount" class="text-xs text-gray-500">0 characters</span>
                        <span id="smsPartCount" class="text-xs text-gray-400 ml-2">(1 SMS part)</span>
                    </div>
                </div>

                {{-- Recipients --}}
                <div class="bg-white shadow rounded-lg p-6 mb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recipients</h3>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Recipient Type</label>
                        <div class="flex gap-4">
                            <label class="flex items-center">
                                <input type="radio" name="recipient_filter_type" value="dynamic" {{ old('recipient_filter_type', $campaign->recipient_filter_type) === 'dynamic' ? 'checked' : '' }}
                                    class="text-indigo-600 focus:ring-indigo-500" onchange="toggleFilterType()">
                                <span class="ml-2 text-sm">Dynamic (auto-query based on filters)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="recipient_filter_type" value="fixed" {{ old('recipient_filter_type', $campaign->recipient_filter_type) === 'fixed' ? 'checked' : '' }}
                                    class="text-indigo-600 focus:ring-indigo-500" onchange="toggleFilterType()">
                                <span class="ml-2 text-sm">Fixed (same list every time)</span>
                            </label>
                        </div>
                    </div>

                    @php
                        $recipientFilters = $campaign->recipient_filters ?? [];
                        $savedStatuses = old('filter_statuses', $recipientFilters['statuses'] ?? []);
                        if (is_string($savedStatuses)) $savedStatuses = json_decode($savedStatuses, true) ?? [];
                        $savedStatuses = array_map('intval', $savedStatuses);
                        $savedDateRange = old('filter_date_range_days', $recipientFilters['date_range_days'] ?? 7);
                        $savedExclude = old('filter_exclude_already_sent', $recipientFilters['exclude_already_sent'] ?? true);
                    @endphp

                    <div id="dynamicFilters">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Shipment Status Filter</label>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach($statuses as $status)
                                    <label class="flex items-center p-2 border rounded hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" name="filter_statuses[]" value="{{ $status->id }}"
                                            {{ in_array($status->id, $savedStatuses) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            onchange="previewRecipients()">
                                        <span class="ml-2 text-sm">{{ $status->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date Range (last N days)</label>
                                <input type="number" name="filter_date_range_days" value="{{ $savedDateRange }}"
                                    min="1" max="365" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    onchange="previewRecipients()">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Daily Send Limit</label>
                                <input type="number" name="daily_send_limit" value="{{ old('daily_send_limit', $campaign->daily_send_limit) }}"
                                    min="1" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="Unlimited">
                            </div>
                        </div>

                        <label class="flex items-center mb-4">
                            <input type="checkbox" name="filter_exclude_already_sent" value="1"
                                {{ $savedExclude ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                onchange="previewRecipients()">
                            <span class="ml-2 text-sm text-gray-600">Exclude recipients who already received this campaign</span>
                        </label>

                        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 flex items-center justify-between">
                            <span class="text-sm text-indigo-700">Estimated recipients:</span>
                            <span id="recipientCount" class="text-lg font-bold text-indigo-700">--</span>
                        </div>
                    </div>

                    <input type="hidden" name="trigger_status_id" id="triggerStatusId" value="{{ old('trigger_status_id', $campaign->trigger_status_id) }}">
                </div>

                {{-- Sending Method --}}
                <div class="bg-white shadow rounded-lg p-6 mb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Sending Method</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="relative flex items-start p-4 border-2 rounded-lg cursor-pointer hover:border-indigo-300 transition" id="methodSim">
                            <input type="radio" name="sending_method" value="sim_based" {{ old('sending_method', $campaign->sending_method) === 'sim_based' ? 'checked' : '' }}
                                class="mt-0.5 text-indigo-600 focus:ring-indigo-500" onchange="toggleMethod()">
                            <div class="ml-3">
                                <span class="block text-sm font-medium text-gray-900">SIM-Based (Phone)</span>
                                <span class="block text-xs text-gray-500 mt-1">Send via registered Android phones. Free, uses SIM load/plan.</span>
                            </div>
                        </label>
                        <label class="relative flex items-start p-4 border-2 rounded-lg cursor-pointer hover:border-indigo-300 transition opacity-60" id="methodGateway">
                            <input type="radio" name="sending_method" value="gateway" {{ old('sending_method', $campaign->sending_method) === 'gateway' ? 'checked' : '' }}
                                class="mt-0.5 text-indigo-600 focus:ring-indigo-500" onchange="toggleMethod()">
                            <div class="ml-3">
                                <span class="block text-sm font-medium text-gray-900">SMS Gateway</span>
                                <span class="block text-xs text-gray-500 mt-1">Send via API (InfoTxt, etc.). <em>Coming soon</em></span>
                            </div>
                        </label>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Throttle Delay (seconds between each SMS)</label>
                        <input type="number" name="throttle_delay_seconds" value="{{ old('throttle_delay_seconds', $campaign->throttle_delay_seconds ?? 10) }}"
                            min="1" max="300" class="w-40 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="text-xs text-gray-500 mt-1">Recommended: 10-30 seconds for SIM-based to avoid carrier blocking.</p>
                    <div class="mt-4" id="operatorAssignment">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assign to SMS Operator</label>
                        <select name="assigned_operator_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Auto-assign to all operators (round-robin) --</option>
                            @foreach($smsOperators as $op)
                                <option value="{{ $op->id }}" {{ old('assigned_operator_id', $campaign->assigned_operator_id) == $op->id ? 'selected' : '' }}>
                                    {{ $op->name }} ({{ $op->email }})
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Choose which SMS Operator account will handle this campaign's messages.</p>
                    </div>
                    </div>
                </div>

                {{-- Schedule --}}
                <div class="bg-white shadow rounded-lg p-6 mb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Schedule</h3>
                    <div class="space-y-3">
                        <label class="flex items-start p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="schedule_type" value="immediate" {{ old('schedule_type', $campaign->schedule_type) === 'immediate' ? 'checked' : '' }}
                                class="mt-0.5 text-indigo-600 focus:ring-indigo-500" onchange="toggleSchedule()">
                            <div class="ml-3">
                                <span class="text-sm font-medium text-gray-900">Send Immediately</span>
                                <span class="block text-xs text-gray-500">Start sending as soon as you click "Update Campaign"</span>
                            </div>
                        </label>
                        <label class="flex items-start p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="schedule_type" value="scheduled" {{ old('schedule_type', $campaign->schedule_type) === 'scheduled' ? 'checked' : '' }}
                                class="mt-0.5 text-indigo-600 focus:ring-indigo-500" onchange="toggleSchedule()">
                            <div class="ml-3">
                                <span class="text-sm font-medium text-gray-900">Schedule (One-Time)</span>
                                <span class="block text-xs text-gray-500">Send at a specific date and time</span>
                            </div>
                        </label>
                        <label class="flex items-start p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="schedule_type" value="recurring_daily" {{ old('schedule_type', $campaign->schedule_type) === 'recurring_daily' ? 'checked' : '' }}
                                class="mt-0.5 text-indigo-600 focus:ring-indigo-500" onchange="toggleSchedule()">
                            <div class="ml-3">
                                <span class="text-sm font-medium text-gray-900">Recurring Daily</span>
                                <span class="block text-xs text-gray-500">Send every day at a specific time</span>
                            </div>
                        </label>
                        <label class="flex items-start p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="schedule_type" value="recurring_hourly" {{ old('schedule_type', $campaign->schedule_type) === 'recurring_hourly' ? 'checked' : '' }}
                                class="mt-0.5 text-indigo-600 focus:ring-indigo-500" onchange="toggleSchedule()">
                            <div class="ml-3">
                                <span class="text-sm font-medium text-gray-900">Recurring Hourly</span>
                                <span class="block text-xs text-gray-500">Send every N hours during the day</span>
                            </div>
                        </label>
                        <label class="flex items-start p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="schedule_type" value="custom_cron" {{ old('schedule_type', $campaign->schedule_type) === 'custom_cron' ? 'checked' : '' }}
                                class="mt-0.5 text-indigo-600 focus:ring-indigo-500" onchange="toggleSchedule()">
                            <div class="ml-3">
                                <span class="text-sm font-medium text-gray-900">Custom Schedule</span>
                                <span class="block text-xs text-gray-500">Advanced: specific days and times</span>
                            </div>
                        </label>
                    </div>

                    <div id="scheduleFields" class="mt-4 hidden">
                        <div id="scheduledAtField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Send Date & Time</label>
                            <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $campaign->scheduled_at ? \Carbon\Carbon::parse($campaign->scheduled_at)->format('Y-m-d\TH:i') : '') }}"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div id="recurringTimeField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Daily Send Time</label>
                            <input type="time" name="recurring_time" value="{{ old('recurring_time', $campaign->recurring_time ?? '09:00') }}"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div id="recurringHoursField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Send Every (hours)</label>
                            <select name="recurring_interval_hours" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach([1,2,3,4,6,8,12] as $h)
                                    <option value="{{ $h }}" {{ old('recurring_interval_hours', $campaign->recurring_interval_hours) == $h ? 'selected' : '' }}>Every {{ $h }} hour{{ $h > 1 ? 's' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="cronField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cron Expression</label>
                            <input type="text" name="cron_expression" value="{{ old('cron_expression', $campaign->cron_expression) }}"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono"
                                placeholder="0 9 * * 1,3,5">
                            <p class="text-xs text-gray-500 mt-1">Format: minute hour day-of-month month day-of-week. Example: <code>0 9 * * 1,3,5</code> = Mon/Wed/Fri at 9AM</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('sms.campaigns.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 shadow-sm">Update Campaign</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const textarea = document.getElementById('smsTemplate');
        const dropdown = document.getElementById('tagDropdown');
        let tagSearchStart = -1;

        textarea.addEventListener('input', function() {
            updateCharCount();
            const val = this.value;
            const pos = this.selectionStart;
            const beforeCursor = val.substring(0, pos);
            const lastBrace = beforeCursor.lastIndexOf('{');

            if (lastBrace >= 0 && !beforeCursor.substring(lastBrace).includes('}')) {
                tagSearchStart = lastBrace;
                showTagDropdown(beforeCursor.substring(lastBrace + 1).toLowerCase());
            } else {
                hideTagDropdown();
            }
        });

        textarea.addEventListener('keydown', function(e) {
            if (!dropdown.classList.contains('hidden')) {
                if (e.key === 'Escape') { hideTagDropdown(); e.preventDefault(); }
                else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); navigateDropdown(e.key === 'ArrowDown' ? 1 : -1); }
                else if (e.key === 'Enter' || e.key === 'Tab') {
                    const active = dropdown.querySelector('.tag-option.bg-indigo-100');
                    if (active) { e.preventDefault(); insertTag(active.dataset.tag); }
                }
            }
        });

        function showTagDropdown(search) {
            const options = dropdown.querySelectorAll('.tag-option');
            let visible = 0;
            options.forEach(opt => {
                const match = opt.dataset.tag.includes(search) || opt.textContent.toLowerCase().includes(search);
                opt.classList.toggle('hidden', !match);
                opt.classList.remove('bg-indigo-100');
                if (match) visible++;
            });
            if (visible > 0) {
                dropdown.style.top = (textarea.offsetHeight + 4) + 'px';
                dropdown.classList.remove('hidden');
                const first = dropdown.querySelector('.tag-option:not(.hidden)');
                if (first) first.classList.add('bg-indigo-100');
            } else { hideTagDropdown(); }
        }

        function hideTagDropdown() { dropdown.classList.add('hidden'); tagSearchStart = -1; }

        function navigateDropdown(dir) {
            const opts = [...dropdown.querySelectorAll('.tag-option:not(.hidden)')];
            const cur = opts.findIndex(o => o.classList.contains('bg-indigo-100'));
            opts.forEach(o => o.classList.remove('bg-indigo-100'));
            let next = (cur + dir + opts.length) % opts.length;
            opts[next].classList.add('bg-indigo-100');
            opts[next].scrollIntoView({ block: 'nearest' });
        }

        function insertTag(tag) {
            const val = textarea.value, pos = textarea.selectionStart;
            const before = val.substring(0, tagSearchStart);
            const after = val.substring(pos);
            textarea.value = before + '{' + tag + '}' + after;
            const newPos = tagSearchStart + tag.length + 2;
            textarea.setSelectionRange(newPos, newPos);
            textarea.focus();
            hideTagDropdown();
            updateCharCount();
        }

        document.querySelectorAll('.quick-tag').forEach(btn => {
            btn.addEventListener('click', function() {
                const tag = this.dataset.tag, pos = textarea.selectionStart, val = textarea.value;
                const insert = '{' + tag + '}';
                textarea.value = val.substring(0, pos) + insert + val.substring(pos);
                textarea.setSelectionRange(pos + insert.length, pos + insert.length);
                textarea.focus(); updateCharCount();
            });
        });

        document.querySelectorAll('.tag-option').forEach(opt => {
            opt.addEventListener('click', function() { insertTag(this.dataset.tag); });
        });

        document.addEventListener('click', function(e) {
            if (!textarea.contains(e.target) && !dropdown.contains(e.target)) hideTagDropdown();
        });

        function updateCharCount() {
            const len = textarea.value.length;
            document.getElementById('charCount').textContent = len + ' characters';
            const parts = len <= 160 ? 1 : Math.ceil(len / 153);
            document.getElementById('smsPartCount').textContent = '(' + parts + ' SMS part' + (parts > 1 ? 's' : '') + ')';
        }
        updateCharCount();

        function toggleSchedule() {
            const type = document.querySelector('input[name="schedule_type"]:checked')?.value;
            const fields = document.getElementById('scheduleFields');
            ['scheduledAtField','recurringTimeField','recurringHoursField','cronField'].forEach(id => document.getElementById(id).classList.add('hidden'));
            const map = { scheduled: 'scheduledAtField', recurring_daily: 'recurringTimeField', recurring_hourly: 'recurringHoursField', custom_cron: 'cronField' };
            if (map[type]) { fields.classList.remove('hidden'); document.getElementById(map[type]).classList.remove('hidden'); }
            else { fields.classList.add('hidden'); }
        }
        toggleSchedule();

        function toggleMethod() {
            const method = document.querySelector('input[name="sending_method"]:checked')?.value;
            document.getElementById('methodSim').classList.toggle('border-indigo-500', method === 'sim_based');
            document.getElementById('methodGateway').classList.toggle('border-indigo-500', method === 'gateway');
        }
        toggleMethod();

        function toggleFilterType() {
            const type = document.querySelector('input[name="recipient_filter_type"]:checked')?.value;
            document.getElementById('dynamicFilters').style.display = type === 'dynamic' ? 'block' : 'none';
        }
        toggleFilterType();

        let previewTimeout;
        function previewRecipients() {
            clearTimeout(previewTimeout);
            previewTimeout = setTimeout(() => {
                const statuses = [...document.querySelectorAll('input[name="filter_statuses[]"]:checked')].map(c => c.value);
                const days = document.querySelector('input[name="filter_date_range_days"]')?.value;
                fetch('{{ route("sms.campaigns.preview-recipients") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ filter_statuses: statuses, filter_date_range_days: days }),
                })
                .then(r => r.json())
                .then(data => {
                    document.getElementById('recipientCount').textContent = data.count.toLocaleString();
                    document.getElementById('triggerStatusId').value = statuses[0] || '';
                })
                .catch(() => { document.getElementById('recipientCount').textContent = '--'; });
            }, 500);
        }
        previewRecipients();
    </script>
</x-app-layout>
