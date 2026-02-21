<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Upload Import File</h2>
            <a href="{{ route('import.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">View Import History</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <x-alert type="success">{{ session('success') }}</x-alert>
            @endif

            @if($errors->any())
                <x-alert type="error">
                    @foreach($errors->all() as $error)
                        {{ $error }}<br>
                    @endforeach
                </x-alert>
            @endif

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-6 py-6">
                    <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-6">
                            <x-input-label for="courier" value="Courier" />
                            <select id="courier" name="courier" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">-- Select Courier --</option>
                                <option value="jnt" {{ old('courier') === 'jnt' ? 'selected' : '' }}>J&T Express (JNT)</option>
                                <option value="flash" {{ old('courier') === 'flash' ? 'selected' : '' }}>Flash Express</option>
                            </select>
                            <x-input-error :messages="$errors->get('courier')" class="mt-2" />
                        </div>

                        <div class="mb-6">
                            <x-input-label for="file" value="File (.xlsx, .xls, .csv)" />
                            <input type="file" id="file" name="file" accept=".xlsx,.xls,.csv"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                required />
                            <p class="mt-1 text-xs text-gray-500">Max file size: 50MB. JNT files are typically .xlsx, Flash files are typically .csv.</p>
                            <x-input-error :messages="$errors->get('file')" class="mt-2" />
                        </div>

                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                            <p class="text-sm text-blue-700">
                                <strong>Auto-detection:</strong> The system will validate that the uploaded file matches the selected courier format. If there's a mismatch, you'll be notified.
                            </p>
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Upload & Process</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
