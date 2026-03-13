<x-app-layout>
    <x-slot name="title">Orders</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Orders</h2>
            <a href="{{ route('orders.export', request()->query()) }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Export CSV
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Stats Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow-sm p-4 border">
                    <p class="text-xs font-medium text-gray-500 uppercase">Today's Orders</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['today_count'] }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4 border">
                    <p class="text-xs font-medium text-gray-500 uppercase">Today's Total</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">₱{{ number_format($stats['today_total'], 2) }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4 border">
                    <p class="text-xs font-medium text-gray-500 uppercase">Pending</p>
                    <p class="text-2xl font-bold text-yellow-600 mt-1">{{ $stats['today_pending'] }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4 border">
                    <p class="text-xs font-medium text-gray-500 uppercase">Confirmed</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">{{ $stats['today_confirmed'] }}</p>
                </div>
            </div>

            {{-- Filters --}}
            <div class="bg-white rounded-lg shadow-sm p-4 border">
                <form method="GET" action="{{ route('orders.index') }}" class="flex flex-wrap items-end gap-3">
                    {{-- Date Filter --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Date</label>
                        <select name="date_filter" onchange="toggleCustomDate(this)" class="border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="today" {{ $dateFilter === 'today' ? 'selected' : '' }}>Today</option>
                            <option value="yesterday" {{ $dateFilter === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                            <option value="this_week" {{ $dateFilter === 'this_week' ? 'selected' : '' }}>This Week</option>
                            <option value="this_month" {{ $dateFilter === 'this_month' ? 'selected' : '' }}>This Month</option>
                            <option value="custom" {{ $dateFilter === 'custom' ? 'selected' : '' }}>Custom Range</option>
                            <option value="all" {{ $dateFilter === 'all' ? 'selected' : '' }}>All Time</option>
                        </select>
                    </div>

                    <div id="custom-date-range" class="{{ $dateFilter === 'custom' ? '' : 'hidden' }} flex gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" class="border-gray-300 rounded-md shadow-sm text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}" class="border-gray-300 rounded-md shadow-sm text-sm">
                        </div>
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <select name="status" class="border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">All</option>
                            @foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $s)
                                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Order Type --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Type</label>
                        <select name="order_type_id" class="border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">All</option>
                            @foreach($orderTypes as $ot)
                                <option value="{{ $ot->id }}" {{ request('order_type_id') == $ot->id ? 'selected' : '' }}>{{ $ot->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Created By --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Telemarketer</label>
                        <select name="created_by" class="border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">All</option>
                            @foreach($telemarketers as $tm)
                                <option value="{{ $tm->id }}" {{ request('created_by') == $tm->id ? 'selected' : '' }}>{{ $tm->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Search --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or phone..."
                               class="border-gray-300 rounded-md shadow-sm text-sm w-40">
                    </div>

                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition">
                        Filter
                    </button>
                    <a href="{{ route('orders.index') }}" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Clear</a>
                </form>
            </div>

            {{-- Orders Table --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Process Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">By</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($orders as $order)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-mono text-gray-500">#{{ $order->id }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <x-badge :color="$order->orderType?->color ?? 'gray'">{{ $order->orderType?->name ?? 'N/A' }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ Str::limit($order->customer_name, 25) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 font-mono">{{ $order->customer_phone }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <span class="font-medium">{{ $order->items->count() }}</span> item(s)
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        {{ $order->items->pluck('item_name')->implode(', ') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">₱{{ number_format($order->total_amount, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $order->process_date?->format('M d, Y') }}
                                    @if($order->process_date?->isToday())
                                        <span class="text-xs text-indigo-600 font-medium">(Today)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <form method="POST" action="{{ route('orders.update-status', $order) }}" class="inline">
                                        @csrf
                                        @method('PUT')
                                        <select name="status" onchange="this.form.submit()"
                                                class="border-gray-300 rounded-md shadow-sm text-xs py-1 px-2
                                                {{ $order->status === 'pending' ? 'bg-yellow-50 text-yellow-800' : '' }}
                                                {{ $order->status === 'confirmed' ? 'bg-green-50 text-green-800' : '' }}
                                                {{ $order->status === 'processing' ? 'bg-blue-50 text-blue-800' : '' }}
                                                {{ $order->status === 'shipped' ? 'bg-indigo-50 text-indigo-800' : '' }}
                                                {{ $order->status === 'delivered' ? 'bg-emerald-50 text-emerald-800' : '' }}
                                                {{ $order->status === 'cancelled' ? 'bg-red-50 text-red-800' : '' }}">
                                            @foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $s)
                                                <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $order->creator?->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ route('orders.show', $order) }}" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">View</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-sm text-gray-500">
                                    No orders found for the selected filters.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($orders->hasPages())
                <div class="px-4 py-3 border-t bg-gray-50">
                    {{ $orders->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function toggleCustomDate(select) {
            var customRange = document.getElementById('custom-date-range');
            if (select.value === 'custom') {
                customRange.classList.remove('hidden');
            } else {
                customRange.classList.add('hidden');
            }
        }
    </script>
    @endpush
</x-app-layout>
