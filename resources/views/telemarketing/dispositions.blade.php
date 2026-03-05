<x-app-layout>
    <x-slot name="title">Dispositions</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Disposition Management</h2>
            <a href="{{ route('telemarketing.dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md bg-gray-100 hover:bg-gray-200 transition">&larr; Back to Dashboard</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Existing Dispositions --}}
                <div class="lg:col-span-2 bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Current Dispositions</h3>
                        <p class="text-sm text-gray-500 mt-1">System dispositions cannot be deleted. Add custom dispositions for your company.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Color</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Final</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Callback</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">DNC</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($dispositions as $d)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center">
                                                <span class="w-3 h-3 rounded-full bg-{{ $d->color }}-500 mr-2"></span>
                                                <span class="text-sm font-medium text-gray-900">{{ $d->name }}</span>
                                            </div>
                                            @if($d->description)
                                                <p class="text-xs text-gray-400 mt-0.5 ml-5">{{ $d->description }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ $d->code }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-block w-6 h-6 rounded-full bg-{{ $d->color }}-500"></span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if($d->is_final)
                                                <svg class="w-5 h-5 text-green-500 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                            @else
                                                <span class="text-gray-300">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if($d->requires_callback)
                                                <svg class="w-5 h-5 text-orange-500 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                            @else
                                                <span class="text-gray-300">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if($d->marks_do_not_call)
                                                <svg class="w-5 h-5 text-red-500 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                            @else
                                                <span class="text-gray-300">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <x-badge :color="$d->is_system ? 'gray' : 'indigo'">{{ $d->is_system ? 'System' : 'Custom' }}</x-badge>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if(!$d->is_system)
                                                <form method="POST" action="{{ route('telemarketing.delete-disposition', $d) }}" class="inline" onsubmit="return confirm('Delete this disposition?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                                </form>
                                            @else
                                                <span class="text-xs text-gray-400">Protected</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Add Custom Disposition --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Add Custom Disposition</h3>
                    </div>
                    <div class="px-6 py-5">
                        <form method="POST" action="{{ route('telemarketing.store-disposition') }}">
                            @csrf
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="disp_name" value="Name *" />
                                    <input type="text" name="name" id="disp_name" required placeholder="e.g., Interested in Promo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>

                                <div>
                                    <x-input-label for="disp_code" value="Code * (unique, snake_case)" />
                                    <input type="text" name="code" id="disp_code" required placeholder="e.g., interested_promo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono">
                                </div>

                                <div>
                                    <x-input-label for="disp_color" value="Color *" />
                                    <select name="color" id="disp_color" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="green">Green</option>
                                        <option value="blue">Blue</option>
                                        <option value="red">Red</option>
                                        <option value="yellow">Yellow</option>
                                        <option value="orange">Orange</option>
                                        <option value="purple">Purple</option>
                                        <option value="indigo">Indigo</option>
                                        <option value="gray" selected>Gray</option>
                                    </select>
                                </div>

                                <div>
                                    <x-input-label for="disp_description" value="Description" />
                                    <input type="text" name="description" id="disp_description" placeholder="Short description..." class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>

                                <div>
                                    <x-input-label for="disp_sort_order" value="Sort Order" />
                                    <input type="number" name="sort_order" id="disp_sort_order" value="50" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>

                                <div class="space-y-2">
                                    <label class="flex items-center text-sm">
                                        <input type="hidden" name="is_final" value="0">
                                        <input type="checkbox" name="is_final" value="1" class="rounded border-gray-300 text-indigo-600 mr-2">
                                        Final disposition (marks shipment as completed)
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="hidden" name="requires_callback" value="0">
                                        <input type="checkbox" name="requires_callback" value="1" class="rounded border-gray-300 text-indigo-600 mr-2">
                                        Requires callback scheduling
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="hidden" name="marks_do_not_call" value="0">
                                        <input type="checkbox" name="marks_do_not_call" value="1" class="rounded border-gray-300 text-indigo-600 mr-2">
                                        Marks as Do Not Call
                                    </label>
                                </div>

                                <x-primary-button>Create Disposition</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
