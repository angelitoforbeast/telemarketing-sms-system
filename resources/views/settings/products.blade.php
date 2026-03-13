<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Products / Item Catalog</h2>
                <p class="text-sm text-gray-500 mt-0.5">Manage your product catalog and pricing tiers</p>
            </div>
            <a href="{{ route('settings.edit') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                &larr; Back to Settings
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 flex items-center gap-3">
                    <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="text-sm text-green-700">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center gap-3">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="text-sm text-red-700">{{ session('error') }}</span>
                </div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                    @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            {{-- Stats Bar --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Products</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ $products->count() }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Active</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">{{ $products->where('is_active', true)->count() }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Inactive</p>
                    <p class="text-2xl font-bold text-gray-400 mt-1">{{ $products->where('is_active', false)->count() }}</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">With Tiers</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1">{{ $products->filter(fn($p) => $p->priceTiers->count() > 0)->count() }}</p>
                </div>
            </div>

            {{-- Add New Product --}}
            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Add New Product
                    </h3>
                </div>
                <form method="POST" action="{{ route('settings.products.store') }}" class="p-5">
                    @csrf
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Product Name *</label>
                            <input type="text" name="name" required placeholder="e.g., UGAT-DAHON-V.III"
                                   class="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400">
                        </div>
                        <div class="w-full sm:w-44">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Default Price (&#8369;) *</label>
                            <input type="number" name="default_price" required step="0.01" min="0" placeholder="0.00"
                                   class="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Add Product
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Products Table --}}
            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        Products ({{ $products->count() }})
                    </h3>
                </div>

                @if($products->isEmpty())
                    <div class="p-8 text-center">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        <p class="text-sm text-gray-500">No products yet. Add your first product above.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($products as $product)
                            <div x-data="productTiers({{ $product->id }}, {{ json_encode($product->priceTiers->map(fn($t) => ['min_qty' => $t->min_qty, 'max_qty' => $t->max_qty, 'price' => (float)$t->price])) }})"
                                 class="transition {{ !$product->is_active ? 'bg-gray-50/50' : '' }}">

                                {{-- Product Row --}}
                                <div class="p-5">
                                    <form method="POST" action="{{ route('settings.products.update', $product) }}">
                                        @csrf
                                        @method('PUT')

                                        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                            {{-- Status Dot --}}
                                            <div class="flex-shrink-0 hidden sm:block">
                                                <div class="w-2.5 h-2.5 rounded-full {{ $product->is_active ? 'bg-green-400' : 'bg-gray-300' }}" title="{{ $product->is_active ? 'Active' : 'Inactive' }}"></div>
                                            </div>

                                            {{-- Product Name --}}
                                            <div class="flex-1 min-w-0">
                                                <input type="text" name="name" value="{{ $product->name }}" required
                                                       class="w-full border-gray-300 rounded-lg shadow-sm text-sm font-medium focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>

                                            {{-- Default Price --}}
                                            <div class="w-full sm:w-36">
                                                <div class="relative">
                                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">&#8369;</span>
                                                    <input type="number" name="default_price" value="{{ $product->default_price }}" required step="0.01" min="0"
                                                           class="w-full pl-7 border-gray-300 rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                </div>
                                            </div>

                                            {{-- Active Toggle --}}
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ $product->is_active ? 'checked' : '' }}>
                                                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                                </label>
                                                <span class="text-xs {{ $product->is_active ? 'text-green-600' : 'text-gray-400' }}">{{ $product->is_active ? 'Active' : 'Off' }}</span>
                                            </div>

                                            {{-- Action Buttons --}}
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <button type="submit" class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-200 transition border border-gray-200">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    Save
                                                </button>
                                                <button type="button" @click="showTiers = !showTiers"
                                                        class="inline-flex items-center px-3 py-2 text-xs font-medium rounded-lg transition border"
                                                        :class="showTiers ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                                    Tiers
                                                    <span class="ml-1 bg-indigo-100 text-indigo-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full" x-text="tiers.length" x-show="tiers.length > 0"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                    {{-- Meta Info --}}
                                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-gray-400 sm:pl-6">
                                        <span>ID: {{ $product->id }}</span>
                                        @if($product->createdBy)
                                            <span>Created by {{ $product->createdBy->name }}</span>
                                        @endif
                                        <span>{{ $product->created_at->format('M d, Y h:i A') }}</span>
                                        @if($product->updatedBy && $product->updated_at->gt($product->created_at->addSeconds(5)))
                                            <span>Last edited by {{ $product->updatedBy->name }} &middot; {{ $product->updated_at->format('M d, Y h:i A') }}</span>
                                        @endif
                                        @if($product->priceTiers->count() > 0)
                                            <span class="text-indigo-400">{{ $product->priceTiers->count() }} price tier(s)</span>
                                        @endif
                                    </div>

                                    {{-- Delete --}}
                                    <div class="mt-2 sm:pl-6">
                                        <form method="POST" action="{{ route('settings.products.destroy', $product) }}" class="inline"
                                              onsubmit="return confirm('Are you sure you want to delete {{ addslashes($product->name) }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-[11px] text-red-400 hover:text-red-600 transition">
                                                Delete product
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                {{-- Price Tiers Panel --}}
                                <div x-show="showTiers" x-collapse class="border-t border-gray-100 bg-gradient-to-b from-indigo-50/30 to-white">
                                    <div class="p-5 sm:pl-11">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                                Quantity-Based Pricing
                                            </h4>
                                            <button type="button" @click="addTier()"
                                                    class="inline-flex items-center px-2.5 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700 transition shadow-sm">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                                Add Tier
                                            </button>
                                        </div>

                                        <template x-if="tiers.length === 0">
                                            <div class="text-sm text-gray-400 italic py-3">
                                                No price tiers configured. Default price (&#8369;{{ number_format($product->default_price, 2) }}) will be used for all quantities.
                                            </div>
                                        </template>

                                        {{-- Tier Headers --}}
                                        <div class="hidden sm:grid grid-cols-12 gap-2 mb-2 text-[11px] font-medium text-gray-500 uppercase tracking-wide" x-show="tiers.length > 0">
                                            <div class="col-span-3">Min Qty</div>
                                            <div class="col-span-3">Max Qty</div>
                                            <div class="col-span-3">Price (&#8369;)</div>
                                            <div class="col-span-2">Preview</div>
                                            <div class="col-span-1"></div>
                                        </div>

                                        {{-- Tier Rows --}}
                                        <div class="space-y-2">
                                            <template x-for="(tier, idx) in tiers" :key="'tier-'+idx">
                                                <div class="grid grid-cols-12 gap-2 items-center bg-white rounded-lg border border-gray-200 p-2.5 sm:p-2">
                                                    <div class="col-span-4 sm:col-span-3">
                                                        <label class="text-[10px] text-gray-400 sm:hidden mb-0.5 block">Min Qty</label>
                                                        <input type="number" x-model.number="tier.min_qty" min="1" placeholder="1"
                                                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    </div>
                                                    <div class="col-span-4 sm:col-span-3">
                                                        <label class="text-[10px] text-gray-400 sm:hidden mb-0.5 block">Max Qty</label>
                                                        <input type="number" x-model.number="tier.max_qty" min="1" placeholder="No limit"
                                                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-300">
                                                    </div>
                                                    <div class="col-span-4 sm:col-span-3">
                                                        <label class="text-[10px] text-gray-400 sm:hidden mb-0.5 block">Price</label>
                                                        <div class="relative">
                                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs">&#8369;</span>
                                                            <input type="number" x-model.number="tier.price" min="0" step="0.01" placeholder="0.00"
                                                                   class="w-full pl-5 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        </div>
                                                    </div>
                                                    <div class="col-span-10 sm:col-span-2 text-xs text-gray-500">
                                                        <span x-text="'Qty ' + (tier.min_qty || '?') + (tier.max_qty ? '-' + tier.max_qty : '+') + ' = &#8369;' + (tier.price || 0).toFixed(2)"></span>
                                                    </div>
                                                    <div class="col-span-2 sm:col-span-1 text-right">
                                                        <button type="button" @click="removeTier(idx)"
                                                                class="inline-flex items-center justify-center w-7 h-7 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-md transition">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>

                                        {{-- Save Tiers Button --}}
                                        <div class="mt-3 flex items-center gap-3" x-show="tiers.length > 0">
                                            <button type="button" @click="saveTiers()"
                                                    :disabled="savingTiers"
                                                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition shadow-sm disabled:opacity-50">
                                                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                <span x-show="!savingTiers">Save Tiers</span>
                                                <span x-show="savingTiers">Saving...</span>
                                            </button>
                                            <span x-show="tierMessage" x-text="tierMessage" class="text-xs text-green-600 font-medium" x-transition></span>
                                        </div>

                                        {{-- Fallback note --}}
                                        <div class="mt-3 text-[11px] text-gray-400 italic" x-show="tiers.length > 0">
                                            Quantities not covered by any tier will use the default price (&#8369;{{ number_format($product->default_price, 2) }}).
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Activity Log --}}
            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Activity Log
                    </h3>
                </div>

                @if($activityLogs->isEmpty())
                    <div class="p-6 text-center text-sm text-gray-400">No activity recorded yet.</div>
                @else
                    <div class="divide-y divide-gray-50">
                        @foreach($activityLogs as $log)
                            <div class="px-5 py-3 flex items-start gap-3 hover:bg-gray-50/50 transition">
                                {{-- Action Icon --}}
                                <div class="flex-shrink-0 mt-0.5">
                                    @switch($log->action)
                                        @case('created')
                                            <div class="w-7 h-7 rounded-full bg-green-100 flex items-center justify-center">
                                                <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                            </div>
                                            @break
                                        @case('updated')
                                            <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center">
                                                <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </div>
                                            @break
                                        @case('deleted')
                                            <div class="w-7 h-7 rounded-full bg-red-100 flex items-center justify-center">
                                                <svg class="w-3.5 h-3.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </div>
                                            @break
                                        @case('tiers_updated')
                                            <div class="w-7 h-7 rounded-full bg-purple-100 flex items-center justify-center">
                                                <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                            </div>
                                            @break
                                        @default
                                            <div class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center">
                                                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </div>
                                    @endswitch
                                </div>

                                {{-- Log Content --}}
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-700">
                                        <span class="font-medium">{{ $log->user_name }}</span>
                                        <span class="text-gray-500">{{ $log->action_label }}</span>
                                        <span class="font-medium">"{{ $log->product_name }}"</span>
                                    </p>
                                    @if($log->details)
                                        <p class="text-[11px] text-gray-400 mt-0.5">
                                            @if(is_array($log->details))
                                                @foreach($log->details as $key => $val)
                                                    @if(is_array($val))
                                                        {{ ucfirst(str_replace('_', ' ', $key)) }}: {{ json_encode($val) }}{{ !$loop->last ? ' | ' : '' }}
                                                    @else
                                                        {{ ucfirst(str_replace('_', ' ', $key)) }}: {{ $val }}{{ !$loop->last ? ' | ' : '' }}
                                                    @endif
                                                @endforeach
                                            @endif
                                        </p>
                                    @endif
                                </div>

                                {{-- Timestamp --}}
                                <div class="flex-shrink-0 text-[11px] text-gray-400 whitespace-nowrap">
                                    {{ $log->created_at->format('M d, h:i A') }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($activityLogs->hasPages())
                        <div class="p-4 border-t border-gray-100">
                            {{ $activityLogs->links() }}
                        </div>
                    @endif
                @endif
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        function productTiers(productId, initialTiers) {
            return {
                showTiers: false,
                tiers: initialTiers || [],
                savingTiers: false,
                tierMessage: '',
                addTier() {
                    var lastMax = this.tiers.length > 0 ? (this.tiers[this.tiers.length - 1].max_qty || this.tiers[this.tiers.length - 1].min_qty) : 0;
                    this.tiers.push({
                        min_qty: lastMax + 1,
                        max_qty: null,
                        price: 0
                    });
                },
                removeTier(idx) {
                    this.tiers.splice(idx, 1);
                },
                saveTiers() {
                    var self = this;
                    self.savingTiers = true;
                    self.tierMessage = '';
                    fetch('/api/products/' + productId + '/tiers', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ tiers: self.tiers })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        self.savingTiers = false;
                        if (data.success) {
                            self.tierMessage = data.message || 'Saved!';
                            setTimeout(function() { self.tierMessage = ''; }, 3000);
                        } else {
                            self.tierMessage = 'Error saving tiers.';
                        }
                    })
                    .catch(function() {
                        self.savingTiers = false;
                        self.tierMessage = 'Error saving tiers.';
                    });
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
