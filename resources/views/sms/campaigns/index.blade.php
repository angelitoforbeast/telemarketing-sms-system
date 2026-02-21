<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">SMS Campaigns</h2>
            @can('sms.campaigns.create')
                <a href="{{ route('sms.campaigns.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">New Campaign</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <x-alert type="success">{{ session('success') }}</x-alert>
            @endif

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trigger Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Active</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sent</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Queued</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Failed</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Daily Limit</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($campaigns as $campaign)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $campaign->name }}</td>
                                    <td class="px-4 py-3 text-sm"><x-badge color="blue">{{ $campaign->triggerStatus?->name ?? 'N/A' }}</x-badge></td>
                                    <td class="px-4 py-3 text-sm">
                                        @if($campaign->is_active)
                                            <x-badge color="green">Active</x-badge>
                                        @else
                                            <x-badge color="gray">Inactive</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-green-600 font-semibold">{{ number_format($campaign->total_sent ?? 0) }}</td>
                                    <td class="px-4 py-3 text-sm text-yellow-600">{{ number_format($campaign->total_queued ?? 0) }}</td>
                                    <td class="px-4 py-3 text-sm text-red-600">{{ number_format($campaign->total_failed ?? 0) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $campaign->daily_send_limit ? number_format($campaign->daily_send_limit) : 'Unlimited' }}</td>
                                    <td class="px-4 py-3 text-sm space-x-2">
                                        @can('sms.campaigns.edit')
                                            <a href="{{ route('sms.campaigns.edit', $campaign) }}" class="text-indigo-600 hover:text-indigo-900 text-xs">Edit</a>
                                        @endcan
                                        @can('sms.campaigns.toggle')
                                            <form method="POST" action="{{ route('sms.campaigns.toggle', $campaign) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs {{ $campaign->is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }}">
                                                    {{ $campaign->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        @endcan
                                        <a href="{{ route('sms.campaigns.logs', $campaign) }}" class="text-gray-600 hover:text-gray-900 text-xs">Logs</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">No SMS campaigns yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t">{{ $campaigns->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
