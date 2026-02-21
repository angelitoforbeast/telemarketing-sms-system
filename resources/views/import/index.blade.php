<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Import History</h2>
            @can('import.upload')
                <a href="{{ route('import.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Upload New</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <x-alert type="success">{{ session('success') }}</x-alert>
            @endif

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">File</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Courier</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Processed</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">New</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Updated</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Failed</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uploaded By</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($importJobs as $job)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm">
                                        <a href="{{ route('import.show', $job) }}" class="text-indigo-600 hover:text-indigo-900">#{{ $job->id }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ Str::limit($job->original_filename, 30) }}</td>
                                    <td class="px-4 py-3 text-sm"><x-badge :color="$job->courier === 'jnt' ? 'red' : 'orange'">{{ strtoupper($job->courier) }}</x-badge></td>
                                    <td class="px-4 py-3 text-sm">
                                        <x-badge :color="match($job->status) { 'completed' => 'green', 'processing' => 'blue', 'failed' => 'red', default => 'gray' }">{{ ucfirst($job->status) }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ number_format($job->total_rows ?? 0) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ number_format($job->processed_rows ?? 0) }}</td>
                                    <td class="px-4 py-3 text-sm text-green-600">{{ number_format($job->new_shipments ?? 0) }}</td>
                                    <td class="px-4 py-3 text-sm text-blue-600">{{ number_format($job->updated_shipments ?? 0) }}</td>
                                    <td class="px-4 py-3 text-sm text-red-600">{{ number_format($job->failed_rows ?? 0) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $job->user?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $job->created_at->format('M d, H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="px-4 py-6 text-center text-sm text-gray-500">No imports yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t">{{ $importJobs->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
