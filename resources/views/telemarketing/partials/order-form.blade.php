{{-- ══════════════════════════════════════════════════════════════════ --}}
{{-- NEW ORDER FORM - Shows when a "triggers_order" disposition is selected --}}
{{-- ══════════════════════════════════════════════════════════════════ --}}
<div id="new-order-section" class="hidden mt-4" x-data="orderForm()" x-init="init()">
    <div class="bg-white overflow-hidden shadow rounded-lg border-2 border-green-300">
        <div class="px-4 sm:px-6 py-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 flex items-center">
                    <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Create New Order
                </h3>
                <button type="button" @click="resetForm()" class="text-xs text-gray-500 hover:text-gray-700 underline">Clear Form</button>
            </div>

            {{-- Order Type Selection --}}
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Order Type *
                    <span x-show="!orderTypeId && showErrors" class="text-red-500 text-xs ml-1">— Please select</span>
                </label>
                <div class="flex flex-wrap gap-2">
                    @foreach($orderTypes as $ot)
                        @php
                            $otColorMap = [
                                'green' => ['default' => 'bg-green-50 text-green-800 border-green-300 hover:bg-green-100', 'active' => 'bg-green-500 text-white border-green-600 ring-2 ring-green-300'],
                                'blue' => ['default' => 'bg-blue-50 text-blue-800 border-blue-300 hover:bg-blue-100', 'active' => 'bg-blue-500 text-white border-blue-600 ring-2 ring-blue-300'],
                                'red' => ['default' => 'bg-red-50 text-red-800 border-red-300 hover:bg-red-100', 'active' => 'bg-red-500 text-white border-red-600 ring-2 ring-red-300'],
                                'orange' => ['default' => 'bg-orange-50 text-orange-800 border-orange-300 hover:bg-orange-100', 'active' => 'bg-orange-500 text-white border-orange-600 ring-2 ring-orange-300'],
                                'purple' => ['default' => 'bg-purple-50 text-purple-800 border-purple-300 hover:bg-purple-100', 'active' => 'bg-purple-500 text-white border-purple-600 ring-2 ring-purple-300'],
                                'yellow' => ['default' => 'bg-yellow-50 text-yellow-800 border-yellow-300 hover:bg-yellow-100', 'active' => 'bg-yellow-500 text-white border-yellow-600 ring-2 ring-yellow-300'],
                                'emerald' => ['default' => 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100', 'active' => 'bg-emerald-500 text-white border-emerald-600 ring-2 ring-emerald-300'],
                                'gray' => ['default' => 'bg-gray-50 text-gray-800 border-gray-300 hover:bg-gray-100', 'active' => 'bg-gray-500 text-white border-gray-600 ring-2 ring-gray-300'],
                            ];
                            $otc = $otColorMap[$ot->color] ?? $otColorMap['gray'];
                        @endphp
                        <button type="button"
                                @click="orderTypeId = {{ $ot->id }}"
                                :class="orderTypeId === {{ $ot->id }} ? '{{ $otc['active'] }}' : '{{ $otc['default'] }}'"
                                class="inline-flex items-center px-4 py-2 border rounded-lg text-sm font-medium transition-all cursor-pointer">
                            {{ $ot->name }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Customer Info (read-only from shipment) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4 p-3 bg-gray-50 rounded-lg">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Customer Name</label>
                    <p class="text-sm font-semibold text-gray-900">{{ $shipment->consignee_name }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Customer Phone</label>
                    <p class="text-sm font-semibold text-gray-900 font-mono">{{ $shipment->consignee_phone_1 }}</p>
                </div>
            </div>

            {{-- Delivery Address --}}
            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">
                        Delivery Address *
                        <span x-show="(!province || !city || !barangay) && showErrors" class="text-red-500 text-xs ml-1">— Incomplete</span>
                    </label>
                    <button type="button" @click="useExistingAddress()" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium underline">Use Existing Address</button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
                    {{-- Province --}}
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Province *</label>
                        <select x-model="province" @change="loadCities()" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select Province</option>
                            <template x-for="p in provinces" :key="p">
                                <option :value="p" x-text="p"></option>
                            </template>
                        </select>
                    </div>
                    {{-- City --}}
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">City/Municipality *</label>
                        <select x-model="city" @change="loadBarangays()" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!province">
                            <option value="">Select City</option>
                            <template x-for="c in cities" :key="c">
                                <option :value="c" x-text="c"></option>
                            </template>
                        </select>
                    </div>
                    {{-- Barangay --}}
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Barangay *</label>
                        <select x-model="barangay" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!city">
                            <option value="">Select Barangay</option>
                            <template x-for="b in barangays" :key="b">
                                <option :value="b" x-text="b"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Address Details --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">House No. / Street / Sitio / Landmark</label>
                    <input type="text" x-model="addressDetails" placeholder="e.g., 123 Rizal St., near Barangay Hall"
                           class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            {{-- Order Items - MOBILE RESPONSIVE --}}
            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">
                        Items *
                        <span x-show="items.some(function(i){ return !i.item_name; }) && showErrors" class="text-red-500 text-xs ml-1">— Item name required</span>
                    </label>
                    <button type="button" @click="addItem()" class="inline-flex items-center text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Add Item
                    </button>
                </div>

                <div class="space-y-3">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="p-3 bg-gray-50 rounded-lg border">
                            {{-- Item Name - FULL WIDTH on top --}}
                            <div class="mb-2">
                                <label class="block text-xs text-gray-500 mb-1">Item Name *</label>
                                <input type="text" x-model="item.item_name" placeholder="Product name"
                                       :class="!item.item_name && showErrors ? 'border-red-400 ring-1 ring-red-400' : 'border-gray-300'"
                                       class="w-full rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            {{-- Qty, Price, Subtotal - in a row below --}}
                            <div class="flex items-end gap-2">
                                {{-- Quantity with +/- buttons --}}
                                <div class="flex-shrink-0">
                                    <label class="block text-xs text-gray-500 mb-1">Qty</label>
                                    <div class="flex items-center border border-gray-300 rounded-md overflow-hidden bg-white">
                                        <button type="button" @click="item.quantity = Math.max(1, item.quantity - 1)"
                                                class="px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-sm transition border-r border-gray-300">
                                            &minus;
                                        </button>
                                        <input type="number" x-model.number="item.quantity" min="1"
                                               class="w-12 text-center border-0 text-sm focus:ring-0 py-1.5 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                        <button type="button" @click="item.quantity++"
                                                class="px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-sm transition border-l border-gray-300">
                                            +
                                        </button>
                                    </div>
                                </div>

                                {{-- Unit Price --}}
                                <div class="flex-1 min-w-0">
                                    <label class="block text-xs text-gray-500 mb-1">Price (₱)</label>
                                    <input type="number" x-model.number="item.unit_price" min="0" step="0.01"
                                           placeholder="0.00"
                                           class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                {{-- Subtotal --}}
                                <div class="flex-shrink-0 text-right">
                                    <label class="block text-xs text-gray-500 mb-1">Subtotal</label>
                                    <p class="text-sm font-semibold text-gray-900 py-1.5 whitespace-nowrap" x-text="'₱' + (item.quantity * item.unit_price).toFixed(2)"></p>
                                </div>

                                {{-- Remove button --}}
                                <div class="flex-shrink-0">
                                    <button type="button" @click="removeItem(index)" x-show="items.length > 1"
                                            class="text-red-400 hover:text-red-600 transition p-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Total --}}
                <div class="mt-3 flex justify-end">
                    <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-2">
                        <span class="text-sm text-green-700">Total COD:</span>
                        <span class="text-lg font-bold text-green-800 ml-2" x-text="'₱' + totalAmount.toFixed(2)"></span>
                    </div>
                </div>
            </div>

            {{-- Process Date --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Process Date *</label>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="processDate = todayStr"
                                :class="processDate === todayStr ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="px-3 py-1.5 rounded-md text-sm font-medium transition whitespace-nowrap">
                            Process Today
                        </button>
                        <input type="date" x-model="processDate"
                               class="flex-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <input type="text" x-model="orderNotes" placeholder="Optional order notes..."
                           class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            {{-- Validation Summary (shows when trying to submit with missing fields) --}}
            <div x-show="showErrors && !isValid" class="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm text-red-700 font-medium">Please complete the following:</p>
                <ul class="text-xs text-red-600 mt-1 list-disc list-inside">
                    <li x-show="!orderTypeId">Select an Order Type</li>
                    <li x-show="!province || !city || !barangay">Complete the delivery address (Province, City, Barangay)</li>
                    <li x-show="items.some(function(i){ return !i.item_name; })">Enter item name for all items</li>
                    <li x-show="items.some(function(i){ return i.quantity < 1; })">Quantity must be at least 1</li>
                    <li x-show="!processDate">Set a process date</li>
                </ul>
            </div>

            {{-- Submit Order --}}
            <div class="flex items-center justify-between pt-3 border-t">
                <div id="order-status-msg" class="text-sm"></div>
                <button type="button" @click="trySubmit()"
                        :disabled="submitting"
                        :class="submitting ? 'opacity-50 cursor-not-allowed' : (isValid ? 'hover:bg-green-700' : 'opacity-75')"
                        class="inline-flex items-center px-5 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg transition shadow-sm">
                    <svg x-show="!submitting" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <svg x-show="submitting" class="w-4 h-4 mr-1.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="submitting ? 'Creating Order...' : 'Create Order'"></span>
                </button>
            </div>

            {{-- Success message area --}}
            <div id="order-success-area" class="hidden mt-3">
                <div class="bg-green-50 border border-green-200 rounded-lg p-3 flex items-center">
                    <svg class="w-5 h-5 text-green-600 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    <span id="order-success-text" class="text-sm text-green-700 font-medium"></span>
                </div>
            </div>
        </div>
    </div>

    {{-- Customer Order History --}}
    @if($customerOrders->isNotEmpty())
    <div class="mt-4 bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 sm:px-6 py-4">
            <h4 class="text-sm font-medium text-gray-500 mb-3 flex items-center">
                <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Previous Orders for this Customer ({{ $customerOrders->count() }})
            </h4>
            <div class="space-y-2">
                @foreach($customerOrders as $co)
                <div class="flex flex-col sm:flex-row sm:items-center justify-between p-2.5 bg-gray-50 rounded-lg text-sm border border-gray-100 gap-1">
                    <div class="flex items-center space-x-3">
                        <span class="font-mono text-xs text-gray-400">#{{ $co->id }}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $co->orderType?->color === 'green' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $co->orderType?->color === 'purple' ? 'bg-purple-100 text-purple-800' : '' }}
                            {{ $co->orderType?->color === 'blue' ? 'bg-blue-100 text-blue-800' : '' }}
                            {{ $co->orderType?->color === 'orange' ? 'bg-orange-100 text-orange-800' : '' }}
                            {{ $co->orderType?->color === 'red' ? 'bg-red-100 text-red-800' : '' }}
                            {{ !in_array($co->orderType?->color, ['green','purple','blue','orange','red']) ? 'bg-gray-100 text-gray-800' : '' }}">
                            {{ $co->orderType?->name ?? 'N/A' }}
                        </span>
                        <span class="text-gray-600">{{ $co->items->count() }} item(s)</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="font-semibold text-gray-900">₱{{ number_format($co->total_amount, 2) }}</span>
                        <span class="text-xs text-gray-500">{{ $co->created_at->format('M d, Y') }}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $co->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                            {{ $co->status === 'confirmed' ? 'bg-green-100 text-green-800' : '' }}
                            {{ !in_array($co->status, ['pending','confirmed']) ? 'bg-gray-100 text-gray-800' : '' }}">
                            {{ ucfirst($co->status) }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
