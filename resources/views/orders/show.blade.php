<x-app-layout>
    <x-slot name="title">Order #{{ $order->id }}</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Order #{{ $order->id }}</h2>
            <a href="{{ route('orders.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Orders</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Order Summary --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <h3 class="text-lg font-semibold text-gray-900">Order Details</h3>
                        <x-badge :color="$order->orderType?->color ?? 'gray'">{{ $order->orderType?->name ?? 'N/A' }}</x-badge>
                        <x-badge :color="$order->status === 'pending' ? 'yellow' : ($order->status === 'confirmed' ? 'green' : ($order->status === 'cancelled' ? 'red' : 'blue'))">{{ ucfirst($order->status) }}</x-badge>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-green-600">₱{{ number_format($order->total_amount, 2) }}</p>
                        <p class="text-xs text-gray-500">Total COD</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Customer Info --}}
                    <div class="space-y-3">
                        <h4 class="text-sm font-semibold text-gray-500 uppercase">Customer</h4>
                        <div>
                            <p class="text-sm text-gray-500">Name</p>
                            <p class="text-sm font-medium text-gray-900">{{ $order->customer_name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Phone</p>
                            <p class="text-sm font-medium text-gray-900 font-mono">{{ $order->customer_phone }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Address</p>
                            <p class="text-sm font-medium text-gray-900">{{ $order->full_address }}</p>
                        </div>
                    </div>

                    {{-- Order Meta --}}
                    <div class="space-y-3">
                        <h4 class="text-sm font-semibold text-gray-500 uppercase">Order Info</h4>
                        <div>
                            <p class="text-sm text-gray-500">Process Date</p>
                            <p class="text-sm font-medium text-gray-900">
                                {{ $order->process_date?->format('M d, Y') }}
                                @if($order->process_date?->isToday()) <span class="text-indigo-600">(Today)</span> @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Created By</p>
                            <p class="text-sm font-medium text-gray-900">{{ $order->creator?->name ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Created At</p>
                            <p class="text-sm font-medium text-gray-900">{{ $order->created_at->format('M d, Y g:i A') }}</p>
                        </div>
                        @if($order->shipment)
                        <div>
                            <p class="text-sm text-gray-500">Source Shipment</p>
                            <p class="text-sm font-medium text-gray-900">{{ $order->shipment->waybill_no }}</p>
                        </div>
                        @endif
                        @if($order->notes)
                        <div>
                            <p class="text-sm text-gray-500">Notes</p>
                            <p class="text-sm text-gray-900">{{ $order->notes }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Order Items --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Items ({{ $order->items->count() }})</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item Name</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Quantity</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($order->items as $index => $item)
                        <tr>
                            <td class="px-6 py-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $item->item_name }}</td>
                            <td class="px-6 py-3 text-sm text-center text-gray-900">{{ $item->quantity }}</td>
                            <td class="px-6 py-3 text-sm text-right text-gray-900">₱{{ number_format($item->unit_price, 2) }}</td>
                            <td class="px-6 py-3 text-sm text-right font-semibold text-gray-900">₱{{ number_format($item->subtotal, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-6 py-3 text-right text-sm font-semibold text-gray-700">Total:</td>
                            <td class="px-6 py-3 text-right text-lg font-bold text-green-600">₱{{ number_format($order->total_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Status Update --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Update Status</h3>
                <form method="POST" action="{{ route('orders.update-status', $order) }}" class="flex items-center gap-3">
                    @csrf
                    @method('PUT')
                    <select name="status" class="border-gray-300 rounded-md shadow-sm text-sm">
                        @foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $s)
                            <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition">
                        Update Status
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
