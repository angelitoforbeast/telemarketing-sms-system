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
            {{-- AGENT STATUS ASSIGNMENTS --}}
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
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                @foreach($statuses as $status)
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">{{ $status->name }}</th>
                                @endforeach
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($telemarketers as $tm)
                                <tr class="hover:bg-gray-50 {{ !$tm->is_telemarketing_active ? 'opacity-50' : '' }}" id="agent-row-{{ $tm->id }}">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 {{ $tm->is_telemarketing_active ? 'bg-indigo-100' : 'bg-gray-200' }} rounded-full flex items-center justify-center">
                                                <span class="text-xs font-bold {{ $tm->is_telemarketing_active ? 'text-indigo-600' : 'text-gray-400' }}">{{ strtoupper(substr($tm->name, 0, 2)) }}</span>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900">{{ $tm->name }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        {{-- Active/Inactive Toggle --}}
                                        <button type="button"
                                                onclick="toggleAgentActive({{ $tm->id }}, '{{ addslashes($tm->name) }}', this)"
                                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 {{ $tm->is_telemarketing_active ? 'bg-green-500' : 'bg-gray-300' }}"
                                                data-active="{{ $tm->is_telemarketing_active ? '1' : '0' }}"
                                                role="switch"
                                                aria-checked="{{ $tm->is_telemarketing_active ? 'true' : 'false' }}"
                                                title="{{ $tm->is_telemarketing_active ? 'Active — click to deactivate' : 'Inactive — click to activate' }}">
                                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $tm->is_telemarketing_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                        </button>
                                        <span class="block text-xs mt-0.5 {{ $tm->is_telemarketing_active ? 'text-green-600' : 'text-gray-400' }}" id="active-label-{{ $tm->id }}">
                                            {{ $tm->is_telemarketing_active ? 'Active' : 'Inactive' }}
                                        </span>
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
                        <strong>Tip:</strong> Toggle the switch to set an agent Active/Inactive. Inactive agents won't receive new assignments. Check specific statuses to restrict what an agent sees.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Manual Assignment Panel --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Manual Assignment</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            Assign unassigned shipments to a telemarketer
                            (<span id="unassigned-count">{{ $unassignedCount }}</span> unassigned
                            @if($onCooldownCount > 0)
                                , <span class="text-amber-600">{{ $onCooldownCount }} on cooldown</span>
                            @endif)
                        </p>
                    </div>
                    <div class="px-6 py-5">
                        <form id="manual-assign-form" onsubmit="return handleManualAssign(event)">
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="telemarketer_id" value="Assign To *" />
                                    <select name="telemarketer_id" id="telemarketer_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Select Telemarketer --</option>
                                        @foreach($telemarketers as $tm)
                                            <option value="{{ $tm->id }}"
                                                    data-active="{{ $tm->is_telemarketing_active ? '1' : '0' }}"
                                                    data-statuses="{{ json_encode($agentStatusMap[$tm->id] ?? []) }}">
                                                {{ $tm->name }} ({{ $tm->pending_count }} pending) {{ !$tm->is_telemarketing_active ? '⛔ INACTIVE' : '' }}
                                            </option>
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

                                {{-- Strict Assign Warning --}}
                                <div id="strict-assign-warning" class="hidden bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-lg text-sm">
                                    <strong>⚠ Status Mismatch:</strong> <span id="strict-assign-msg"></span>
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

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- STATUS TRANSITION RULES --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Status Transition Rules</h3>
                    <p class="text-sm text-gray-500 mt-1">Define what happens when a shipment's status changes during import. Controls auto-reassignment, cooldowns, and attempt resets.</p>
                </div>
                <div class="px-6 py-5">

                    {{-- Existing Transition Rules --}}
                    @if($transitionRules->isNotEmpty())
                        <div class="overflow-x-auto mb-6">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">From Status</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">To Status</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Reset Attempts</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Cooldown</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($transitionRules as $tr)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2">
                                                @if($tr->fromStatus)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">{{ $tr->fromStatus->name }}</span>
                                                @else
                                                    <span class="text-gray-400 text-xs">Any</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2">
                                                @if($tr->toStatus)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">{{ $tr->toStatus->name }}</span>
                                                @else
                                                    <span class="text-gray-400 text-xs">Any</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2">
                                                @php
                                                    $actionColors = [
                                                        'auto_reassign' => 'bg-green-100 text-green-700',
                                                        'auto_unassign' => 'bg-yellow-100 text-yellow-700',
                                                        'mark_completed' => 'bg-blue-100 text-blue-700',
                                                        'no_action' => 'bg-gray-100 text-gray-600',
                                                    ];
                                                @endphp
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $actionColors[$tr->action] ?? 'bg-gray-100 text-gray-600' }}">
                                                    {{ ucfirst(str_replace('_', ' ', $tr->action)) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                @if($tr->reset_attempts)
                                                    <span class="text-green-600">✓ Yes</span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                @if($tr->cooldown_days > 0)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">{{ $tr->cooldown_days }} day{{ $tr->cooldown_days > 1 ? 's' : '' }}</span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $tr->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                                    {{ $tr->is_active ? 'Active' : 'Disabled' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <div class="flex items-center justify-center space-x-2">
                                                    <form method="POST" action="{{ route('telemarketing.toggle-transition-rule', $tr) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="text-xs px-2 py-1 rounded {{ $tr->is_active ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-green-100 text-green-700 hover:bg-green-200' }}">
                                                            {{ $tr->is_active ? 'Disable' : 'Enable' }}
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('telemarketing.delete-transition-rule', $tr) }}" class="inline" onsubmit="return confirm('Delete this transition rule?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 mb-4">No transition rules configured yet. Add rules below to control what happens when shipment statuses change.</p>
                    @endif

                    {{-- Add New Transition Rule --}}
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Add Transition Rule</h4>
                        <form method="POST" action="{{ route('telemarketing.store-transition-rule') }}">
                            @csrf
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label for="from_status_id" value="From Status" />
                                        <select name="from_status_id" id="from_status_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">Any Status</option>
                                            @foreach($statuses as $status)
                                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label for="to_status_id" value="To Status" />
                                        <select name="to_status_id" id="to_status_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">Any Status</option>
                                            @foreach($statuses as $status)
                                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <x-input-label for="tr_action" value="Action *" />
                                        <select name="action" id="tr_action" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="auto_reassign">Auto Reassign (to correct agent)</option>
                                            <option value="auto_unassign">Auto Unassign (back to pool)</option>
                                            <option value="mark_completed">Mark Completed (no more calls)</option>
                                            <option value="no_action">No Action (keep as is)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label for="cooldown_days" value="Cooldown (days)" />
                                        <input type="number" name="cooldown_days" id="cooldown_days" value="0" min="0" max="365" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <p class="text-xs text-gray-400 mt-1">0 = no cooldown</p>
                                    </div>
                                    <div>
                                        <x-input-label for="tr_priority" value="Priority" />
                                        <input type="number" name="priority" id="tr_priority" value="0" min="0" max="100" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                </div>

                                <div class="flex items-center space-x-6">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="reset_attempts" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span class="ml-2 text-sm text-gray-700">Reset attempt count to 0</span>
                                    </label>
                                </div>

                                <div>
                                    <x-input-label for="tr_description" value="Description (optional)" />
                                    <input type="text" name="description" id="tr_description" placeholder="e.g., When For Return becomes Returned, reassign to Returned agent" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>

                                <x-primary-button>Add Transition Rule</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                    <p class="text-xs text-gray-500">
                        <strong>How it works:</strong> After each import, the system checks if any shipment's status changed. If a matching transition rule exists, it applies the configured action (reassign, unassign, complete, or no action). Dispositions marked as "final" are always respected — completed shipments won't be re-called. Dispositions marked as "re-callable on status change" allow the shipment to be called again after reassignment.
                    </p>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- TELEMARKETER WORKLOAD SUMMARY --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
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
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
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
                                <tr class="hover:bg-gray-50 {{ !$tm->is_telemarketing_active ? 'opacity-50' : '' }}">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 {{ $tm->is_telemarketing_active ? 'bg-indigo-100' : 'bg-gray-200' }} rounded-full flex items-center justify-center">
                                                <span class="text-xs font-bold {{ $tm->is_telemarketing_active ? 'text-indigo-600' : 'text-gray-400' }}">{{ strtoupper(substr($tm->name, 0, 2)) }}</span>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900">{{ $tm->name }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $tm->is_telemarketing_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $tm->is_telemarketing_active ? 'Active' : 'Inactive' }}
                                        </span>
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
                                        <div class="flex items-center justify-center space-x-2">
                                            <a href="{{ route('telemarketing.queue', ['telemarketer_id' => $tm->id]) }}" class="text-indigo-600 hover:text-indigo-900 text-xs">Queue</a>
                                            @if(!$tm->is_telemarketing_active && $tm->pending_count > 0)
                                                <button type="button" onclick="redistributeAgent({{ $tm->id }}, '{{ addslashes($tm->name) }}', this)"
                                                        class="text-amber-600 hover:text-amber-900 text-xs inline-flex items-center">
                                                    <span class="btn-text">Redistribute</span>
                                                    <svg class="btn-spinner hidden animate-spin ml-1 h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                </button>
                                            @endif
                                            <button type="button" onclick="unassignAll({{ $tm->id }}, '{{ addslashes($tm->name) }}', this)"
                                                    class="text-red-600 hover:text-red-900 text-xs inline-flex items-center">
                                                <span class="btn-text">Unassign</span>
                                                <svg class="btn-spinner hidden animate-spin ml-1 h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                            </button>
                                        </div>
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

        // ── Status name map for strict validation ──
        const statusNameMap = {
            @foreach($statuses as $status)
                {{ $status->id }}: '{{ $status->name }}',
            @endforeach
        };

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
        //  AGENT ACTIVE/INACTIVE TOGGLE
        // ═══════════════════════════════════════════════════════

        async function toggleAgentActive(agentId, agentName, btn) {
            const currentActive = btn.dataset.active === '1';
            const newState = !currentActive;
            const confirmMsg = newState
                ? `Activate ${agentName} for telemarketing? They will start receiving assignments.`
                : `Deactivate ${agentName}? They won't receive new assignments. You can redistribute their pending shipments afterward.`;

            if (!confirm(confirmMsg)) return;

            try {
                const res = await fetch('{{ route("telemarketing.toggle-agent-active") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ telemarketer_id: agentId }),
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    // Update toggle UI
                    btn.dataset.active = data.is_active ? '1' : '0';
                    const knob = btn.querySelector('span');
                    const label = document.getElementById(`active-label-${agentId}`);
                    const row = document.getElementById(`agent-row-${agentId}`);

                    if (data.is_active) {
                        btn.classList.remove('bg-gray-300');
                        btn.classList.add('bg-green-500');
                        knob.classList.remove('translate-x-0');
                        knob.classList.add('translate-x-5');
                        if (label) { label.textContent = 'Active'; label.classList.remove('text-gray-400'); label.classList.add('text-green-600'); }
                        if (row) row.classList.remove('opacity-50');
                    } else {
                        btn.classList.remove('bg-green-500');
                        btn.classList.add('bg-gray-300');
                        knob.classList.remove('translate-x-5');
                        knob.classList.add('translate-x-0');
                        if (label) { label.textContent = 'Inactive'; label.classList.remove('text-green-600'); label.classList.add('text-gray-400'); }
                        if (row) row.classList.add('opacity-50');
                    }

                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast(data.message || 'Failed to toggle.', 'error');
                }
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }
        }

        // ═══════════════════════════════════════════════════════
        //  REDISTRIBUTE AGENT
        // ═══════════════════════════════════════════════════════

        async function redistributeAgent(agentId, agentName, btn) {
            if (!confirm(`Redistribute all pending shipments from ${agentName} to other active agents?`)) return;
            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Redistributing...';

            try {
                const res = await fetch('{{ route("telemarketing.redistribute-agent") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ telemarketer_id: agentId }),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'info');
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Redistribute';
            setTimeout(() => location.reload(), 2000);
        }

        // ═══════════════════════════════════════════════════════
        //  AGENT STATUS ASSIGNMENTS
        // ═══════════════════════════════════════════════════════

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
                    body: JSON.stringify({ telemarketer_id: agentId, status_ids: statusIds }),
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    dirtyAgents.delete(agentId);
                    const savedLabel = document.getElementById(`saved-agent-${agentId}`);
                    if (savedLabel) {
                        savedLabel.textContent = statusIds.length > 0
                            ? `✓ ${statusIds.length} status${statusIds.length > 1 ? 'es' : ''}`
                            : 'All';
                        savedLabel.classList.remove('hidden');
                    }
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
        //  STRICT MANUAL ASSIGN VALIDATION (Client-side)
        // ═══════════════════════════════════════════════════════

        function checkStrictAssign() {
            const tmSelect = document.getElementById('telemarketer_id');
            const statusSelect = document.getElementById('status_id');
            const warning = document.getElementById('strict-assign-warning');
            const warningMsg = document.getElementById('strict-assign-msg');

            if (!tmSelect.value) { warning.classList.add('hidden'); return; }

            const selectedOption = tmSelect.options[tmSelect.selectedIndex];
            const agentStatuses = JSON.parse(selectedOption.dataset.statuses || '[]');
            const isActive = selectedOption.dataset.active === '1';

            // Check inactive
            if (!isActive) {
                warning.classList.remove('hidden');
                warningMsg.textContent = 'This agent is INACTIVE. They cannot receive new assignments.';
                return;
            }

            // Check status mismatch (only if agent has specific statuses assigned AND a status filter is selected)
            if (agentStatuses.length > 0 && statusSelect.value) {
                const selectedStatusId = parseInt(statusSelect.value);
                if (!agentStatuses.includes(selectedStatusId)) {
                    const statusName = statusNameMap[selectedStatusId] || 'Unknown';
                    const allowedNames = agentStatuses.map(id => statusNameMap[id] || 'Unknown').join(', ');
                    warning.classList.remove('hidden');
                    warningMsg.textContent = `This agent only handles [${allowedNames}]. You selected "${statusName}" which is not in their assigned statuses. This assignment will be blocked.`;
                    return;
                }
            }

            warning.classList.add('hidden');
        }

        // Attach change listeners for strict validation
        document.getElementById('telemarketer_id').addEventListener('change', checkStrictAssign);
        document.getElementById('status_id').addEventListener('change', checkStrictAssign);

        // ═══════════════════════════════════════════════════════
        //  AJAX FUNCTIONS
        // ═══════════════════════════════════════════════════════

        async function runAllRules(btn) {
            if (!confirm('Run auto-assignment for all active rules?')) return;
            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Running...';

            try {
                const res = await fetch('{{ route("telemarketing.run-auto-assign") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
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

        async function runSingleRule(ruleId, btn) {
            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Running...';

            try {
                const res = await fetch('{{ route("telemarketing.run-auto-assign") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
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
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : (res.status === 422 ? 'error' : 'info'));
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Assign Shipments';
            setTimeout(() => location.reload(), 3000);
            return false;
        }

        async function unassignAll(telemarketerId, name, btn) {
            if (!confirm(`Unassign all pending shipments from ${name}?`)) return;
            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Unassigning...';

            try {
                const res = await fetch('{{ route("telemarketing.unassign-all") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ telemarketer_id: telemarketerId }),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'info');
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Unassign';
            setTimeout(() => location.reload(), 3000);
        }

        function toggleRuleFields() {
            const ruleType = document.getElementById('rule_type').value;
            document.getElementById('status-field').classList.toggle('hidden', ruleType === 'delivered_age');
            document.getElementById('days-field').classList.toggle('hidden', ruleType !== 'delivered_age');
        }
    </script>
    @endpush
</x-app-layout>
