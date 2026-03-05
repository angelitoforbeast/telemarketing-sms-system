<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">SMS Devices</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            {{-- Register New Device --}}
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Register New Device</h3>
                <form method="POST" action="{{ route('sms.devices.store') }}">
                    @csrf
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Device Name *</label>
                            <input type="text" name="device_name" required
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="e.g., Phone A - Globe">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SIM Number</label>
                            <input type="text" name="sim_number"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="09171234567">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Carrier</label>
                            <select name="carrier" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- Select --</option>
                                <option value="Globe">Globe</option>
                                <option value="Smart">Smart</option>
                                <option value="TNT">TNT</option>
                                <option value="DITO">DITO</option>
                                <option value="Sun">Sun</option>
                                <option value="TM">TM</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Daily Limit</label>
                            <input type="number" name="daily_limit" value="200" min="1" max="10000" required
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Throttle (seconds)</label>
                            <input type="number" name="throttle_delay_seconds" value="10" min="1" max="300" required
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">Register Device</button>
                </form>
            </div>

            {{-- Device List --}}
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-medium text-gray-900">Registered Devices ({{ $devices->count() }})</h3>
                </div>

                @if($devices->isEmpty())
                    <div class="p-6 text-center text-gray-500">
                        <p>No devices registered yet. Register a phone above to start sending SMS.</p>
                    </div>
                @else
                    <div class="divide-y">
                        @foreach($devices as $device)
                            <div class="px-6 py-4 flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    {{-- Online indicator --}}
                                    @if($device->last_seen_at && $device->last_seen_at->gt(now()->subMinutes(5)))
                                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse" title="Online"></div>
                                    @else
                                        <div class="w-3 h-3 bg-gray-300 rounded-full" title="Offline"></div>
                                    @endif

                                    <div>
                                        <div class="font-medium text-gray-900">{{ $device->device_name }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $device->carrier ?? 'Unknown carrier' }}
                                            @if($device->sim_number) &middot; {{ $device->sim_number }} @endif
                                            &middot; Limit: {{ $device->daily_limit }}/day
                                            &middot; Sent today: {{ $device->messages_sent_today }}
                                            &middot; Throttle: {{ $device->throttle_delay_seconds }}s
                                        </div>
                                        <div class="text-xs text-gray-400 mt-1">
                                            Last seen: {{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never' }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    {{-- Device Token (copyable) --}}
                                    <div class="relative group">
                                        <button type="button" onclick="copyToken('{{ $device->device_token }}')"
                                            class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded font-mono text-gray-600 transition"
                                            title="Click to copy token">
                                            {{ substr($device->device_token, 0, 12) }}...
                                        </button>
                                    </div>

                                    {{-- Toggle --}}
                                    <form method="POST" action="{{ route('sms.devices.toggle', $device) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-3 py-1 text-xs rounded font-medium
                                            {{ $device->is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
                                            {{ $device->is_active ? 'Active' : 'Inactive' }}
                                        </button>
                                    </form>

                                    {{-- Regenerate Token --}}
                                    <form method="POST" action="{{ route('sms.devices.regenerate-token', $device) }}" class="inline"
                                        onsubmit="return confirm('Regenerate token? The phone will need to re-authenticate.')">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 text-xs text-gray-500 hover:text-indigo-600" title="Regenerate Token">
                                            &#x21bb;
                                        </button>
                                    </form>

                                    {{-- Delete --}}
                                    <form method="POST" action="{{ route('sms.devices.destroy', $device) }}" class="inline"
                                        onsubmit="return confirm('Remove this device?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="px-2 py-1 text-xs text-red-500 hover:text-red-700" title="Remove">
                                            &times;
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Instructions --}}
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-blue-800 mb-2">How to connect a phone:</h4>
                <ol class="text-sm text-blue-700 space-y-1 list-decimal list-inside">
                    <li>Register the device above to get a device token</li>
                    <li>Install the TeleSMS app on the Android phone</li>
                    <li>Login as an SMS Operator account</li>
                    <li>The app will auto-detect the device token and connect</li>
                    <li>The device will appear as "Online" when connected</li>
                </ol>
            </div>
        </div>
    </div>

    <script>
        function copyToken(token) {
            navigator.clipboard.writeText(token).then(() => {
                alert('Token copied to clipboard!');
            });
        }
    </script>
</x-app-layout>
