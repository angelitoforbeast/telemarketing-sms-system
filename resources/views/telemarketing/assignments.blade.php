<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Assignment Management</h2>
            <a href="{{ route('telemarketing.dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md bg-gray-100 hover:bg-gray-200 transition">&larr; Back to Dashboard</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            <div id="flash-container">
                @if(session('success'))
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
                @endif
                @if(session('info'))
                    <div class="mb-4 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg">{{ session('info') }}</div>
                @endif
                @if(session('error'))
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
                @endif
            </div>

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- AGENT STATUS ASSIGNMENTS (NEW) --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="mb-6 bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Agent Status Assignments</h3>
                    <p class="text-sm text-gray-500 mt-1">Set which shipment statuses each telemarketer handles. Agents will only see shipments matching their assigned statuses. Leave blank = all statuses.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent</th>
                                @foreach($statuses as $status)
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">{{ $status->name }}</th>
                                @endforeach
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($telemarketers as $tm)
                                <tr class="hover:bg-gray-50" id="agent-row-{{ $tm->id }}">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 bg-indigo-100 rounded-full flex items-center justify-center">
                                                <span class="text-xs font-bold text-indigo-600">{{ strtoupper(substr($tm->name, 0, 2)) }}</span>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900">{{ $tm->name }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    @foreach($statuses as $status)
                                        <td class="px-3 py-3 text-center">
                                            <input type="checkbox"
                                                   class="agent-status-cb rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                   data-agent-id="{{ $tm->id }}"
                                                   data-status-id="{{ $status->id }}"
                                                   {{ in_array($status->id, $agentStatusMap[$tm->id] ?? []) ? 'checked' : '' }}
                                                   onchange="markAgentDirty({{ $tm->id }})">
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-3 text-center">
                                        <button type="button"
                                                id="save-agent-{{ $tm->id }}"
                                                onclick="saveAgentStatuses({{ $tm->id }}, this)"
                                                class="hidden inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700 transition">
                                            <span class="btn-text">Save</span>
                                            <svg class="btn-spinner hidden animate-spin ml-1 h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        </button>
                                        <span id="saved-agent-{{ $tm->id }}" class="{{ !empty($agentStatusMap[$tm->id] ?? []) ? '' : 'hidden' }} text-xs text-green-600 font-medium">
                                            @if(!empty($agentStatusMap[$tm->id] ?? []))
                                                ✓ {{ count($agentStatusMap[$tm->id]) }} status{{ count($agentStatusMap[$tm->id]) > 1 ? 'es' : '' }}
                                            @else
                                                All
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                    <p class="text-xs text-gray-500">
                        <strong>Tip:</strong> If no checkboxes are selected for an agent, they can handle <strong>all</strong> statuses. Check specific statuses to restrict what an agent sees in their queue. Auto-assignment rules will also respect these settings.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Manual Assignment Panel --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Manual Assignment</h3>
                        <p class="text-sm text-gray-500 mt-1">Assign unassigned shipments to a telemarketer (<span id="unassigned-count">{{ $unassignedCount }}</span> unassigned)</p>
                    </div>
                    <div class="px-6 py-5">
                        <form id="manual-assign-form" onsubmit="return handleManualAssign(event)">
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="telemarketer_id" value="Assign To *" />
                                    <select name="telemarketer_id" id="telemarketer_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Select Telemarketer --</option>
                                        @foreach($telemarketers as $tm)
                                            <option value="{{ $tm->id }}">{{ $tm->name }} ({{ $tm->pending_count }} pending)</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <x-input-label for="status_id" value="Filter by Status (optional)" />
                                    <select name="status_id" id="status_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">Any Status</option>
                                        @foreach($statuses as $status)
                                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <x-input-label for="courier" value="Filter by Courier (optional)" />
                                    <select name="courier" id="courier" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">Any Courier</option>
                                        <option value="jnt">JNT</option>
                                        <option value="flash">Flash Express</option>
                                    </select>
                                </div>

                                <div>
                                    <x-input-label for="limit" value="Max Shipments to Assign" />
                                    <input type="number" name="limit" id="limit" value="100" min="1" max="5000" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>

                                <button type="submit" id="btn-manual-assign" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <span class="btn-text">Assign Shipments</span>
                                    <svg class="btn-spinner hidden animate-spin ml-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Auto-Assignment Rules Panel --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Auto-Assignment Rules</h3>
                        <p class="text-sm text-gray-500 mt-1">Configure rules for automatic shipment distribution</p>
                    </div>
                    <div class="px-6 py-5">

                        {{-- Existing Rules --}}
                        @if($rules->isNotEmpty())
                            <div class="space-y-3 mb-6">
                                @foreach($rules as $rule)
                                    <div class="flex items-center justify-between p-3 border rounded-lg {{ $rule->is_active ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }}">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $rule->name }}</p>
                                            <p class="text-xs text-gray-500">
                                                {{ ucfirst(str_replace('_', ' ', $rule->rule_type)) }} |
                                                {{ ucfirst(str_replace('_', ' ', $rule->assignment_method)) }} |
                                                Max {{ $rule->max_attempts }} attempts
                                            </p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <button type="button" onclick="runSingleRule({{ $rule->id }}, this)"
                                                class="text-xs px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 inline-flex items-center">
                                                <span class="btn-text">Run</span>
                                                <svg class="btn-spinner hidden animate-spin ml-1 h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                            </button>
                                            <form method="POST" action="{{ route('telemarketing.toggle-rule', $rule) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs px-2 py-1 rounded {{ $rule->is_active ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-green-100 text-green-700 hover:bg-green-200' }}">
                                                    {{ $rule->is_active ? 'Disable' : 'Enable' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('telemarketing.delete-rule', $rule) }}" class="inline" onsubmit="return confirm('Delete this rule?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Add New Rule --}}
                        <div class="border-t pt-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Add New Rule</h4>
                            <form method="POST" action="{{ route('telemarketing.store-rule') }}">
                                @csrf
                                <div class="space-y-3">
                                    <div>
                                        <x-input-label for="rule_name" value="Rule Name *" />
                                        <input type="text" name="name" id="rule_name" required placeholder="e.g., Return Shipments" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <x-input-label for="rule_type" value="Rule Type *" />
                                            <select name="rule_type" id="rule_type" required onchange="toggleRuleFields()" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                <option value="status_based">Status Based</option>
                                                <option value="delivered_age">Delivered Age</option>
                                            </select>
                                        </div>
                                        <div>
                                            <x-input-label for="assignment_method" value="Method *" />
                                            <select name="assignment_method" id="assignment_method" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                <option value="round_robin">Round Robin</option>
                                                <option value="workload_based">Workload Based</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div id="status-field">
                                        <x-input-label for="rule_status_id" value="Target Status" />
                                        <select name="status_id" id="rule_status_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">Any Status</option>
                                            @foreach($statuses as $status)
                                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div id="days-field" class="hidden">
                                        <x-input-label for="days_threshold" value="Days Since Delivery" />
                                        <input type="number" name="days_threshold" id="days_threshold" value="7" min="1" max="365" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <x-input-label for="max_attempts" value="Max Attempts" />
                                            <input type="number" name="max_attempts" id="max_attempts" value="5" min="1" max="50" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        </div>
                                        <div>
                                            <x-input-label for="priority" value="Priority (higher = first)" />
                                            <input type="number" name="priority" id="priority" value="0" min="0" max="100" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        </div>
                                    </div>

                                    <x-primary-button>Add Rule</x-primary-button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Telemarketer Workload Summary --}}
            <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Telemarketer Workload</h3>
                    <button type="button" id="btn-run-all-rules" onclick="runAllRules(this)"
                            class="inline-flex items-center px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition">
                        <svg class="w-4 h-4 mr-1 btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <svg class="btn-spinner hidden animate-spin mr-1 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span class="btn-text">Run Auto-Assign</span>
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agent</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assigned Statuses</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Pending</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Completed</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($telemarketers as $tm)
                                @php
                                    $agentStatuses = $agentStatusMap[$tm->id] ?? [];
                                    $statusNames = $agentStatuses
                                        ? $statuses->whereIn('id', $agentStatuses)->pluck('name')->toArray()
                                        : [];
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 bg-indigo-100 rounded-full flex items-center justify-center">
                                                <span class="text-xs font-bold text-indigo-600">{{ strtoupper(substr($tm->name, 0, 2)) }}</span>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900">{{ $tm->name }}</p>
                                                <p class="text-xs text-gray-500">{{ $tm->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if(empty($statusNames))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">All Statuses</span>
                                        @else
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($statusNames as $sn)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">{{ $sn }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ $tm->pending_count }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ $tm->completed_count }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <a href="{{ route('telemarketing.queue', ['telemarketer_id' => $tm->id]) }}" class="text-indigo-600 hover:text-indigo-900 text-sm mr-2">View Queue</a>
                                        <button type="button" onclick="unassignAll({{ $tm->id }}, '{{ addslashes($tm->name) }}', this)"
                                                class="text-red-600 hover:text-red-900 text-sm inline-flex items-center">
                                            <span class="btn-text">Unassign All</span>
                                            <svg class="btn-spinner hidden animate-spin ml-1 h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // ── Helper: show toast notification ──
        function showToast(message, type = 'success') {
            const container = document.getElementById('flash-container');
            const colors = {
                success: 'bg-green-50 border-green-200 text-green-700',
                info: 'bg-blue-50 border-blue-200 text-blue-700',
                error: 'bg-red-50 border-red-200 text-red-700',
            };
            const div = document.createElement('div');
            div.className = `mb-4 border px-4 py-3 rounded-lg ${colors[type] || colors.info} transition-opacity duration-500`;
            div.innerHTML = message;
            container.prepend(div);
            setTimeout(() => {
                div.style.opacity = '0';
                setTimeout(() => div.remove(), 500);
            }, 6000);
        }

        // ── Helper: toggle button loading state ──
        function setLoading(btn, loading) {
            const text = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.btn-spinner');
            const icon = btn.querySelector('.btn-icon');
            if (loading) {
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
                if (text) text.textContent = 'Processing...';
                if (spinner) spinner.classList.remove('hidden');
                if (icon) icon.classList.add('hidden');
            } else {
                btn.disabled = false;
                btn.classList.remove('opacity-75', 'cursor-not-allowed');
                if (spinner) spinner.classList.add('hidden');
                if (icon) icon.classList.remove('hidden');
            }
        }

        // ═══════════════════════════════════════════════════════
        //  AGENT STATUS ASSIGNMENTS (NEW)
        // ═══════════════════════════════════════════════════════

        // Track which agents have unsaved changes
        const dirtyAgents = new Set();

        function markAgentDirty(agentId) {
            dirtyAgents.add(agentId);
            const saveBtn = document.getElementById(`save-agent-${agentId}`);
            const savedLabel = document.getElementById(`saved-agent-${agentId}`);
            if (saveBtn) {
                saveBtn.classList.remove('hidden');
                saveBtn.classList.add('inline-flex');
            }
            if (savedLabel) savedLabel.classList.add('hidden');
        }

        async function saveAgentStatuses(agentId, btn) {
            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Saving...';

            // Collect checked status IDs for this agent
            const checkboxes = document.querySelectorAll(`.agent-status-cb[data-agent-id="${agentId}"]`);
            const statusIds = [];
            checkboxes.forEach(cb => {
                if (cb.checked) statusIds.push(parseInt(cb.dataset.statusId));
            });

            try {
                const res = await fetch('{{ route("telemarketing.sync-agent-statuses") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        telemarketer_id: agentId,
                        status_ids: statusIds,
                    }),
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    dirtyAgents.delete(agentId);

                    // Update the saved label
                    const savedLabel = document.getElementById(`saved-agent-${agentId}`);
                    if (savedLabel) {
                        if (statusIds.length > 0) {
                            savedLabel.textContent = `✓ ${statusIds.length} status${statusIds.length > 1 ? 'es' : ''}`;
                        } else {
                            savedLabel.textContent = 'All';
                        }
                        savedLabel.classList.remove('hidden');
                    }

                    // Hide save button
                    btn.classList.add('hidden');
                    btn.classList.remove('inline-flex');
                } else {
                    showToast(data.message || 'Failed to save.', 'error');
                }
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Save';
        }

        // ═══════════════════════════════════════════════════════
        //  EXISTING AJAX FUNCTIONS
        // ═══════════════════════════════════════════════════════

        // ── AJAX: Run all assignment rules ──
        async function runAllRules(btn) {
            if (!confirm('Run auto-assignment for all active rules?')) return;
            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Running...';

            try {
                const res = await fetch('{{ route("telemarketing.run-auto-assign") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'info');
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Run Auto-Assign';
            setTimeout(() => location.reload(), 3000);
        }

        // ── AJAX: Run a single rule ──
        async function runSingleRule(ruleId, btn) {
            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Running...';

            try {
                const res = await fetch('{{ route("telemarketing.run-auto-assign") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ rule_id: ruleId }),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'info');
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Run';
            setTimeout(() => location.reload(), 3000);
        }

        // ── AJAX: Manual assign ──
        async function handleManualAssign(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-manual-assign');
            const form = document.getElementById('manual-assign-form');

            const telemarketer = form.querySelector('[name=telemarketer_id]').value;
            if (!telemarketer) {
                showToast('Please select a telemarketer.', 'error');
                return false;
            }

            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Assigning...';

            try {
                const formData = new FormData(form);
                const body = {};
                formData.forEach((v, k) => { if (v) body[k] = v; });

                const res = await fetch('{{ route("telemarketing.manual-assign") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'info');
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Assign Shipments';
            setTimeout(() => location.reload(), 3000);
            return false;
        }

        // ── AJAX: Unassign all ──
        async function unassignAll(telemarketerId, name, btn) {
            if (!confirm(`Unassign all pending shipments from ${name}?`)) return;
            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Unassigning...';

            try {
                const res = await fetch('{{ route("telemarketing.unassign-all") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ telemarketer_id: telemarketerId }),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'info');
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Unassign All';
            setTimeout(() => location.reload(), 3000);
        }

        // ── Toggle rule type fields ──
        function toggleRuleFields() {
            const ruleType = document.getElementById('rule_type').value;
            document.getElementById('status-field').classList.toggle('hidden', ruleType === 'delivered_age');
            document.getElementById('days-field').classList.toggle('hidden', ruleType !== 'delivered_age');
        }
    </script>
    @endpush
</x-app-layout>
