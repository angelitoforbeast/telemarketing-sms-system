<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Upload Import File</h2>
            <a href="{{ route('import.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Import History</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                {{-- Upload Form --}}
                <div id="upload-section">
                    <form id="upload-form" enctype="multipart/form-data">
                        @csrf

                        {{-- Courier Selection --}}
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Courier</label>
                            <div class="flex space-x-4">
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="radio" name="courier" value="jnt" class="text-indigo-600 focus:ring-indigo-500" required>
                                    <span class="text-sm font-medium text-gray-700">J&T Express</span>
                                    <span class="text-xs text-gray-400">(.xlsx)</span>
                                </label>
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="radio" name="courier" value="flash" class="text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm font-medium text-gray-700">Flash Express</span>
                                    <span class="text-xs text-gray-400">(.csv)</span>
                                </label>
                            </div>
                        </div>

                        {{-- File Input --}}
                        <div class="mb-6">
                            <label for="file" class="block text-sm font-medium text-gray-700 mb-2">Select File</label>
                            <input type="file" name="file" id="file" accept=".xlsx,.csv"
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" required>
                            <p class="mt-1 text-xs text-gray-400">Max file size: 200MB. Supported formats: .xlsx, .csv</p>
                        </div>

                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                            <p class="text-sm text-blue-700">
                                <strong>Note:</strong> The file will be uploaded and processed in the background. You can track the progress in real-time below.
                            </p>
                        </div>

                        {{-- Submit --}}
                        <button type="submit" id="btn-upload"
                                class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 font-semibold">
                            Upload & Process
                        </button>
                    </form>
                </div>

                {{-- Progress Section (hidden by default) --}}
                <div id="progress-section" class="hidden">
                    <div class="text-center mb-4">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-100 mb-3">
                            <svg id="spinner" class="animate-spin h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg id="check-icon" class="hidden h-8 w-8 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            <svg id="error-icon" class="hidden h-8 w-8 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h3 id="progress-title" class="text-lg font-semibold text-gray-800">Uploading file...</h3>
                        <p id="progress-subtitle" class="text-sm text-gray-500 mt-1">Please wait while we process your file.</p>
                    </div>

                    {{-- Progress Bar --}}
                    <div class="w-full bg-gray-200 rounded-full h-3 mb-4">
                        <div id="progress-bar" class="bg-indigo-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <p id="progress-text" class="text-xs text-gray-500 text-center mb-6">0%</p>

                    {{-- Stats Grid --}}
                    <div id="stats-grid" class="hidden grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500">Processed</p>
                            <p id="stat-processed" class="text-lg font-bold text-gray-800">0</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-green-600">New</p>
                            <p id="stat-inserted" class="text-lg font-bold text-green-700">0</p>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-blue-600">Updated</p>
                            <p id="stat-updated" class="text-lg font-bold text-blue-700">0</p>
                        </div>
                        <div class="bg-yellow-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-yellow-600">Skipped</p>
                            <p id="stat-skipped" class="text-lg font-bold text-yellow-700">0</p>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div id="action-buttons" class="hidden flex space-x-3">
                        <a href="{{ route('import.index') }}" class="flex-1 text-center bg-gray-100 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-200 font-medium text-sm">
                            View Import History
                        </a>
                        <a href="{{ route('shipments.index') }}" class="flex-1 text-center bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 font-medium text-sm">
                            View Shipments
                        </a>
                    </div>

                    {{-- Upload Another --}}
                    <div id="upload-another" class="hidden mt-4 text-center">
                        <button onclick="resetForm()" class="text-sm text-indigo-600 hover:text-indigo-800 underline">
                            Upload another file
                        </button>
                    </div>

                    {{-- Error Message --}}
                    <div id="error-message" class="hidden bg-red-50 border border-red-200 rounded-lg p-4 mt-4">
                        <p class="text-sm text-red-700" id="error-text"></p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let pollTimer = null;
        let importJobId = null;

        document.getElementById('upload-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);

            // Validate
            const courier = formData.get('courier');
            const file = formData.get('file');
            if (!courier) { alert('Please select a courier.'); return; }
            if (!file || !file.name) { alert('Please select a file.'); return; }

            // Switch to progress view
            document.getElementById('upload-section').classList.add('hidden');
            document.getElementById('progress-section').classList.remove('hidden');
            document.getElementById('progress-title').textContent = 'Uploading file...';
            document.getElementById('progress-subtitle').textContent = 'Sending file to server...';
            document.getElementById('progress-bar').style.width = '5%';
            document.getElementById('progress-text').textContent = 'Uploading...';

            // AJAX upload
            fetch('{{ route("import.store") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: formData,
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => { throw new Error(data.message || 'Upload failed'); });
                }
                return response.json();
            })
            .then(data => {
                importJobId = data.id;
                document.getElementById('progress-title').textContent = 'Processing...';
                document.getElementById('progress-subtitle').textContent = 'Reading and importing rows from your file.';
                document.getElementById('progress-bar').style.width = '10%';
                document.getElementById('progress-text').textContent = 'Processing...';
                document.getElementById('stats-grid').classList.remove('hidden');
                document.getElementById('stats-grid').classList.add('grid');

                // Start polling every 2 seconds
                pollTimer = setInterval(pollStatus, 2000);
            })
            .catch(error => {
                showError(error.message || 'An unexpected error occurred during upload.');
            });
        });

        function pollStatus() {
            if (!importJobId) return;

            fetch('/import/' + importJobId + '/status', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            })
            .then(r => r.json())
            .then(data => {
                // Update stats
                document.getElementById('stat-processed').textContent = numberFormat(data.processed_rows || 0);
                document.getElementById('stat-inserted').textContent = numberFormat(data.new_shipments || 0);
                document.getElementById('stat-updated').textContent = numberFormat(data.updated || 0);
                document.getElementById('stat-skipped').textContent = numberFormat(data.skipped || 0);

                // Update progress bar
                let pct = 15;
                if (data.total_rows && data.total_rows > 0) {
                    pct = Math.min(95, Math.round((data.processed_rows / data.total_rows) * 100));
                } else if (data.processed_rows > 0) {
                    pct = Math.min(90, 15 + Math.round(data.processed_rows / 100));
                }

                if (data.status === 'completed') {
                    pct = 100;
                    clearInterval(pollTimer);
                    showCompleted(data);
                } else if (data.status === 'failed') {
                    clearInterval(pollTimer);
                    showError(data.error_summary?.message || 'Import failed. Check the logs for details.');
                }

                document.getElementById('progress-bar').style.width = pct + '%';
                document.getElementById('progress-text').textContent = pct + '%';
            })
            .catch(err => {
                console.error('Poll error:', err);
            });
        }

        function showCompleted(data) {
            document.getElementById('spinner').classList.add('hidden');
            document.getElementById('check-icon').classList.remove('hidden');
            document.getElementById('progress-title').textContent = 'Import Complete!';
            document.getElementById('progress-subtitle').textContent =
                'Successfully processed ' + numberFormat(data.processed_rows || 0) + ' rows.';
            document.getElementById('progress-bar').classList.remove('bg-indigo-600');
            document.getElementById('progress-bar').classList.add('bg-green-500');
            document.getElementById('action-buttons').classList.remove('hidden');
            document.getElementById('action-buttons').classList.add('flex');
            document.getElementById('upload-another').classList.remove('hidden');
        }

        function showError(message) {
            document.getElementById('spinner').classList.add('hidden');
            document.getElementById('error-icon').classList.remove('hidden');
            document.getElementById('progress-title').textContent = 'Import Failed';
            document.getElementById('progress-subtitle').textContent = '';
            document.getElementById('progress-bar').classList.remove('bg-indigo-600');
            document.getElementById('progress-bar').classList.add('bg-red-500');
            document.getElementById('progress-bar').style.width = '100%';
            document.getElementById('progress-text').textContent = 'Error';
            document.getElementById('error-message').classList.remove('hidden');
            document.getElementById('error-text').textContent = message;
            document.getElementById('upload-another').classList.remove('hidden');
        }

        function resetForm() {
            clearInterval(pollTimer);
            importJobId = null;
            document.getElementById('upload-section').classList.remove('hidden');
            document.getElementById('progress-section').classList.add('hidden');
            document.getElementById('upload-form').reset();

            // Reset icons
            document.getElementById('spinner').classList.remove('hidden');
            document.getElementById('check-icon').classList.add('hidden');
            document.getElementById('error-icon').classList.add('hidden');

            // Reset progress bar
            document.getElementById('progress-bar').style.width = '0%';
            document.getElementById('progress-bar').classList.remove('bg-green-500', 'bg-red-500');
            document.getElementById('progress-bar').classList.add('bg-indigo-600');
            document.getElementById('progress-text').textContent = '0%';

            // Reset stats
            document.getElementById('stat-processed').textContent = '0';
            document.getElementById('stat-inserted').textContent = '0';
            document.getElementById('stat-updated').textContent = '0';
            document.getElementById('stat-skipped').textContent = '0';

            // Hide sections
            document.getElementById('stats-grid').classList.add('hidden');
            document.getElementById('action-buttons').classList.add('hidden');
            document.getElementById('upload-another').classList.add('hidden');
            document.getElementById('error-message').classList.add('hidden');
        }

        function numberFormat(n) {
            return Number(n).toLocaleString();
        }
    </script>
    @endpush
</x-app-layout>
