<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Products / Item Catalog</h2>
            <a href="{{ route('settings.edit') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Settings</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                    @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            {{-- Add New Product --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Add New Product</h3>
                <form method="POST" action="{{ route('settings.products.store') }}" class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                    @csrf
                    <div class="flex-1 w-full sm:w-auto">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product Name *</label>
                        <input type="text" name="name" required placeholder="e.g., UGAT-DAHON-V.III"
                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="w-full sm:w-40">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default Price (&#8369;) *</label>
                        <input type="number" name="default_price" required min="0" step="0.01" placeholder="0.00"
                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition shadow-sm whitespace-nowrap">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Add Product
                    </button>
                </form>
            </div>

            {{-- Existing Products --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Products ({{ $products->count() }})</h3>

                @if($products->isEmpty())
                    <p class="text-sm text-gray-500">No products yet. Add one above to get started.</p>
                @else
                    <div class="space-y-4">
                        @foreach($products as $product)
                            <div class="border rounded-lg {{ $product->is_active ? 'border-gray-200' : 'border-gray-200 bg-gray-50 opacity-60' }}"
                                 x-data="productTiers({{ $product->id }}, {{ json_encode($product->priceTiers->map(fn($t) => ['min_qty' => $t->min_qty, 'max_qty' => $t->max_qty, 'price' => (float)$t->price])) }})">

                                {{-- Product Header --}}
                                <div class="p-4">
                                    <form method="POST" action="{{ route('settings.products.update', $product) }}" class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                        @csrf
                                        @method('PUT')
                                        <div class="flex-1 min-w-0 w-full sm:w-auto">
                                            <input type="text" name="name" value="{{ $product->name }}" required
                                                   class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 font-medium">
                                        </div>
                                        <div class="w-full sm:w-36">
                                            <div class="flex items-center">
                                                <span class="text-sm text-gray-500 mr-1">&#8369;</span>
                                                <input type="number" name="default_price" value="{{ $product->default_price }}" required min="0" step="0.01"
                                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="hidden" name="is_active" value="0">
                                                <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ $product->is_active ? 'checked' : '' }}>
                                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                            </label>
                                            <span class="text-xs text-gray-500">{{ $product->is_active ? 'Active' : 'Inactive' }}</span>
                                        </div>
                                        <button type="submit" class="px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-medium rounded-md hover:bg-gray-200 transition">
                                            Save
                                        </button>
                                    </form>

                                    <div class="flex items-center gap-3 mt-2">
                                        {{-- Toggle Price Tiers --}}
                                        <button type="button" @click="showTiers = !showTiers"
                                                class="inline-flex items-center text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                            <svg class="w-3.5 h-3.5 mr-1 transition-transform" :class="showTiers ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                            Price Tiers (<span x-text="tiers.length"></span>)
                                        </button>

                                        {{-- Delete --}}
                                        <form method="POST" action="{{ route('settings.products.destroy', $product) }}" class="inline"
                                              onsubmit="return confirm('Delete this product?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                        </form>
                                    </div>
                                </div>

                                {{-- Price Tiers Section (collapsible) --}}
                                <div x-show="showTiers" x-transition class="border-t bg-gray-50 p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-medium text-gray-700">
                                            Quantity-Based Price Tiers
                                            <span class="text-xs text-gray-400 font-normal ml-1">(auto-applies when telemarketer changes qty)</span>
                                        </h4>
                                        <button type="button" @click="addTier()"
                                                class="inline-flex items-center text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                            Add Tier
                                        </button>
                                    </div>

                                    <template x-if="tiers.length === 0">
                                        <p class="text-xs text-gray-500 italic">No price tiers set. Default price (&#8369;{{ number_format($product->default_price, 2) }}) will be used for all quantities.</p>
                                    </template>

                                    <div class="space-y-2">
                                        <template x-for="(tier, idx) in tiers" :key="idx">
                                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 bg-white p-2.5 rounded-md border border-gray-200">
                                                <div class="flex items-center gap-1 w-full sm:w-auto">
                                                    <span class="text-xs text-gray-500 whitespace-nowrap">Qty</span>
                                                    <input type="number" x-model.number="tier.min_qty" min="1" placeholder="Min"
                                                           class="w-20 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    <span class="text-xs text-gray-400">to</span>
                                                    <input type="number" x-model.number="tier.max_qty" min="1" placeholder="No limit"
                                                           class="w-20 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                </div>
                                                <div class="flex items-center gap-1 w-full sm:w-auto">
                                                    <span class="text-xs text-gray-500">&#8369;</span>
                                                    <input type="number" x-model.number="tier.price" min="0" step="0.01" placeholder="Price"
                                                           class="w-28 border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                </div>
                                                <button type="button" @click="removeTier(idx)" class="text-red-400 hover:text-red-600 transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                </button>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="mt-3 flex items-center gap-3" x-show="tiers.length > 0">
                                        <button type="button" @click="saveTiers()"
                                                :disabled="savingTiers"
                                                class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-md hover:bg-green-700 transition shadow-sm">
                                            <span x-show="!savingTiers">Save Tiers</span>
                                            <span x-show="savingTiers">Saving...</span>
                                        </button>
                                        <span x-show="tierMessage" x-text="tierMessage" class="text-xs text-green-600" x-transition></span>
                                    </div>

                                    {{-- Tier Preview --}}
                                    <div class="mt-3 text-xs text-gray-500" x-show="tiers.length > 0">
                                        <p class="font-medium text-gray-600 mb-1">Preview:</p>
                                        <template x-for="(tier, idx) in tiers" :key="'preview-'+idx">
                                            <p>
                                                <span x-text="'Qty ' + tier.min_qty + (tier.max_qty ? ' - ' + tier.max_qty : '+')"></span>
                                                = <span class="font-semibold" x-text="'₱' + (tier.price || 0).toFixed(2)"></span> each
                                            </p>
                                        </template>
                                        <p class="mt-1 italic">Other quantities = &#8369;{{ number_format($product->default_price, 2) }} (default)</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
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
