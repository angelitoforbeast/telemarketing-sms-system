<x-app-layout>
    <x-slot name="title">Order Types</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Order Types</h2>
            <a href="{{ route('settings.telemarketing') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Telemarketing Settings</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Add New Order Type --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Add New Order Type</h3>
                <p class="text-sm text-gray-500 mb-4">Order types categorize orders created by telemarketers (e.g., Reorder, Distributorship). These appear as options when creating new orders during calls.</p>

                <form method="POST" action="{{ route('settings.order-types.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input type="text" name="name" id="name" required
                                   class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   placeholder="e.g., Reorder" value="{{ old('name') }}">
                        </div>
                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                            <input type="text" name="code" id="code"
                                   class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   placeholder="Auto-generated if blank" value="{{ old('code') }}">
                        </div>
                        <div>
                            <label for="color" class="block text-sm font-medium text-gray-700 mb-1">Color *</label>
                            <select name="color" id="color" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="blue">Blue</option>
                                <option value="green">Green</option>
                                <option value="red">Red</option>
                                <option value="orange">Orange</option>
                                <option value="purple">Purple</option>
                                <option value="yellow">Yellow</option>
                                <option value="emerald">Emerald</option>
                                <option value="gray">Gray</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Add Order Type
                        </button>
                    </div>
                </form>
            </div>

            {{-- Existing Order Types --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Existing Order Types ({{ $orderTypes->count() }})</h3>

                @if($orderTypes->isEmpty())
                    <p class="text-sm text-gray-500">No order types configured yet. Add one above to get started.</p>
                @else
                    <div class="space-y-3">
                        @foreach($orderTypes as $type)
                            <div class="border rounded-lg p-4 {{ $type->is_active ? 'border-gray-200' : 'border-gray-200 bg-gray-50 opacity-60' }}">
                                <form method="POST" action="{{ route('settings.order-types.update', $type) }}" class="flex flex-wrap items-center gap-3">
                                    @csrf
                                    @method('PUT')

                                    <div class="flex-1 min-w-[150px]">
                                        <input type="text" name="name" value="{{ $type->name }}" required
                                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div class="w-32">
                                        <input type="text" name="code" value="{{ $type->code }}"
                                               class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                               placeholder="Code">
                                    </div>
                                    <div class="w-28">
                                        <select name="color" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @foreach(['blue','green','red','orange','purple','yellow','emerald','gray'] as $c)
                                                <option value="{{ $c }}" {{ $type->color === $c ? 'selected' : '' }}>{{ ucfirst($c) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ $type->is_active ? 'checked' : '' }}>
                                            <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                        </label>
                                        <span class="text-xs text-gray-500">{{ $type->is_active ? 'Active' : 'Inactive' }}</span>
                                    </div>
                                    <button type="submit" class="px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-medium rounded-md hover:bg-gray-200 transition">
                                        Save
                                    </button>
                                </form>

                                @if(!$type->is_system)
                                <form method="POST" action="{{ route('settings.order-types.destroy', $type) }}" class="inline mt-2"
                                      onsubmit="return confirm('Delete this order type?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700 mt-1">Delete</button>
                                </form>
                                @else
                                    <span class="text-xs text-gray-400 mt-1 inline-block">System default</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
