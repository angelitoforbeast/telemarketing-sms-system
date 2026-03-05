<x-app-layout>
    <x-slot name="title">SMS Campaign Details</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $campaign->name }}</h2>
            <a href="{{ route('sms.campaigns.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            {{-- Status & Controls --}}
            <div class="bg-white shadow rounded-lg p-6 mb-4">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                            @switch($campaign->campaign_status)
                                @case('draft') bg-gray-100 text-gray-700 @break
                                @case('queued') bg-blue-100 text-blue-700 @break
                                @case('sending') bg-yellow-100 text-yellow-700 @break
                                @case('paused') bg-orange-100 text-orange-700 @break
                                @case('completed') bg-green-100 text-green-700 @break
                                @case('cancelled') bg-red-100 text-red-700 @break
                            @endswitch">
                            {{ ucfirst($campaign->campaign_status) }}
                        </span>
                        <span class="text-sm text-gray-500">
                            {{ $campaign->sending_method === 'sim_based' ? 'SIM-Based' : 'Gateway' }} &middot;
                            {{ ucfirst(str_replace('_', ' ', $campaign->schedule_type)) }}
                        </span>
                    </div>

                    <div class="flex gap-2">
                        @if(in_array($campaign->campaign_status, ['draft', 'paused', 'completed']))
                            <form method="POST" action="{{ route('sms.campaigns.start', $campaign) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700">
                                    {{ $campaign->campaign_status === 'draft' ? 'Start Campaign' : 'Restart' }}
                                </button>
                            </form>
                        @endif

                        @if($campaign->campaign_status === 'sending')
                            <form method="POST" action="{{ route('sms.campaigns.pause', $campaign) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-md text-sm font-medium hover:bg-yellow-600">Pause</button>
                            </form>
                        @endif

                        @if(in_array($campaign->campaign_status, ['sending', 'paused', 'queued']))
                            <form method="POST" action="{{ route('sms.campaigns.cancel', $campaign) }}" class="inline"
                                onsubmit="return confirm('Cancel this campaign? Remaining queued messages will be removed.')">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">Cancel</button>
                            </form>
                        @endif

                        <a href="{{ route('sms.campaigns.edit', $campaign) }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">Edit</a>
                    </div>
                </div>

                {{-- Progress Bar --}}
                @php
                    $total = $campaign->total_recipients ?: 0;
                    $sent = $campaign->sent_count ?? $campaign->total_sent ?? 0;
                    $failed = $campaign->failed_count ?? $campaign->total_failed ?? 0;
                    $queued = $campaign->queued_count ?? 0;
                    $pct = $total > 0 ? round(($sent + $failed) / $total * 100, 1) : 0;
                @endphp
                <div class="mb-2">
                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span>Progress: {{ $pct }}%</span>
                        <span>{{ $sent + $failed }} / {{ $total }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                        @if($total > 0)
                            <div class="h-3 rounded-full bg-green-500" style="width: {{ $total > 0 ? ($sent / $total * 100) : 0 }}%; float: left;"></div>
                            <div class="h-3 bg-red-400" style="width: {{ $total > 0 ? ($failed / $total * 100) : 0 }}%; float: left;"></div>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-4 mt-4">
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-gray-700">{{ number_format($total) }}</div>
                        <div class="text-xs text-gray-500">Total Recipients</div>
                    </div>
                    <div class="text-center p-3 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-700">{{ number_format($sent) }}</div>
                        <div class="text-xs text-green-600">Sent</div>
                    </div>
                    <div class="text-center p-3 bg-red-50 rounded-lg">
                        <div class="text-2xl font-bold text-red-700">{{ number_format($failed) }}</div>
                        <div class="text-xs text-red-600">Failed</div>
                    </div>
                    <div class="text-center p-3 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-700">{{ number_format($queued) }}</div>
                        <div class="text-xs text-blue-600">Queued</div>
                    </div>
                </div>
            </div>

            {{-- Campaign Details --}}
            <div class="bg-white shadow rounded-lg p-6 mb-4">
                <h3 class="text-lg font-medium text-gray-900 mb-3">Campaign Details</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Template:</span>
                        <div class="mt-1 p-3 bg-gray-50 rounded font-mono text-xs whitespace-pre-wrap">{{ $campaign->sms_template }}</div>
                    </div>
                    <div class="space-y-2">
                        <div><span class="text-gray-500">Throttle:</span> {{ $campaign->throttle_delay_seconds }}s between sends</div>
                        <div><span class="text-gray-500">Filter Type:</span> {{ ucfirst($campaign->recipient_filter_type) }}</div>
                        <div><span class="text-gray-500">Dynamic Recipients Now:</span> <strong>{{ number_format($recipientCount) }}</strong></div>
                        @if($campaign->last_run_at)
                            <div><span class="text-gray-500">Last Run:</span> {{ $campaign->last_run_at->format('M d, Y H:i') }}</div>
                        @endif
                    </div>
                </div>
            </div>


            {{-- Assign SMS Operator --}}
            <div class="bg-white shadow rounded-lg p-6 mb-4">
                <h3 class="text-lg font-medium text-gray-900 mb-3">SMS Operator Assignment</h3>
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        @if($campaign->assignedOperator)
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-700">
                                    {{ $campaign->assignedOperator->name }}
                                </span>
                                <span class="text-sm text-gray-500">{{ $campaign->assignedOperator->email }}</span>
                            </div>
                        @else
                            <span class="text-sm text-gray-500">Not assigned — messages will be auto-distributed to all operators.</span>
                        @endif
                    </div>
                    @if(in_array($campaign->campaign_status, ['draft', 'paused', 'sending']))
                        <form method="POST" action="{{ route('sms.campaigns.assign-operator', $campaign) }}" class="flex items-center gap-2">
                            @csrf
                            <select name="assigned_operator_id" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($smsOperators as $op)
                                    <option value="{{ $op->id }}" {{ $campaign->assigned_operator_id == $op->id ? 'selected' : '' }}>
                                        {{ $op->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="px-3 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">
                                Assign
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="bg-white shadow rounded-lg p-6 mb-4">
                <h3 class="text-lg font-medium text-gray-900 mb-3">Online Devices ({{ $onlineDevices->count() }})</h3>
                @if($onlineDevices->isEmpty())
                    <p class="text-sm text-gray-500">No devices online. Connect a phone with the TeleSMS app to start sending.</p>
                @else
                    <div class="grid grid-cols-3 gap-3">
                        @foreach($onlineDevices as $device)
                            <div class="p-3 border rounded-lg flex items-center gap-3">
                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                <div>
                                    <div class="text-sm font-medium">{{ $device->device_name }}</div>
                                    <div class="text-xs text-gray-500">{{ $device->carrier }} &middot; {{ $device->remainingCapacity() }} remaining today</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Recent Logs --}}
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-medium text-gray-900">Recent Activity</h3>
                    <a href="{{ route('sms.campaigns.logs', $campaign) }}" class="text-sm text-indigo-600 hover:text-indigo-900">View All Logs &rarr;</a>
                </div>
                @if($recentLogs->isEmpty())
                    <p class="text-sm text-gray-500">No messages sent yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-500 uppercase border-b">
                                    <th class="pb-2">Phone</th>
                                    <th class="pb-2">Consignee</th>
                                    <th class="pb-2">Status</th>
                                    <th class="pb-2">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach($recentLogs as $log)
                                    <tr>
                                        <td class="py-2 font-mono text-xs">{{ $log->phone_number }}</td>
                                        <td class="py-2">{{ $log->shipment?->consignee_name ?? '-' }}</td>
                                        <td class="py-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                {{ $log->status === 'sent' ? 'bg-green-100 text-green-700' : ($log->status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700') }}">
                                                {{ ucfirst($log->status) }}
                                            </span>
                                        </td>
                                        <td class="py-2 text-gray-500 text-xs">{{ $log->updated_at?->format('M d H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($campaign->campaign_status === 'sending')
    <script>
        // Auto-refresh every 10 seconds while sending
        setTimeout(() => window.location.reload(), 10000);
    </script>
    @endif
</x-app-layout>
