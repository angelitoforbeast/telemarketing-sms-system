<x-app-layout>
    <x-slot name="title">Create User</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add Team Member</h2>
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
                    <form method="POST" action="{{ route('company.users.store') }}">
                        @csrf

                        <div class="mb-4">
                            <x-input-label for="name" value="Full Name *" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                        </div>

                        <div class="mb-4">
                            <x-input-label for="email" value="Email Address *" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                        </div>

                        <div class="mb-4">
                            <x-input-label for="role" value="Role *" />
                            <select id="role" name="role" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- Select Role --</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-4">
                            <x-input-label for="password" value="Password *" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                        </div>

                        <div class="mb-4">
                            <x-input-label for="password_confirmation" value="Confirm Password *" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required />
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Create User</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
