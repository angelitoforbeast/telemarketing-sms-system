<x-app-layout>
    <x-slot name="title">Users</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Team Members</h2>
            @can('users.create')
                <a href="{{ route('company.users.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Add User</a>
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
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($users as $user)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $user->name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $user->email }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        @foreach($user->roles as $role)
                                            <x-badge color="indigo">{{ $role->name }}</x-badge>
                                        @endforeach
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        @if($user->is_active)
                                            <x-badge color="green">Active</x-badge>
                                        @else
                                            <x-badge color="red">Inactive</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $user->created_at->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 text-sm space-x-2">
                                        @can('users.edit')
                                            <a href="{{ route('company.users.edit', $user) }}" class="text-indigo-600 hover:text-indigo-900 text-xs">Edit</a>
                                        @endcan
                                        @can('users.toggle')
                                            <form method="POST" action="{{ route('company.users.toggle', $user) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs {{ $user->is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }}">
                                                    {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No team members yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t">{{ $users->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
