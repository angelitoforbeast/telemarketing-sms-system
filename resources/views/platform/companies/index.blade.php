<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">All Companies</h2>
            <a href="{{ route('platform.companies.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition">
                + Create Company
            </a>
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
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Users</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shipments</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($companies as $company)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-500">#{{ $company->id }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <a href="{{ route('platform.companies.show', $company) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">{{ $company->name }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <x-badge :color="$company->status === 'active' ? 'green' : 'red'">{{ ucfirst($company->status) }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $company->users_count }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ number_format($company->shipments_count) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $company->created_at->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 text-sm space-x-2">
                                        <a href="{{ route('platform.companies.show', $company) }}" class="text-indigo-600 hover:text-indigo-900 text-xs">View</a>
                                        <form method="POST" action="{{ route('platform.companies.toggle', $company) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs {{ $company->status === 'active' ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }}">
                                                {{ $company->status === 'active' ? 'Suspend' : 'Activate' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">No companies yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t">{{ $companies->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
