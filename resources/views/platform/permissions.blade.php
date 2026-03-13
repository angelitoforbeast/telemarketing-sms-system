<x-app-layout>
    <x-slot name="title">Global Role Permissions</x-slot>

    @php
    $permLabels = [
        // SMS
        'sms.devices.view' => 'View SMS Devices',
        'sms.devices.manage' => 'Manage SMS Devices',
        'sms.blast.send' => 'Send SMS Blast',
        'sms.campaigns.view' => 'View Campaigns',
        'sms.campaigns.create' => 'Create Campaigns',
        'sms.campaigns.edit' => 'Edit Campaigns',
        'sms.campaigns.toggle' => 'Toggle Campaigns',
        'sms.logs.view' => 'View SMS Logs',
        // Import
        'import.upload' => 'Upload Imports',
        'import.view' => 'View Import History',
        // Shipments
        'shipments.view' => 'View Shipments',
        'shipments.assign' => 'Assign Shipments',
        'shipments.auto-assign' => 'Auto-Assign Shipments',
        // Telemarketing
        'telemarketing.view-queue' => 'View Call Queue',
        'telemarketing.log-call' => 'Log Calls',
        'telemarketing.view-all-logs' => 'View All Call Logs',
        'telemarketing.manage-recording-mode' => 'Manage Recording Mode',
        // Users
        'users.view' => 'View Users',
        'users.create' => 'Create Users',
        'users.edit' => 'Edit Users',
        'users.toggle' => 'Activate/Deactivate Users',
        // Dashboard
        'dashboard.view' => 'View Dashboard',
        'dashboard.reports' => 'View Reports',
        // Remittance
        'remittance.view' => 'View Remittance',
        // Settings
        'settings.manage' => 'Manage Settings',
        // Platform
        'platform.manage-all-users' => 'Manage All Users',
        'platform.manage-companies' => 'Manage Companies',
        'platform.view-global-stats' => 'View Global Statistics',
    ];

    $moduleLabels = [
        'Sms' => 'SMS',
        'Import' => 'Import',
        'Shipments' => 'Shipments',
        'Telemarketing' => 'Telemarketing',
        'Users' => 'Users',
        'Dashboard' => 'Dashboard',
        'Remittance' => 'Remittance',
        'Settings' => 'Settings',
        'Platform' => 'Platform',
    ];
    @endphp
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Global Role Permissions</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <p class="text-sm text-gray-600">
                    Set the <strong>maximum allowed permissions</strong> for each role across all companies.
                    Company owners can only enable permissions within these limits.
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

            <form method="POST" action="{{ route('platform.permissions.update') }}">
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
                                    <tr class="bg-indigo-50">
                                        <td colspan="{{ count($roles) + 1 }}" class="px-4 py-2">
                                            <div class="flex items-center">
                                                <span class="text-sm font-bold text-indigo-700">{{ $moduleLabels[$module] ?? $module }}</span>
                                                <span class="ml-2 text-xs text-indigo-500">({{ count($perms) }} permissions)</span>
                                            </div>
                                        </td>
                                    </tr>
                                    @foreach($perms as $perm)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-2.5 text-sm text-gray-700 sticky left-0 bg-white z-10">
                                                <span class="text-sm text-gray-800">
                                                    {{ $permLabels[$perm->name] ?? $perm->name }}
                                                </span>
                                            </td>
                                            @foreach($roles as $role)
                                                @php
                                                    $key = $role->id . '_' . $perm->id;
                                                    $isAllowed = isset($platformSettings[$key]) ? $platformSettings[$key] : false;
                                                @endphp
                                                <td class="px-3 py-2.5 text-center">
                                                    <label class="inline-flex items-center justify-center cursor-pointer">
                                                        <input type="checkbox"
                                                               name="permissions[{{ $key }}]"
                                                               value="1"
                                                               {{ $isAllowed ? 'checked' : '' }}
                                                               class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 transition-colors cursor-pointer">
                                                    </label>
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
                        <p class="text-xs text-gray-500">
                            Changes will affect all companies. Disabling a permission here will also disable it for all company-level overrides.
                        </p>
                        <div class="flex items-center space-x-3">
                            <a href="{{ route('platform.dashboard') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                                Cancel
                            </a>
                            <button type="submit" class="px-6 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Quick toggle buttons --}}
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <span class="text-sm text-gray-500">Quick actions:</span>
                <button type="button" onclick="toggleAll(true)" class="text-xs text-indigo-600 hover:text-indigo-800 underline">
                    Check All
                </button>
                <button type="button" onclick="toggleAll(false)" class="text-xs text-red-600 hover:text-red-800 underline">
                    Uncheck All
                </button>
                @foreach($roles as $role)
                    <button type="button" onclick="toggleRole('{{ $role->id }}')" class="text-xs text-gray-600 hover:text-gray-800 underline">
                        Toggle {{ $role->name }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        function toggleAll(checked) {
            document.querySelectorAll('input[type="checkbox"][name^="permissions"]').forEach(cb => {
                cb.checked = checked;
            });
        }

        function toggleRole(roleId) {
            document.querySelectorAll('input[type="checkbox"][name^="permissions"]').forEach(cb => {
                const key = cb.name.replace('permissions[', '').replace(']', '');
                if (key.startsWith(roleId + '_')) {
                    cb.checked = !cb.checked;
                }
            });
        }
    </script>
</x-app-layout>
