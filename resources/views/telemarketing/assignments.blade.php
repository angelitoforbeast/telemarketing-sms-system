<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Assignment Management</h2>
            <a href="{{ route('telemarketing.dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md bg-gray-100 hover:bg-gray-200 transition">&larr; Back to Dashboard</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
            @endif
            @if(session('info'))
                <div class="mb-4 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg">{{ session('info') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Manual Assignment Panel --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Manual Assignment</h3>
                        <p class="text-sm text-gray-500 mt-1">Assign unassigned shipments to a telemarketer ({{ $unassignedCount }} unassigned)</p>
                    </div>
                    <div class="px-6 py-5">
                        <form method="POST" action="{{ route('telemarketing.manual-assign') }}">
                            @csrf
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

                                <x-primary-button>Assign Shipments</x-primary-button>
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
                    <form method="POST" action="{{ route('telemarketing.run-auto-assign') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition"
                                onclick="return confirm('Run auto-assignment for all active rules?')">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            Run Auto-Assign
                        </button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agent</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Pending</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Completed</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($telemarketers as $tm)
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
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ $tm->pending_count }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ $tm->completed_count }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <a href="{{ route('telemarketing.queue', ['telemarketer_id' => $tm->id]) }}" class="text-indigo-600 hover:text-indigo-900 text-sm mr-2">View Queue</a>
                                        <form method="POST" action="{{ route('telemarketing.unassign-all') }}" class="inline" onsubmit="return confirm('Unassign all pending shipments from {{ $tm->name }}?')">
                                            @csrf
                                            <input type="hidden" name="telemarketer_id" value="{{ $tm->id }}">
                                            <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Unassign All</button>
                                        </form>
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
        function toggleRuleFields() {
            const ruleType = document.getElementById('rule_type').value;
            document.getElementById('status-field').classList.toggle('hidden', ruleType === 'delivered_age');
            document.getElementById('days-field').classList.toggle('hidden', ruleType !== 'delivered_age');
        }
    </script>
    @endpush
</x-app-layout>
