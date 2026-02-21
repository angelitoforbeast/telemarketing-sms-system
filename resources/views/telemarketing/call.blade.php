<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Call: {{ $shipment->waybill_no }}</h2>
            <a href="{{ route('telemarketing.queue') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Queue</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if($errors->any())
                <x-alert type="error">
                    @foreach($errors->all() as $error)
                        {{ $error }}<br>
                    @endforeach
                </x-alert>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Shipment Info Panel --}}
                <div class="lg:col-span-1 space-y-4">
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-5 py-4">
                            <h3 class="text-sm font-medium text-gray-500 mb-3">Shipment Info</h3>
                            <dl class="space-y-2">
                                <div><dt class="text-xs text-gray-400">Waybill</dt><dd class="text-sm font-mono font-semibold">{{ $shipment->waybill_no }}</dd></div>
                                <div><dt class="text-xs text-gray-400">Courier</dt><dd><x-badge :color="$shipment->courier === 'jnt' ? 'red' : 'orange'">{{ strtoupper($shipment->courier) }}</x-badge></dd></div>
                                <div><dt class="text-xs text-gray-400">Status</dt><dd><x-badge color="blue">{{ $shipment->status?->name ?? 'Unknown' }}</x-badge></dd></div>
                                <div><dt class="text-xs text-gray-400">COD</dt><dd class="text-sm font-semibold">{{ $shipment->cod_amount ? '₱' . number_format($shipment->cod_amount, 2) : '-' }}</dd></div>
                            </dl>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-5 py-4">
                            <h3 class="text-sm font-medium text-gray-500 mb-3">Consignee</h3>
                            <dl class="space-y-2">
                                <div><dt class="text-xs text-gray-400">Name</dt><dd class="text-sm font-semibold">{{ $shipment->consignee_name }}</dd></div>
                                <div>
                                    <dt class="text-xs text-gray-400">Phone 1</dt>
                                    <dd class="text-sm font-mono font-semibold text-green-700">{{ $shipment->consignee_phone_1 ?? '-' }}</dd>
                                </div>
                                @if($shipment->consignee_phone_2)
                                <div>
                                    <dt class="text-xs text-gray-400">Phone 2</dt>
                                    <dd class="text-sm font-mono text-green-600">{{ $shipment->consignee_phone_2 }}</dd>
                                </div>
                                @endif
                                <div><dt class="text-xs text-gray-400">Address</dt><dd class="text-sm">{{ $shipment->consignee_address }}</dd></div>
                                <div><dt class="text-xs text-gray-400">Province / City</dt><dd class="text-sm">{{ $shipment->consignee_province }} / {{ $shipment->consignee_city }}</dd></div>
                            </dl>
                        </div>
                    </div>
                </div>

                {{-- Call Form + History --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- Log Call Form --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-6 py-5">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Log Call (Attempt #{{ $shipment->telemarketing_attempt_count + 1 }})</h3>
                            <form method="POST" action="{{ route('telemarketing.log-call', $shipment) }}">
                                @csrf

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <x-input-label for="disposition_id" value="Disposition *" />
                                        <select id="disposition_id" name="disposition_id" required
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="">-- Select --</option>
                                            @foreach($dispositions as $d)
                                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label for="phone_called" value="Phone Called" />
                                        <select id="phone_called" name="phone_called"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="{{ $shipment->consignee_phone_1 }}">{{ $shipment->consignee_phone_1 }} (Primary)</option>
                                            @if($shipment->consignee_phone_2)
                                                <option value="{{ $shipment->consignee_phone_2 }}">{{ $shipment->consignee_phone_2 }} (Secondary)</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <x-input-label for="notes" value="Notes" />
                                    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Any notes about the call...">{{ old('notes') }}</textarea>
                                </div>

                                <div class="mb-4">
                                    <x-input-label for="callback_at" value="Schedule Callback (optional)" />
                                    <input type="datetime-local" id="callback_at" name="callback_at" value="{{ old('callback_at') }}"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>

                                <div class="flex justify-end">
                                    <x-primary-button>Save Call Log</x-primary-button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Call History --}}
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-6 py-5">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Previous Calls</h3>
                            <div class="space-y-3">
                                @forelse($callHistory as $log)
                                    <div class="border rounded-lg p-3 {{ $loop->first ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200' }}">
                                        <div class="flex justify-between items-start mb-1">
                                            <div>
                                                <span class="text-sm font-semibold text-gray-900">Attempt #{{ $log->attempt_no }}</span>
                                                <x-badge color="purple">{{ $log->disposition?->name ?? 'N/A' }}</x-badge>
                                            </div>
                                            <span class="text-xs text-gray-500">{{ $log->created_at->format('M d, Y H:i') }}</span>
                                        </div>
                                        <p class="text-xs text-gray-500">By: {{ $log->user?->name ?? 'N/A' }} | Phone: {{ $log->phone_called ?? '-' }}</p>
                                        @if($log->notes)
                                            <p class="text-sm text-gray-700 mt-1">{{ $log->notes }}</p>
                                        @endif
                                        @if($log->callback_at)
                                            <p class="text-xs text-orange-600 mt-1">Callback: {{ $log->callback_at->format('M d, Y H:i') }}</p>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500">No previous calls.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
