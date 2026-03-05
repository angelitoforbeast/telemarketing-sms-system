<x-app-layout>
    <x-slot name="title">Telemarketing Assignments</x-slot>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Assignment Management</h2>
            <a href="{{ route('telemarketing.dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md bg-gray-100 hover:bg-gray-200 transition">&larr; Back to Dashboard</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            <div id="flash-container">
                @if(session('success'))
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
                @endif
                @if(session('info'))
                    <div class="mb-4 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg">{{ session('info') }}</div>
                @endif
                @if(session('error'))
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
                @endif
            </div>

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- AGENT STATUS ASSIGNMENTS --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="mb-6 bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Agent Status Assignments</h3>
                    <p class="text-sm text-gray-500 mt-1">Set which shipment statuses each telemarketer handles. Agents will only see shipments matching their assigned statuses. Leave blank = all statuses.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                @foreach($statuses as $status)
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">{{ $status->name }}</th>
                                @endforeach
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($telemarketers as $tm)
                                <tr class="hover:bg-gray-50 {{ !$tm->is_telemarketing_active ? 'opacity-50' : '' }}" id="agent-row-{{ $tm->id }}">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 {{ $tm->is_telemarketing_active ? 'bg-indigo-100' : 'bg-gray-200' }} rounded-full flex items-center justify-center">
                                                <span class="text-xs font-bold {{ $tm->is_telemarketing_active ? 'text-indigo-600' : 'text-gray-400' }}">{{ strtoupper(substr($tm->name, 0, 2)) }}</span>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900">{{ $tm->name }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        {{-- Active/Inactive Toggle --}}
                                        <button type="button"
                                                onclick="toggleAgentActive({{ $tm->id }}, '{{ addslashes($tm->name) }}', this)"
                                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 {{ $tm->is_telemarketing_active ? 'bg-green-500' : 'bg-gray-300' }}"
                                                data-active="{{ $tm->is_telemarketing_active ? '1' : '0' }}"
                                                role="switch"
                                                aria-checked="{{ $tm->is_telemarketing_active ? 'true' : 'false' }}"
                                                title="{{ $tm->is_telemarketing_active ? 'Active — click to deactivate' : 'Inactive — click to activate' }}">
                                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $tm->is_telemarketing_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                        </button>
                                        <span class="block text-xs mt-0.5 {{ $tm->is_telemarketing_active ? 'text-green-600' : 'text-gray-400' }}" id="active-label-{{ $tm->id }}">
                                            {{ $tm->is_telemarketing_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    @foreach($statuses as $status)
                                        <td class="px-3 py-3 text-center">
                                            <input type="checkbox"
                                                   class="agent-status-cb rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                   data-agent-id="{{ $tm->id }}"
                                                   data-status-id="{{ $status->id }}"
                                                   {{ in_array($status->id, $agentStatusMap[$tm->id] ?? []) ? 'checked' : '' }}
                                                   onchange="markAgentDirty({{ $tm->id }})">
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-3 text-center">
                                        <button type="button"
                                                id="save-agent-{{ $tm->id }}"
                                                onclick="saveAgentStatuses({{ $tm->id }}, this)"
                                                class="hidden inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700 transition">
                                            <span class="btn-text">Save</span>
                                            <svg class="btn-spinner hidden animate-spin ml-1 h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        </button>
                                        <span id="saved-agent-{{ $tm->id }}" class="{{ !empty($agentStatusMap[$tm->id] ?? []) ? '' : 'hidden' }} text-xs text-green-600 font-medium">
                                            @if(!empty($agentStatusMap[$tm->id] ?? []))
                                                ✓ {{ count($agentStatusMap[$tm->id]) }} status{{ count($agentStatusMap[$tm->id]) > 1 ? 'es' : '' }}
                                            @else
                                                All
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                    <p class="text-xs text-gray-500">
                        <strong>Tip:</strong> Toggle the switch to set an agent Active/Inactive. Inactive agents won't receive new assignments. Check specific statuses to restrict what an agent sees.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Manual Assignment Panel --}}
                <div class="bg-white shadow rounded-lg" style="overflow: visible;">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Manual Assignment</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            Assign unassigned shipments to a telemarketer
                            (<span id="unassigned-count">{{ $unassignedCount }}</span> unassigned
                            @if($onCooldownCount > 0)
                                , <span class="text-amber-600">{{ $onCooldownCount }} on cooldown</span>
                            @endif)
                        </p>
                    </div>
                    <div class="px-6 py-5">
                        <form id="manual-assign-form" onsubmit="return handleManualAssign(event)">
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="telemarketer_id" value="Assign To *" />
                                    <select name="telemarketer_id" id="telemarketer_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Select Telemarketer --</option>
                                        @foreach($telemarketers as $tm)
                                            <option value="{{ $tm->id }}"
                                                    data-active="{{ $tm->is_telemarketing_active ? '1' : '0' }}"
                                                    data-statuses="{{ json_encode($agentStatusMap[$tm->id] ?? []) }}">
                                                {{ $tm->name }} ({{ $tm->pending_count }} pending) {{ !$tm->is_telemarketing_active ? '⛔ INACTIVE' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <x-input-label for="status_id" value="Filter by Status (optional)" />
                                    <select name="status_id" id="status_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">Any Status</option>
                                        @foreach($statuses as $status)
                                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Strict Assign Warning --}}
                                <div id="strict-assign-warning" class="hidden bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-lg text-sm">
                                    <strong>⚠ Status Mismatch:</strong> <span id="strict-assign-msg"></span>
                                </div>

                                <div>
                                    <x-input-label for="courier" value="Filter by Courier (optional)" />
                                    <select name="courier" id="courier" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">Any Courier</option>
                                        <option value="jnt">JNT</option>
                                        <option value="flash">Flash Express</option>
                                    </select>
                                </div>

                                {{-- Shop Name multi-select --}}
                                <div class="relative" :style="open ? 'z-index: 9999' : ''" x-data="assignMultiSelect('sender_name', {{ $shopNames->toJson() }})">
                                    <x-input-label value="Filter by Shop Name (optional)" class="mb-1" />
                                    <div @click="open = !open" class="w-full border rounded-lg text-sm bg-white cursor-pointer transition-all duration-150 min-h-[40px] flex flex-wrap items-center gap-1.5 px-3 py-2"
                                         :class="open ? 'border-indigo-500 ring-2 ring-indigo-100 shadow-md' : 'border-gray-300 shadow-sm hover:border-gray-400'">
                                        <span x-show="selected.length === 0" class="text-gray-400 select-none">All Shops</span>
                                        <template x-for="tag in selected.slice(0, 3)" :key="tag">
                                            <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 text-xs font-medium px-2 py-0.5 rounded-full border border-indigo-200">
                                                <span x-text="tag.length > 18 ? tag.substring(0,18)+'...' : tag"></span>
                                                <button type="button" @click.stop="toggleItem(tag)" class="text-indigo-400 hover:text-indigo-700 ml-0.5">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                                                </button>
                                            </span>
                                        </template>
                                        <span x-show="selected.length > 3" class="text-xs text-indigo-500 font-semibold" x-text="'+' + (selected.length - 3) + ' more'"></span>
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0 ml-auto transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </div>
                                    <div x-show="open" x-cloak @click.away="open = false"
                                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1"
                                         class="absolute z-[9999] w-full bg-white border border-gray-200 rounded-xl shadow-xl ring-1 ring-black/5" style="margin-top: 6px;">
                                        <div class="p-2.5 border-b border-gray-100">
                                            <div class="relative">
                                                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                                <input type="text" x-model="searchText" placeholder="Search shops..." autocomplete="off"
                                                    class="w-full pl-8 pr-3 py-2 border-gray-200 rounded-lg shadow-sm text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 bg-gray-50 placeholder-gray-400" @click.stop />
                                            </div>
                                        </div>
                                        <div x-show="selected.length > 0" class="px-2.5 pt-2 pb-1 border-b border-gray-100">
                                            <p class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold mb-1">Selected (<span x-text="selected.length"></span>)</p>
                                        </div>
                                        <div style="max-height: 220px; overflow-y: auto; scrollbar-width: thin;">
                                            <template x-for="item in filteredItems" :key="item">
                                                <label class="flex items-center px-3 py-2 text-sm cursor-pointer transition-colors duration-75 hover:bg-gray-50 text-gray-700"
                                                       :style="selected.includes(item) ? 'background-color: #eef2ff; color: #3730a3;' : ''" @click.stop>
                                                    <span class="flex items-center justify-center w-4 h-4 rounded border mr-2.5 flex-shrink-0 transition-colors duration-75"
                                                          :style="selected.includes(item) ? 'background-color: #4f46e5; border-color: #4f46e5;' : 'border-color: #d1d5db; background-color: white;'">
                                                        <svg x-show="selected.includes(item)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                    </span>
                                                    <input type="checkbox" :value="item" :checked="selected.includes(item)" @change="toggleItem(item)" class="sr-only" />
                                                    <span x-text="item" class="truncate"></span>
                                                </label>
                                            </template>
                                            <div x-show="filteredItems.length === 0" class="px-3 py-4 text-sm text-gray-400 text-center">
                                                <svg class="w-5 h-5 mx-auto mb-1 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                No shops found
                                            </div>
                                        </div>
                                        <div class="px-3 py-2.5 border-t border-gray-100 flex items-center justify-between bg-gray-50/50 rounded-b-xl">
                                            <button type="button" @click="clearAll()" class="text-xs text-red-500 hover:text-red-700 font-medium transition-colors" x-show="selected.length > 0">Clear All</button>
                                            <span x-show="selected.length === 0" class="text-xs text-gray-400">Select one or more shops</span>
                                            <button type="button" @click="open = false" class="text-xs bg-indigo-600 text-white px-3 py-1 rounded-md hover:bg-indigo-700 font-medium transition-colors shadow-sm">Done</button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Item Name multi-select --}}
                                <div class="relative" :style="open ? 'z-index: 9998' : ''" x-data="assignMultiSelect('item_description', {{ $itemDescriptions->toJson() }})">
                                    <x-input-label value="Filter by Item Name (optional)" class="mb-1" />
                                    <div @click="open = !open" class="w-full border rounded-lg text-sm bg-white cursor-pointer transition-all duration-150 min-h-[40px] flex flex-wrap items-center gap-1.5 px-3 py-2"
                                         :class="open ? 'border-indigo-500 ring-2 ring-indigo-100 shadow-md' : 'border-gray-300 shadow-sm hover:border-gray-400'">
                                        <span x-show="selected.length === 0" class="text-gray-400 select-none">All Items</span>
                                        <template x-for="tag in selected.slice(0, 3)" :key="tag">
                                            <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 text-xs font-medium px-2 py-0.5 rounded-full border border-indigo-200">
                                                <span x-text="tag.length > 18 ? tag.substring(0,18)+'...' : tag"></span>
                                                <button type="button" @click.stop="toggleItem(tag)" class="text-indigo-400 hover:text-indigo-700 ml-0.5">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                                                </button>
                                            </span>
                                        </template>
                                        <span x-show="selected.length > 3" class="text-xs text-indigo-500 font-semibold" x-text="'+' + (selected.length - 3) + ' more'"></span>
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0 ml-auto transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </div>
                                    <div x-show="open" x-cloak @click.away="open = false"
                                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1"
                                         class="absolute z-[9999] w-full bg-white border border-gray-200 rounded-xl shadow-xl ring-1 ring-black/5" style="margin-top: 6px;">
                                        <div class="p-2.5 border-b border-gray-100">
                                            <div class="relative">
                                                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                                <input type="text" x-model="searchText" placeholder="Search items..." autocomplete="off"
                                                    class="w-full pl-8 pr-3 py-2 border-gray-200 rounded-lg shadow-sm text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 bg-gray-50 placeholder-gray-400" @click.stop />
                                            </div>
                                        </div>
                                        <div x-show="selected.length > 0" class="px-2.5 pt-2 pb-1 border-b border-gray-100">
                                            <p class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold mb-1">Selected (<span x-text="selected.length"></span>)</p>
                                        </div>
                                        <div style="max-height: 220px; overflow-y: auto; scrollbar-width: thin;">
                                            <template x-for="item in filteredItems" :key="item">
                                                <label class="flex items-center px-3 py-2 text-sm cursor-pointer transition-colors duration-75 hover:bg-gray-50 text-gray-700"
                                                       :style="selected.includes(item) ? 'background-color: #eef2ff; color: #3730a3;' : ''" @click.stop>
                                                    <span class="flex items-center justify-center w-4 h-4 rounded border mr-2.5 flex-shrink-0 transition-colors duration-75"
                                                          :style="selected.includes(item) ? 'background-color: #4f46e5; border-color: #4f46e5;' : 'border-color: #d1d5db; background-color: white;'">
                                                        <svg x-show="selected.includes(item)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                    </span>
                                                    <input type="checkbox" :value="item" :checked="selected.includes(item)" @change="toggleItem(item)" class="sr-only" />
                                                    <span x-text="item" class="truncate"></span>
                                                </label>
                                            </template>
                                            <div x-show="filteredItems.length === 0" class="px-3 py-4 text-sm text-gray-400 text-center">
                                                <svg class="w-5 h-5 mx-auto mb-1 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                No items found
                                            </div>
                                        </div>
                                        <div class="px-3 py-2.5 border-t border-gray-100 flex items-center justify-between bg-gray-50/50 rounded-b-xl">
                                            <button type="button" @click="clearAll()" class="text-xs text-red-500 hover:text-red-700 font-medium transition-colors" x-show="selected.length > 0">Clear All</button>
                                            <span x-show="selected.length === 0" class="text-xs text-gray-400">Select one or more items</span>
                                            <button type="button" @click="open = false" class="text-xs bg-indigo-600 text-white px-3 py-1 rounded-md hover:bg-indigo-700 font-medium transition-colors shadow-sm">Done</button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Filter by Disposition (optional) --}}
                                <div class="relative" x-data="assignMultiSelect('disposition', [
                                    {id: 'no_disposition', name: 'No Disposition', desc: 'Never been called'},
                                    {id: 'no_disposition_today', name: 'No Disposition Today', desc: 'No call yet today'},
                                    @foreach($dispositions as $disp)
                                        {id: '{{ $disp->id }}', name: '{{ addslashes($disp->name) }}', desc: '{{ addslashes($disp->description ?? '') }}'},
                                    @endforeach
                                ], [])" :style="open ? 'z-index: 9997' : ''">
                                    <x-input-label value="Filter by Disposition (optional)" />
                                    <div @click="open = !open" class="mt-1 w-full border border-gray-300 rounded-md shadow-sm text-sm px-3 py-2 bg-white cursor-pointer flex items-center gap-1.5 min-h-[38px] flex-wrap"
                                         :style="open ? 'border-color: #818cf8; box-shadow: 0 0 0 2px rgba(99,102,241,0.15);' : ''">
                                        <span x-show="selected.length === 0" class="text-gray-400">All Dispositions</span>
                                        <template x-for="tag in selected.slice(0, 3)" :key="tag">
                                            <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 text-xs font-medium px-2 py-0.5 rounded-full border border-amber-200">
                                                <span x-text="getDisplayName(tag).length > 16 ? getDisplayName(tag).substring(0,16)+'...' : getDisplayName(tag)"></span>
                                                <button type="button" @click.stop="toggleItem(tag)" class="text-amber-400 hover:text-amber-700 ml-0.5">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                                                </button>
                                            </span>
                                        </template>
                                        <span x-show="selected.length > 3" class="text-xs text-amber-500 font-semibold" x-text="'+' + (selected.length - 3) + ' more'"></span>
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0 ml-auto transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </div>
                                    <div x-show="open" x-cloak @click.away="open = false"
                                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1"
                                         class="absolute z-[9999] w-full bg-white border border-gray-200 rounded-xl shadow-xl ring-1 ring-black/5" style="margin-top: 6px;">
                                        <div x-show="selected.length > 0" class="px-2.5 pt-2 pb-1 border-b border-gray-100">
                                            <p class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold mb-1">Selected (<span x-text="selected.length"></span>)</p>
                                        </div>
                                        <div style="max-height: 260px; overflow-y: auto; scrollbar-width: thin;">
                                            <template x-for="item in allItems" :key="item.id">
                                                <label class="flex items-center px-3 py-2 text-sm cursor-pointer transition-colors duration-75 hover:bg-gray-50 text-gray-700"
                                                       :style="selected.includes(item.id) ? 'background-color: #fffbeb; color: #92400e;' : ''" @click.stop>
                                                    <span class="flex items-center justify-center w-4 h-4 rounded border mr-2.5 flex-shrink-0 transition-colors duration-75"
                                                          :style="selected.includes(item.id) ? 'background-color: #d97706; border-color: #d97706;' : 'border-color: #d1d5db; background-color: white;'">
                                                        <svg x-show="selected.includes(item.id)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                    </span>
                                                    <input type="checkbox" :value="item.id" :checked="selected.includes(item.id)" @change="toggleItem(item.id)" class="sr-only" />
                                                    <div class="flex flex-col">
                                                        <span x-text="item.name" class="truncate font-medium"></span>
                                                        <span x-show="item.desc" x-text="item.desc" class="text-[11px] text-gray-400 truncate"></span>
                                                    </div>
                                                </label>
                                            </template>
                                        </div>
                                        <div class="px-3 py-2.5 border-t border-gray-100 flex items-center justify-between bg-gray-50/50 rounded-b-xl">
                                            <button type="button" @click="clearAll()" class="text-xs text-red-500 hover:text-red-700 font-medium transition-colors" x-show="selected.length > 0">Clear All</button>
                                            <span x-show="selected.length === 0" class="text-xs text-gray-400">Select one or more dispositions</span>
                                            <button type="button" @click="open = false" class="text-xs bg-amber-600 text-white px-3 py-1 rounded-md hover:bg-amber-700 font-medium transition-colors shadow-sm">Done</button>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <x-input-label for="limit" value="Max Shipments to Assign" />
                                    <input type="number" name="limit" id="limit" value="100" min="1" max="5000" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>

                                <button type="submit" id="btn-manual-assign" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <span class="btn-text">Assign Shipments</span>
                                    <svg class="btn-spinner hidden animate-spin ml-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Auto-Assignment Rules Panel --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Auto-Assignment Rules</h3>
                        <p class="text-sm text-gray-500 mt-1">Configure rules for automatic shipment distribution</p>
                    </div>
                    <div class="px-6 py-5">

                        @if($rules->isNotEmpty())
                            <div class="space-y-3 mb-6">
                                @foreach($rules as $rule)
                                    <div class="flex items-center justify-between p-3 border rounded-lg {{ $rule->is_active ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }}">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $rule->name }}</p>
                                            <p class="text-xs text-gray-500">
                                                {{ ucfirst(str_replace('_', ' ', $rule->rule_type)) }} |
                                                {{ ucfirst(str_replace('_', ' ', $rule->assignment_method)) }} |
                                                Max {{ $rule->max_attempts }} attempts
                                            </p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <button type="button" onclick="runSingleRule({{ $rule->id }}, this)"
                                                class="text-xs px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 inline-flex items-center">
                                                <span class="btn-text">Run</span>
                                                <svg class="btn-spinner hidden animate-spin ml-1 h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                            </button>
                                            <form method="POST" action="{{ route('telemarketing.toggle-rule', $rule) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-xs px-2 py-1 rounded {{ $rule->is_active ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-green-100 text-green-700 hover:bg-green-200' }}">
                                                    {{ $rule->is_active ? 'Disable' : 'Enable' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('telemarketing.delete-rule', $rule) }}" class="inline" onsubmit="return confirm('Delete this rule?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Add New Rule --}}
                        <div class="border-t pt-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Add New Rule</h4>
                            <form method="POST" action="{{ route('telemarketing.store-rule') }}">
                                @csrf
                                <div class="space-y-3">
                                    <div>
                                        <x-input-label for="rule_name" value="Rule Name *" />
                                        <input type="text" name="name" id="rule_name" required placeholder="e.g., Return Shipments" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <x-input-label for="rule_type" value="Rule Type *" />
                                            <select name="rule_type" id="rule_type" required onchange="toggleRuleFields()" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                <option value="status_based">Status Based</option>
                                                <option value="delivered_age">Delivered Age</option>
                                            </select>
                                        </div>
                                        <div>
                                            <x-input-label for="assignment_method" value="Method *" />
                                            <select name="assignment_method" id="assignment_method" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                <option value="round_robin">Round Robin</option>
                                                <option value="workload_based">Workload Based</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div id="status-field">
                                        <x-input-label for="rule_status_id" value="Target Status" />
                                        <select name="status_id" id="rule_status_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">Any Status</option>
                                            @foreach($statuses as $status)
                                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div id="days-field" class="hidden">
                                        <x-input-label for="days_threshold" value="Days Since Delivery" />
                                        <input type="number" name="days_threshold" id="days_threshold" value="7" min="1" max="365" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <x-input-label for="max_attempts" value="Max Attempts" />
                                            <input type="number" name="max_attempts" id="max_attempts" value="5" min="1" max="50" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        </div>
                                        <div>
                                            <x-input-label for="priority" value="Priority (higher = first)" />
                                            <input type="number" name="priority" id="priority" value="0" min="0" max="100" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        </div>
                                    </div>
                                    <x-primary-button>Add Rule</x-primary-button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- STATUS TRANSITION RULES --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Status Transition Rules</h3>
                    <p class="text-sm text-gray-500 mt-1">Define what happens when a shipment's status changes during import. Controls auto-reassignment, cooldowns, and attempt resets.</p>
                </div>
                <div class="px-6 py-5">

                    {{-- Existing Transition Rules --}}
                    @if($transitionRules->isNotEmpty())
                        <div class="overflow-x-auto mb-6">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">From Status</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">To Status</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Reset Attempts</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Cooldown</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($transitionRules as $tr)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2">
                                                @if($tr->fromStatus)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">{{ $tr->fromStatus->name }}</span>
                                                @else
                                                    <span class="text-gray-400 text-xs">Any</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2">
                                                @if($tr->toStatus)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">{{ $tr->toStatus->name }}</span>
                                                @else
                                                    <span class="text-gray-400 text-xs">Any</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2">
                                                @php
                                                    $actionColors = [
                                                        'auto_reassign' => 'bg-green-100 text-green-700',
                                                        'auto_unassign' => 'bg-yellow-100 text-yellow-700',
                                                        'mark_completed' => 'bg-blue-100 text-blue-700',
                                                        'no_action' => 'bg-gray-100 text-gray-600',
                                                    ];
                                                @endphp
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $actionColors[$tr->action] ?? 'bg-gray-100 text-gray-600' }}">
                                                    {{ ucfirst(str_replace('_', ' ', $tr->action)) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                @if($tr->reset_attempts)
                                                    <span class="text-green-600">✓ Yes</span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                @if($tr->cooldown_days > 0)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">{{ $tr->cooldown_days }} day{{ $tr->cooldown_days > 1 ? 's' : '' }}</span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $tr->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                                    {{ $tr->is_active ? 'Active' : 'Disabled' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <div class="flex items-center justify-center space-x-2">
                                                    <form method="POST" action="{{ route('telemarketing.toggle-transition-rule', $tr) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="text-xs px-2 py-1 rounded {{ $tr->is_active ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-green-100 text-green-700 hover:bg-green-200' }}">
                                                            {{ $tr->is_active ? 'Disable' : 'Enable' }}
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('telemarketing.delete-transition-rule', $tr) }}" class="inline" onsubmit="return confirm('Delete this transition rule?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 mb-4">No transition rules configured yet. Add rules below to control what happens when shipment statuses change.</p>
                    @endif

                    {{-- Add New Transition Rule --}}
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Add Transition Rule</h4>
                        <form method="POST" action="{{ route('telemarketing.store-transition-rule') }}">
                            @csrf
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label for="from_status_id" value="From Status" />
                                        <select name="from_status_id" id="from_status_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">Any Status</option>
                                            @foreach($statuses as $status)
                                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label for="to_status_id" value="To Status" />
                                        <select name="to_status_id" id="to_status_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">Any Status</option>
                                            @foreach($statuses as $status)
                                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <x-input-label for="tr_action" value="Action *" />
                                        <select name="action" id="tr_action" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="auto_reassign">Auto Reassign (to correct agent)</option>
                                            <option value="auto_unassign">Auto Unassign (back to pool)</option>
                                            <option value="mark_completed">Mark Completed (no more calls)</option>
                                            <option value="no_action">No Action (keep as is)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label for="cooldown_days" value="Cooldown (days)" />
                                        <input type="number" name="cooldown_days" id="cooldown_days" value="0" min="0" max="365" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <p class="text-xs text-gray-400 mt-1">0 = no cooldown</p>
                                    </div>
                                    <div>
                                        <x-input-label for="tr_priority" value="Priority" />
                                        <input type="number" name="priority" id="tr_priority" value="0" min="0" max="100" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                </div>

                                <div class="flex items-center space-x-6">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="reset_attempts" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span class="ml-2 text-sm text-gray-700">Reset attempt count to 0</span>
                                    </label>
                                </div>

                                <div>
                                    <x-input-label for="tr_description" value="Description (optional)" />
                                    <input type="text" name="description" id="tr_description" placeholder="e.g., When For Return becomes Returned, reassign to Returned agent" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>

                                <x-primary-button>Add Transition Rule</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                    <p class="text-xs text-gray-500">
                        <strong>How it works:</strong> After each import, the system checks if any shipment's status changed. If a matching transition rule exists, it applies the configured action (reassign, unassign, complete, or no action). Dispositions marked as "final" are always respected — completed shipments won't be re-called. Dispositions marked as "re-callable on status change" allow the shipment to be called again after reassignment.
                    </p>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- TELEMARKETER WORKLOAD SUMMARY --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Telemarketer Workload</h3>
                    <button type="button" id="btn-run-all-rules" onclick="runAllRules(this)"
                            class="inline-flex items-center px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition">
                        <svg class="w-4 h-4 mr-1 btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <svg class="btn-spinner hidden animate-spin mr-1 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span class="btn-text">Run Auto-Assign</span>
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agent</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assigned Statuses</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Pending</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Completed</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($telemarketers as $tm)
                                @php
                                    $agentStatuses = $agentStatusMap[$tm->id] ?? [];
                                    $statusNames = $agentStatuses
                                        ? $statuses->whereIn('id', $agentStatuses)->pluck('name')->toArray()
                                        : [];
                                @endphp
                                <tr class="hover:bg-gray-50 {{ !$tm->is_telemarketing_active ? 'opacity-50' : '' }}">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 {{ $tm->is_telemarketing_active ? 'bg-indigo-100' : 'bg-gray-200' }} rounded-full flex items-center justify-center">
                                                <span class="text-xs font-bold {{ $tm->is_telemarketing_active ? 'text-indigo-600' : 'text-gray-400' }}">{{ strtoupper(substr($tm->name, 0, 2)) }}</span>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900">{{ $tm->name }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $tm->is_telemarketing_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $tm->is_telemarketing_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if(empty($statusNames))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">All Statuses</span>
                                        @else
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($statusNames as $sn)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">{{ $sn }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ $tm->pending_count }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ $tm->completed_count }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center space-x-2">
                                            <a href="{{ route('telemarketing.queue', ['telemarketer_id' => $tm->id]) }}" class="text-indigo-600 hover:text-indigo-900 text-xs">Queue</a>
                                            @if(!$tm->is_telemarketing_active && $tm->pending_count > 0)
                                                <button type="button" onclick="redistributeAgent({{ $tm->id }}, '{{ addslashes($tm->name) }}', this)"
                                                        class="text-amber-600 hover:text-amber-900 text-xs inline-flex items-center">
                                                    <span class="btn-text">Redistribute</span>
                                                    <svg class="btn-spinner hidden animate-spin ml-1 h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                </button>
                                            @endif
                                            <button type="button" onclick="unassignAll({{ $tm->id }}, '{{ addslashes($tm->name) }}', this)"
                                                    class="text-red-600 hover:text-red-900 text-xs inline-flex items-center">
                                                <span class="btn-text">Unassign</span>
                                                <svg class="btn-spinner hidden animate-spin ml-1 h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // ── Multi-select dropdown for manual assignment filters ──
        function assignMultiSelect(name, items, initialSelected) {
            // Detect if items are objects (disposition) or strings (shop/item)
            const isObjectMode = items.length > 0 && typeof items[0] === 'object';
            return {
                name: name,
                items: isObjectMode ? [] : items,
                allItems: isObjectMode ? items : [],
                selected: initialSelected || [],
                searchText: '',
                open: false,
                get filteredItems() {
                    if (isObjectMode) {
                        // Object mode not used for filteredItems — uses allItems directly
                        return [];
                    }
                    if (!this.searchText) return this.items;
                    const search = this.searchText.toLowerCase();
                    return this.items.filter(item => item.toLowerCase().includes(search));
                },
                getDisplayName(id) {
                    if (!isObjectMode) return id;
                    const found = this.allItems.find(i => i.id === id || String(i.id) === String(id));
                    return found ? found.name : id;
                },
                toggleItem(item) {
                    const idx = this.selected.indexOf(item);
                    if (idx > -1) {
                        this.selected.splice(idx, 1);
                    } else {
                        this.selected.push(item);
                    }
                },
                clearAll() {
                    this.selected = [];
                }
            };
        }

        // ── Status name map for strict validation ──
        const statusNameMap = {
            @foreach($statuses as $status)
                {{ $status->id }}: '{{ $status->name }}',
            @endforeach
        };

        // ── Helper: show toast notification ──
        function showToast(message, type = 'success') {
            const container = document.getElementById('flash-container');
            const colors = {
                success: 'bg-green-50 border-green-200 text-green-700',
                info: 'bg-blue-50 border-blue-200 text-blue-700',
                error: 'bg-red-50 border-red-200 text-red-700',
            };
            const div = document.createElement('div');
            div.className = `mb-4 border px-4 py-3 rounded-lg ${colors[type] || colors.info} transition-opacity duration-500`;
            div.innerHTML = message;
            container.prepend(div);
            setTimeout(() => {
                div.style.opacity = '0';
                setTimeout(() => div.remove(), 500);
            }, 6000);
        }

        // ── Helper: toggle button loading state ──
        function setLoading(btn, loading) {
            const text = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.btn-spinner');
            const icon = btn.querySelector('.btn-icon');
            if (loading) {
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
                if (text) text.textContent = 'Processing...';
                if (spinner) spinner.classList.remove('hidden');
                if (icon) icon.classList.add('hidden');
            } else {
                btn.disabled = false;
                btn.classList.remove('opacity-75', 'cursor-not-allowed');
                if (spinner) spinner.classList.add('hidden');
                if (icon) icon.classList.remove('hidden');
            }
        }

        // ═══════════════════════════════════════════════════════
        //  AGENT ACTIVE/INACTIVE TOGGLE
        // ═══════════════════════════════════════════════════════

        async function toggleAgentActive(agentId, agentName, btn) {
            const currentActive = btn.dataset.active === '1';
            const newState = !currentActive;
            const confirmMsg = newState
                ? `Activate ${agentName} for telemarketing? They will start receiving assignments.`
                : `Deactivate ${agentName}? They won't receive new assignments. You can redistribute their pending shipments afterward.`;

            if (!confirm(confirmMsg)) return;

            try {
                const res = await fetch('{{ route("telemarketing.toggle-agent-active") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ telemarketer_id: agentId }),
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    // Update toggle UI
                    btn.dataset.active = data.is_active ? '1' : '0';
                    const knob = btn.querySelector('span');
                    const label = document.getElementById(`active-label-${agentId}`);
                    const row = document.getElementById(`agent-row-${agentId}`);

                    if (data.is_active) {
                        btn.classList.remove('bg-gray-300');
                        btn.classList.add('bg-green-500');
                        knob.classList.remove('translate-x-0');
                        knob.classList.add('translate-x-5');
                        if (label) { label.textContent = 'Active'; label.classList.remove('text-gray-400'); label.classList.add('text-green-600'); }
                        if (row) row.classList.remove('opacity-50');
                    } else {
                        btn.classList.remove('bg-green-500');
                        btn.classList.add('bg-gray-300');
                        knob.classList.remove('translate-x-5');
                        knob.classList.add('translate-x-0');
                        if (label) { label.textContent = 'Inactive'; label.classList.remove('text-green-600'); label.classList.add('text-gray-400'); }
                        if (row) row.classList.add('opacity-50');
                    }

                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast(data.message || 'Failed to toggle.', 'error');
                }
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }
        }

        // ═══════════════════════════════════════════════════════
        //  REDISTRIBUTE AGENT
        // ═══════════════════════════════════════════════════════

        async function redistributeAgent(agentId, agentName, btn) {
            if (!confirm(`Redistribute all pending shipments from ${agentName} to other active agents?`)) return;
            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Redistributing...';

            try {
                const res = await fetch('{{ route("telemarketing.redistribute-agent") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ telemarketer_id: agentId }),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'info');
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Redistribute';
            setTimeout(() => location.reload(), 2000);
        }

        // ═══════════════════════════════════════════════════════
        //  AGENT STATUS ASSIGNMENTS
        // ═══════════════════════════════════════════════════════

        const dirtyAgents = new Set();

        function markAgentDirty(agentId) {
            dirtyAgents.add(agentId);
            const saveBtn = document.getElementById(`save-agent-${agentId}`);
            const savedLabel = document.getElementById(`saved-agent-${agentId}`);
            if (saveBtn) {
                saveBtn.classList.remove('hidden');
                saveBtn.classList.add('inline-flex');
            }
            if (savedLabel) savedLabel.classList.add('hidden');
        }

        async function saveAgentStatuses(agentId, btn) {
            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Saving...';

            const checkboxes = document.querySelectorAll(`.agent-status-cb[data-agent-id="${agentId}"]`);
            const statusIds = [];
            checkboxes.forEach(cb => {
                if (cb.checked) statusIds.push(parseInt(cb.dataset.statusId));
            });

            try {
                const res = await fetch('{{ route("telemarketing.sync-agent-statuses") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ telemarketer_id: agentId, status_ids: statusIds }),
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    dirtyAgents.delete(agentId);
                    const savedLabel = document.getElementById(`saved-agent-${agentId}`);
                    if (savedLabel) {
                        savedLabel.textContent = statusIds.length > 0
                            ? `✓ ${statusIds.length} status${statusIds.length > 1 ? 'es' : ''}`
                            : 'All';
                        savedLabel.classList.remove('hidden');
                    }
                    btn.classList.add('hidden');
                    btn.classList.remove('inline-flex');
                } else {
                    showToast(data.message || 'Failed to save.', 'error');
                }
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Save';
        }

        // ═══════════════════════════════════════════════════════
        //  STRICT MANUAL ASSIGN VALIDATION (Client-side)
        // ═══════════════════════════════════════════════════════

        function checkStrictAssign() {
            const tmSelect = document.getElementById('telemarketer_id');
            const statusSelect = document.getElementById('status_id');
            const warning = document.getElementById('strict-assign-warning');
            const warningMsg = document.getElementById('strict-assign-msg');

            if (!tmSelect.value) { warning.classList.add('hidden'); return; }

            const selectedOption = tmSelect.options[tmSelect.selectedIndex];
            const agentStatuses = JSON.parse(selectedOption.dataset.statuses || '[]');
            const isActive = selectedOption.dataset.active === '1';

            // Check inactive
            if (!isActive) {
                warning.classList.remove('hidden');
                warningMsg.textContent = 'This agent is INACTIVE. They cannot receive new assignments.';
                return;
            }

            // Check status mismatch (only if agent has specific statuses assigned AND a status filter is selected)
            if (agentStatuses.length > 0 && statusSelect.value) {
                const selectedStatusId = parseInt(statusSelect.value);
                if (!agentStatuses.includes(selectedStatusId)) {
                    const statusName = statusNameMap[selectedStatusId] || 'Unknown';
                    const allowedNames = agentStatuses.map(id => statusNameMap[id] || 'Unknown').join(', ');
                    warning.classList.remove('hidden');
                    warningMsg.textContent = `This agent only handles [${allowedNames}]. You selected "${statusName}" which is not in their assigned statuses. This assignment will be blocked.`;
                    return;
                }
            }

            warning.classList.add('hidden');
        }

        // Attach change listeners for strict validation
        document.getElementById('telemarketer_id').addEventListener('change', checkStrictAssign);
        document.getElementById('status_id').addEventListener('change', checkStrictAssign);

        // ═══════════════════════════════════════════════════════
        //  AJAX FUNCTIONS
        // ═══════════════════════════════════════════════════════

        async function runAllRules(btn) {
            if (!confirm('Run auto-assignment for all active rules?')) return;
            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Running...';

            try {
                const res = await fetch('{{ route("telemarketing.run-auto-assign") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({}),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'info');
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Run Auto-Assign';
            setTimeout(() => location.reload(), 3000);
        }

        async function runSingleRule(ruleId, btn) {
            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Running...';

            try {
                const res = await fetch('{{ route("telemarketing.run-auto-assign") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ rule_id: ruleId }),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'info');
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Run';
            setTimeout(() => location.reload(), 3000);
        }

        async function handleManualAssign(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-manual-assign');
            const form = document.getElementById('manual-assign-form');

            const telemarketer = form.querySelector('[name=telemarketer_id]').value;
            if (!telemarketer) {
                showToast('Please select a telemarketer.', 'error');
                return false;
            }

            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Assigning...';

            try {
                const formData = new FormData(form);
                const body = {};
                formData.forEach((v, k) => { if (v) body[k] = v; });

                // Collect multi-select values from Alpine components
                const senderNameEl = document.querySelector('[x-data*="sender_name"]');
                const itemDescEl = document.querySelector('[x-data*="item_description"]');
                if (senderNameEl && senderNameEl.__x) {
                    const senderSelected = senderNameEl.__x.$data.selected;
                    if (senderSelected && senderSelected.length > 0) body.sender_name = senderSelected;
                }
                if (itemDescEl && itemDescEl.__x) {
                    const itemSelected = itemDescEl.__x.$data.selected;
                    if (itemSelected && itemSelected.length > 0) body.item_description = itemSelected;
                }
                // Collect disposition multi-select values
                const dispositionEl = document.querySelector('[x-data*="disposition"]');
                if (dispositionEl && dispositionEl.__x) {
                    const dispSelected = dispositionEl.__x.$data.selected;
                    if (dispSelected && dispSelected.length > 0) body.disposition = dispSelected;
                }

                // Also try Alpine.$data for newer Alpine versions
                if (!body.sender_name && senderNameEl) {
                    try {
                        const sd = Alpine.$data(senderNameEl);
                        if (sd && sd.selected && sd.selected.length > 0) body.sender_name = sd.selected;
                    } catch(e) {}
                }
                if (!body.item_description && itemDescEl) {
                    try {
                        const id = Alpine.$data(itemDescEl);
                        if (id && id.selected && id.selected.length > 0) body.item_description = id.selected;
                    } catch(e) {}
                }
                if (!body.disposition && dispositionEl) {
                    try {
                        const dd = Alpine.$data(dispositionEl);
                        if (dd && dd.selected && dd.selected.length > 0) body.disposition = dd.selected;
                    } catch(e) {}
                }

                const res = await fetch('{{ route("telemarketing.manual-assign") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : (res.status === 422 ? 'error' : 'info'));
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Assign Shipments';
            setTimeout(() => location.reload(), 3000);
            return false;
        }

        async function unassignAll(telemarketerId, name, btn) {
            if (!confirm(`Unassign all pending shipments from ${name}?`)) return;
            setLoading(btn, true);
            btn.querySelector('.btn-text').textContent = 'Unassigning...';

            try {
                const res = await fetch('{{ route("telemarketing.unassign-all") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ telemarketer_id: telemarketerId }),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'info');
            } catch (e) {
                showToast('An error occurred. Please try again.', 'error');
            }

            setLoading(btn, false);
            btn.querySelector('.btn-text').textContent = 'Unassign';
            setTimeout(() => location.reload(), 3000);
        }

        function toggleRuleFields() {
            const ruleType = document.getElementById('rule_type').value;
            document.getElementById('status-field').classList.toggle('hidden', ruleType === 'delivered_age');
            document.getElementById('days-field').classList.toggle('hidden', ruleType !== 'delivered_age');
        }
    </script>
    @endpush
</x-app-layout>
