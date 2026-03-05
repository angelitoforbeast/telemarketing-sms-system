<x-app-layout>
    <x-slot name="title">Company Details</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Company: {{ $company->name }}</h2>
            <a href="{{ route('platform.companies.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Companies</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <x-alert type="success">{{ session('success') }}</x-alert>
            @endif

            {{-- Company Info --}}
            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-6 py-5">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Company Details</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $company->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1"><x-badge :color="$company->status === 'active' ? 'green' : 'red'">{{ ucfirst($company->status) }}</x-badge></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->created_at->format('M d, Y H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">SMS Provider</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->sms_provider ?? 'Not configured' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">SMS Sender ID</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->sms_sender_id ?? 'Not configured' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">SMS API Key</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $company->sms_api_key ? '••••••••' . substr($company->sms_api_key, -4) : 'Not configured' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <x-stat-card title="Users" :value="$company->users->count()" color="blue" />
                <x-stat-card title="Shipments" :value="number_format($company->shipments_count)" color="indigo" />
                <x-stat-card title="Imports" :value="number_format($company->import_jobs_count)" color="purple" />
                <x-stat-card title="SMS Campaigns" :value="$company->sms_campaigns_count" color="green" />
            </div>

            {{-- Users --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-6 py-5">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Users</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($company->users as $user)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $user->name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $user->email }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @foreach($user->roles as $role)
                                                <x-badge color="indigo">{{ $role->name }}</x-badge>
                                            @endforeach
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <x-badge :color="$user->is_active ? 'green' : 'red'">{{ $user->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $user->created_at->format('M d, Y') }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <div x-data="{ open: false, saving: false }">
                                                <button @click="open = !open" type="button" class="text-xs px-2 py-1 rounded font-medium" :class="open ? 'bg-gray-200 text-gray-700' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100'">
                                                    <span x-show="!open">Change Password</span>
                                                    <span x-show="open">Cancel</span>
                                                </button>
                                                <div x-show="open" x-transition class="mt-2">
                                                    <form method="POST" action="{{ route('platform.companies.users.update-password', [$company, $user]) }}" @submit="saving = true">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="password" name="password" placeholder="New password" required minlength="8" class="block w-full text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 mb-1" />
                                                        <input type="password" name="password_confirmation" placeholder="Confirm password" required minlength="8" class="block w-full text-xs border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 mb-2" />
                                                        <button type="submit" :disabled="saving" class="text-xs px-3 py-1 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                                            <span x-show="!saving">Save</span>
                                                            <span x-show="saving">Saving...</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-4 text-center text-sm text-gray-500">No users.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
