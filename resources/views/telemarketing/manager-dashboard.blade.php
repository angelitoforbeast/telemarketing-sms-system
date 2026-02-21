<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Telemarketing Overview</h2>
            <div class="flex space-x-2">
                <a href="{{ route('telemarketing.assignments') }}" class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Assignments
                </a>
                <a href="{{ route('telemarketing.call-logs') }}" class="inline-flex items-center px-3 py-2 bg-gray-600 text-white text-sm rounded-lg hover:bg-gray-700 transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    Call Logs
                </a>
                <a href="{{ route('telemarketing.dispositions') }}" class="inline-flex items-center px-3 py-2 bg-purple-600 text-white text-sm rounded-lg hover:bg-purple-700 transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                    Dispositions
                </a>
            </div>
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

            {{-- Company Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <x-stat-card title="Unassigned" :value="$stats['total_unassigned']" color="red" />
                <x-stat-card title="Pending" :value="$stats['total_pending']" color="yellow" />
                <x-stat-card title="In Progress" :value="$stats['total_in_progress']" color="blue" />
                <x-stat-card title="Completed" :value="$stats['total_completed']" color="green" />
                <x-stat-card title="Calls Today" :value="$stats['calls_today']" color="indigo" />
            </div>

            {{-- Telemarketer Performance Table --}}
            <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Telemarketer Performance</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agent</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Pending Queue</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Calls Today</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($stats['telemarketers'] as $tm)
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
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $tm->pending_count > 0 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $tm->pending_count }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $tm->calls_today_count > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $tm->calls_today_count }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <a href="{{ route('telemarketing.queue', ['telemarketer_id' => $tm->id]) }}" class="text-indigo-600 hover:text-indigo-900 text-sm mr-3">View Queue</a>
                                        <form method="POST" action="{{ route('telemarketing.unassign-all') }}" class="inline" onsubmit="return confirm('Unassign all pending shipments from {{ $tm->name }}?')">
                                            @csrf
                                            <input type="hidden" name="telemarketer_id" value="{{ $tm->id }}">
                                            <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Unassign All</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">No active telemarketers found. Create telemarketer accounts in User Management.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Assignment Rules --}}
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Assignment Rules</h3>
                    <form method="POST" action="{{ route('telemarketing.run-auto-assign') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition"
                                onclick="return confirm('Run auto-assignment for all active rules?')">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            Run All Rules
                        </button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rule Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Max Attempts</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($rules as $rule)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $rule->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        @if($rule->rule_type === 'status_based')
                                            Status: {{ $rule->status?->name ?? 'Any' }}
                                        @elseif($rule->rule_type === 'delivered_age')
                                            Delivered {{ $rule->days_threshold }}+ days ago
                                        @else
                                            Custom
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $rule->assignment_method)) }}</td>
                                    <td class="px-6 py-4 text-center text-sm text-gray-600">{{ $rule->max_attempts }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <x-badge :color="$rule->is_active ? 'green' : 'gray'">{{ $rule->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                    </td>
                                    <td class="px-6 py-4 text-center space-x-2">
                                        <form method="POST" action="{{ route('telemarketing.run-auto-assign') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="rule_id" value="{{ $rule->id }}">
                                            <button type="submit" class="text-green-600 hover:text-green-900 text-sm">Run</button>
                                        </form>
                                        <form method="POST" action="{{ route('telemarketing.toggle-rule', $rule) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-yellow-600 hover:text-yellow-900 text-sm">{{ $rule->is_active ? 'Disable' : 'Enable' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('telemarketing.delete-rule', $rule) }}" class="inline" onsubmit="return confirm('Delete this rule?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No assignment rules configured. Create rules in the Assignments page.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
