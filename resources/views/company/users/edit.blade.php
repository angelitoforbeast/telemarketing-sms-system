<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit User: {{ $user->name }}</h2>
            <a href="{{ route('company.users.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">

            @if($errors->any())
                <x-alert type="error">
                    @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
                </x-alert>
            @endif

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-6 py-6">
                    <form method="POST" action="{{ route('company.users.update', $user) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <x-input-label for="name" value="Full Name *" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required />
                        </div>

                        <div class="mb-4">
                            <x-input-label for="email" value="Email Address *" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
                        </div>

                        <div class="mb-4">
                            <x-input-label for="role" value="Role *" />
                            <select id="role" name="role" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($roles as $role)
                                    <option value="{{ $role->name }}" {{ $user->hasRole($role->name) ? 'selected' : '' }}>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Password Change (Optional) --}}
                        <div class="mb-4 border-t border-gray-200 pt-4 mt-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Change Password (optional)</h3>
                            <div class="mb-4">
                                <x-input-label for="password" value="New Password" />
                                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" placeholder="Leave blank to keep current password" />
                            </div>
                            <div class="mb-4">
                                <x-input-label for="password_confirmation" value="Confirm New Password" />
                                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="ml-2 text-sm text-gray-600">Active</span>
                            </label>
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Update User</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
