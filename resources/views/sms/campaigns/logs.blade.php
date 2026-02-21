<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">SMS Logs: {{ $campaign->name }}</h2>
            <a href="{{ route('sms.campaigns.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Campaigns</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waybill</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Message</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Send Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sent At</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($logs as $log)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-500">#{{ $log->id }}</td>
                                    <td class="px-4 py-3 text-sm font-mono">
                                        @if($log->shipment)
                                            <a href="{{ route('shipments.show', $log->shipment) }}" class="text-indigo-600 hover:text-indigo-900">{{ $log->shipment->waybill_no }}</a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ $log->phone_number }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <x-badge :color="match($log->status) { 'sent' => 'green', 'failed' => 'red', 'queued' => 'yellow', default => 'gray' }">{{ ucfirst($log->status) }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ Str::limit($log->message_body, 50) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $log->send_date }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $log->sent_at?->format('M d, H:i') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">No SMS logs for this campaign.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t">{{ $logs->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
