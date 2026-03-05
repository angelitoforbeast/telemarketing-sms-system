<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="text-lg font-bold text-indigo-600">
                        TeleSMS
                    </a>
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    @if(auth()->user()->company_id)
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            Dashboard
                        </x-nav-link>

                        @can('import.upload')
                        <x-nav-link :href="route('import.create')" :active="request()->routeIs('import.*')">
                            Import
                        </x-nav-link>
                        @endcan

                        @can('shipments.view')
                        <x-nav-link :href="route('shipments.index')" :active="request()->routeIs('shipments.*')">
                            Shipments
                        </x-nav-link>
                        @endcan

                        @can('telemarketing.view-queue')
                        <x-nav-link :href="route('telemarketing.queue')" :active="request()->routeIs('telemarketing.*')">
                            Telemarketing
                        </x-nav-link>
                        @endcan

                        @can('sms.campaigns.view')
                        <x-nav-link :href="route('sms.campaigns.index')" :active="request()->routeIs('sms.*')">
                            SMS Campaigns
                        </x-nav-link>
                        @endcan

                        @can('remittance.view')
                        <x-nav-link :href="route('remittance.index')" :active="request()->routeIs('remittance.*')">
                            Remittance
                        </x-nav-link>
                        @endcan

                        @can('users.view')
                        <x-nav-link :href="route('company.users.index')" :active="request()->routeIs('company.users.*')">
                            Users
                        </x-nav-link>
                        @endcan

                        @can('settings.manage')
                        <x-nav-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')">
                            Settings
                        </x-nav-link>
                        @endcan
                    @else
                        {{-- Platform Admin Navigation --}}
                        <x-nav-link :href="route('platform.dashboard')" :active="request()->routeIs('platform.dashboard')">
                            Platform Dashboard
                        </x-nav-link>
                        <x-nav-link :href="route('platform.companies.index')" :active="request()->routeIs('platform.companies.*')">
                            Companies
                        </x-nav-link>
                        <x-nav-link :href="route('platform.permissions')" :active="request()->routeIs('platform.permissions*')">
                            Role Permissions
                        </x-nav-link>
                        <x-nav-link :href="route('platform.ai-settings')" :active="request()->routeIs('platform.ai-settings*')">
                            AI Settings
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                @if(auth()->user()->company_id && auth()->user()->company)
                    <span class="text-xs text-gray-400 mr-4">{{ auth()->user()->company->name }}</span>
                @endif
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>
                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                Log Out
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            @if(auth()->user()->company_id)
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-responsive-nav-link>
                @can('import.upload')
                <x-responsive-nav-link :href="route('import.create')" :active="request()->routeIs('import.*')">Import</x-responsive-nav-link>
                @endcan
                @can('shipments.view')
                <x-responsive-nav-link :href="route('shipments.index')" :active="request()->routeIs('shipments.*')">Shipments</x-responsive-nav-link>
                @endcan
                @can('telemarketing.view-queue')
                <x-responsive-nav-link :href="route('telemarketing.queue')" :active="request()->routeIs('telemarketing.*')">Telemarketing</x-responsive-nav-link>
                @endcan
                @can('sms.campaigns.view')
                <x-responsive-nav-link :href="route('sms.campaigns.index')" :active="request()->routeIs('sms.*')">SMS Campaigns</x-responsive-nav-link>
                @endcan
                @can('remittance.view')
                <x-responsive-nav-link :href="route('remittance.index')" :active="request()->routeIs('remittance.*')">Remittance</x-responsive-nav-link>
                @endcan
                @can('users.view')
                <x-responsive-nav-link :href="route('company.users.index')" :active="request()->routeIs('company.users.*')">Users</x-responsive-nav-link>
                @endcan
                @can('settings.manage')
                <x-responsive-nav-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')">Settings</x-responsive-nav-link>
                @endcan
            @else
                <x-responsive-nav-link :href="route('platform.dashboard')" :active="request()->routeIs('platform.dashboard')">Platform Dashboard</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('platform.companies.index')" :active="request()->routeIs('platform.companies.*')">Companies</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('platform.permissions')" :active="request()->routeIs('platform.permissions*')">Role Permissions</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('platform.ai-settings')" :active="request()->routeIs('platform.ai-settings*')">AI Settings</x-responsive-nav-link>
            @endif
        </div>
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">Profile</x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log Out</x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
