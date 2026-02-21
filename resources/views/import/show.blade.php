<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Import #{{ $importJob->id }}</h2>
            <a href="{{ route('import.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to History</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <x-alert type="success">{{ session('success') }}</x-alert>
            @endif

            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-6 py-5">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Import Details</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">File Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $importJob->original_filename }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Courier</dt>
                            <dd class="mt-1"><x-badge :color="$importJob->courier === 'jnt' ? 'red' : 'orange'">{{ strtoupper($importJob->courier) }}</x-badge></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1"><x-badge :color="match($importJob->status) { 'completed' => 'green', 'processing' => 'blue', 'failed' => 'red', default => 'gray' }">{{ ucfirst($importJob->status) }}</x-badge></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Uploaded By</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $importJob->user?->name ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Upload Date</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $importJob->created_at->format('M d, Y H:i:s') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Completed At</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $importJob->completed_at?->format('M d, Y H:i:s') ?? 'In progress...' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <x-stat-card title="Total Rows" :value="number_format($importJob->total_rows ?? 0)" color="gray" />
                <x-stat-card title="Processed" :value="number_format($importJob->processed_rows ?? 0)" color="blue" />
                <x-stat-card title="New" :value="number_format($importJob->new_shipments ?? 0)" color="green" />
                <x-stat-card title="Updated" :value="number_format($importJob->updated_shipments ?? 0)" color="indigo" />
                <x-stat-card title="Failed" :value="number_format($importJob->failed_rows ?? 0)" color="red" />
            </div>

            @if($importJob->status === 'failed' && $importJob->error_details)
                <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                    <h4 class="text-sm font-medium text-red-800">Error Details</h4>
                    <pre class="mt-2 text-xs text-red-700 whitespace-pre-wrap">{{ json_encode($importJob->error_details, JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif

            @if($importJob->status === 'processing')
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                    <p class="text-sm text-blue-700">This import is currently being processed. Refresh this page to see updated progress.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
