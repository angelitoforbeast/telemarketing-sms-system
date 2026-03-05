<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Company</h2>
            <a href="{{ route('platform.companies.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Companies</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-6">Company Details</h3>

                <form method="POST" action="{{ route('platform.companies.store') }}">
                    @csrf

                    <div class="space-y-6">
                        {{-- Company Name --}}
                        <div>
                            <label for="company_name" class="block text-sm font-medium text-gray-700">Company Name <span class="text-red-500">*</span></label>
                            <input type="text" name="company_name" id="company_name"
                                   value="{{ old('company_name') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                   required>
                            @error('company_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Contact Email --}}
                        <div>
                            <label for="contact_email" class="block text-sm font-medium text-gray-700">Company Contact Email</label>
                            <input type="email" name="contact_email" id="contact_email"
                                   value="{{ old('contact_email') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('contact_email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Contact Phone --}}
                        <div>
                            <label for="contact_phone" class="block text-sm font-medium text-gray-700">Company Contact Phone</label>
                            <input type="text" name="contact_phone" id="contact_phone"
                                   value="{{ old('contact_phone') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('contact_phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Address --}}
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                            <input type="text" name="address" id="address"
                                   value="{{ old('address') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <hr class="my-6">

                    <h3 class="text-lg font-semibold text-gray-800 mb-6">Owner Account</h3>
                    <p class="text-sm text-gray-500 mb-4">This will be the first user (Company Owner) for this company.</p>

                    <div class="space-y-6">
                        {{-- Owner Name --}}
                        <div>
                            <label for="owner_name" class="block text-sm font-medium text-gray-700">Owner Name <span class="text-red-500">*</span></label>
                            <input type="text" name="owner_name" id="owner_name"
                                   value="{{ old('owner_name') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                   required>
                            @error('owner_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Owner Email --}}
                        <div>
                            <label for="owner_email" class="block text-sm font-medium text-gray-700">Owner Email <span class="text-red-500">*</span></label>
                            <input type="email" name="owner_email" id="owner_email"
                                   value="{{ old('owner_email') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                   required>
                            @error('owner_email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Owner Password --}}
                        <div>
                            <label for="owner_password" class="block text-sm font-medium text-gray-700">Owner Password <span class="text-red-500">*</span></label>
                            <input type="password" name="owner_password" id="owner_password"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                   required>
                            @error('owner_password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Confirm Password --}}
                        <div>
                            <label for="owner_password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password <span class="text-red-500">*</span></label>
                            <input type="password" name="owner_password_confirmation" id="owner_password_confirmation"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                   required>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <a href="{{ route('platform.companies.index') }}" class="mr-3 inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                            Create Company
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
