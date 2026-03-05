<x-app-layout>
    <x-slot name="title">Platform Dashboard</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Platform Admin Dashboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <x-stat-card title="Total Companies" :value="$totalCompanies" color="indigo" />
                <x-stat-card title="Active Companies" :value="$activeCompanies" color="green" />
                <x-stat-card title="Total Users" :value="$totalUsers" color="blue" />
                <x-stat-card title="Total Shipments" :value="number_format($totalShipments)" color="purple" />
                <x-stat-card title="SMS Today" :value="number_format($todaySms)" color="yellow" />
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Recent Companies</h3>
                        <a href="{{ route('platform.companies.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Users</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shipments</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($companies as $company)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm">
                                            <a href="{{ route('platform.companies.show', $company) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">{{ $company->name }}</a>
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <x-badge :color="$company->status === 'active' ? 'green' : 'red'">{{ ucfirst($company->status) }}</x-badge>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $company->users_count }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ number_format($company->shipments_count) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $company->created_at->format('M d, Y') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No companies yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
