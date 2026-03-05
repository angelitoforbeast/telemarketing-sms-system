<x-app-layout>
    <x-slot name="title">Role Permissions</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Role Permissions</h2>
            <a href="{{ route('settings.edit') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Settings</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <p class="text-sm text-gray-600">
                    Manage what each role can access in your company. Permissions grayed out are restricted by the platform administrator.
                </p>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('settings.role-permissions.update') }}">
                @csrf
                @method('PUT')

                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider sticky left-0 bg-gray-50 z-10 min-w-[220px]">
                                        Permission
                                    </th>
                                    @foreach($roles as $role)
                                        <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider min-w-[120px]">
                                            {{ $role->name }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($grouped as $module => $perms)
                                    {{-- Module header row --}}
                                    <tr class="bg-emerald-50">
                                        <td colspan="{{ count($roles) + 1 }}" class="px-4 py-2">
                                            <div class="flex items-center">
                                                <span class="text-sm font-bold text-emerald-700">{{ $module }}</span>
                                                <span class="ml-2 text-xs text-emerald-500">({{ count($perms) }} permissions)</span>
                                            </div>
                                        </td>
                                    </tr>
                                    @foreach($perms as $perm)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-2.5 text-sm text-gray-700 sticky left-0 bg-white z-10">
                                                <code class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">
                                                    {{ $perm->name }}
                                                </code>
                                            </td>
                                            @foreach($roles as $role)
                                                @php
                                                    $key = $role->id . '_' . $perm->id;
                                                    $platformAllowed = isset($platformSettings[$key]) ? $platformSettings[$key] : false;
                                                    $companyEnabled = isset($companySettings[$key]) ? $companySettings[$key] : false;
                                                    // If no company setting exists yet, default to platform allowed
                                                    $isEnabled = $companySettings->has($key) ? $companyEnabled : $platformAllowed;
                                                @endphp
                                                <td class="px-3 py-2.5 text-center">
                                                    @if($platformAllowed)
                                                        <label class="inline-flex items-center justify-center cursor-pointer">
                                                            <input type="checkbox"
                                                                   name="permissions[{{ $key }}]"
                                                                   value="1"
                                                                   {{ $isEnabled ? 'checked' : '' }}
                                                                   class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500 transition-colors cursor-pointer">
                                                        </label>
                                                    @else
                                                        {{-- Blocked by platform admin --}}
                                                        <span class="inline-flex items-center justify-center" title="Restricted by platform administrator">
                                                            <svg class="h-4 w-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                            </svg>
                                                        </span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Footer with save button --}}
                    <div class="bg-gray-50 px-4 py-3 border-t border-gray-200 flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <p class="text-xs text-gray-500">
                                Lock icon = restricted by platform admin and cannot be enabled.
                            </p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <a href="{{ route('settings.edit') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                                Cancel
                            </a>
                            <button type="submit" class="px-6 py-2 text-sm font-medium text-white bg-emerald-600 border border-transparent rounded-md hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-colors">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Legend --}}
            <div class="mt-4 flex items-center gap-6 text-xs text-gray-500">
                <div class="flex items-center gap-1.5">
                    <input type="checkbox" checked disabled class="h-3.5 w-3.5 text-emerald-600 border-gray-300 rounded">
                    <span>Enabled</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <input type="checkbox" disabled class="h-3.5 w-3.5 text-emerald-600 border-gray-300 rounded">
                    <span>Disabled</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <span>Restricted by platform</span>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
