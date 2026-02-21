<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Remittance Report</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Date Range Filter --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('remittance.index') }}" id="filterForm" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                        <input type="text" id="dateRange" name="date_range"
                               class="border-gray-300 rounded-md shadow-sm text-sm w-72"
                               placeholder="Select date range..." readonly>
                        <input type="hidden" name="start_date" id="startDate" value="{{ $periodStart->format('Y-m-d') }}">
                        <input type="hidden" name="end_date" id="endDate" value="{{ $periodEnd->format('Y-m-d') }}">
                    </div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                        Apply Filter
                    </button>
                    <a href="{{ route('remittance.index') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-200 border border-gray-300">
                        This Month
                    </a>
                    <a href="{{ route('remittance.index', ['preset' => 'last_month']) }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-200 border border-gray-300">
                        Last Month
                    </a>
                </form>

                <div class="mt-3 text-sm text-gray-500">
                    Period: <strong>{{ $periodStart->format('M d, Y') }}</strong> to <strong>{{ $periodEnd->format('M d, Y') }}</strong>
                    &mdash; COD Fee Rate: <strong>{{ number_format($codFeeRate * 100, 2) }}%</strong>,
                    VAT Rate: <strong>{{ number_format($codVatRate * 100, 2) }}%</strong>
                    @can('settings.manage')
                        <a href="{{ route('settings.edit') }}" class="text-indigo-600 hover:underline ml-2">[Change]</a>
                    @endcan
                </div>
            </div>

            {{-- Remittance Summary --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Remittance Summary</h3>

                {{-- Main computation display --}}
                <div class="bg-gray-50 rounded-lg p-6 mb-4">
                    <table class="w-full text-sm">
                        <tbody>
                            <tr>
                                <td class="py-2 font-medium text-gray-700">COD Collected (Delivered, by Signing Time)</td>
                                <td class="py-2 text-right text-lg font-bold text-green-700">&#8369;{{ number_format($grandTotals->total_cod, 2) }}</td>
                            </tr>
                            <tr class="border-t">
                                <td class="py-2 text-gray-600 pl-4">− Shipping Fee (by Submission Time)</td>
                                <td class="py-2 text-right text-red-600">&#8369;{{ number_format($grandTotals->total_sf, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="py-2 text-gray-600 pl-4">− Valuation Fee (by Submission Time)</td>
                                <td class="py-2 text-right text-red-600">&#8369;{{ number_format($grandTotals->total_valuation_fee, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="py-2 text-gray-600 pl-4">− COD Fee ({{ number_format($codFeeRate * 100, 2) }}% of COD)</td>
                                <td class="py-2 text-right text-red-600">&#8369;{{ number_format($grandTotals->cod_fee, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="py-2 text-gray-600 pl-4">− COD Fee VAT ({{ number_format($codVatRate * 100, 2) }}% of COD Fee)</td>
                                <td class="py-2 text-right text-red-600">&#8369;{{ number_format($grandTotals->cod_fee_vat, 2) }}</td>
                            </tr>
                            <tr class="border-t">
                                <td class="py-2 font-medium text-gray-700">Total Deductions</td>
                                <td class="py-2 text-right text-lg font-bold text-red-700">&#8369;{{ number_format($grandTotals->total_deductions, 2) }}</td>
                            </tr>
                            <tr class="border-t-2 border-gray-400">
                                <td class="py-3 font-bold text-gray-900 text-base">NET REMITTANCE</td>
                                <td class="py-3 text-right text-2xl font-bold {{ $grandTotals->net_remittance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    &#8369;{{ number_format($grandTotals->net_remittance, 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Quick stats --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <p class="text-sm text-blue-600 font-medium">Delivered Parcels</p>
                        <p class="text-2xl font-bold text-blue-800">{{ number_format($grandTotals->total_delivered) }}</p>
                        <p class="text-xs text-blue-500">by Signing Time</p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4">
                        <p class="text-sm text-green-600 font-medium">Total COD</p>
                        <p class="text-2xl font-bold text-green-800">&#8369;{{ number_format($grandTotals->total_cod, 2) }}</p>
                        <p class="text-xs text-green-500">by Signing Time</p>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-4">
                        <p class="text-sm text-orange-600 font-medium">Total SF</p>
                        <p class="text-2xl font-bold text-orange-800">&#8369;{{ number_format($grandTotals->total_sf, 2) }}</p>
                        <p class="text-xs text-orange-500">by Submission Time</p>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4">
                        <p class="text-sm text-purple-600 font-medium">Net Remittance</p>
                        <p class="text-2xl font-bold {{ $grandTotals->net_remittance >= 0 ? 'text-purple-800' : 'text-red-800' }}">&#8369;{{ number_format($grandTotals->net_remittance, 2) }}</p>
                    </div>
                </div>
            </div>

            {{-- COD by Signing Time (Delivery Date) --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">COD by Delivery Date (Signing Time)</h3>
                <p class="text-xs text-gray-500 mb-3">Period: {{ $periodStart->format('M d, Y') }} — {{ $periodEnd->format('M d, Y') }}</p>

                @if($codBySigningDate->isEmpty())
                    <p class="text-gray-500 text-sm">No delivered shipments found in this period.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Courier</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">Parcels</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">COD</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($codBySigningDate as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap font-medium">{{ \Carbon\Carbon::parse($row->the_date)->format('M d, Y (D)') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $row->courier === 'jnt' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ strtoupper($row->courier) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">{{ number_format($row->total_parcels) }}</td>
                                        <td class="px-4 py-3 text-right font-medium text-green-700">&#8369;{{ number_format($row->total_cod, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-100">
                                <tr class="font-bold">
                                    <td class="px-4 py-3" colspan="2">Total</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($codBySigningDate->sum('total_parcels')) }}</td>
                                    <td class="px-4 py-3 text-right text-green-700">&#8369;{{ number_format($codBySigningDate->sum('total_cod'), 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Rows by Submission Time --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Shipments by Submission Date</h3>
                <p class="text-xs text-gray-500 mb-3">
                    Showing: {{ $rowRangeStart->format('M d, Y') }} — {{ $rowRangeEnd->format('M d, Y') }}
                    <span class="text-gray-400">(selected start − 30 days to selected end)</span>
                </p>

                @if($submissionRows->isEmpty())
                    <p class="text-gray-500 text-sm">No shipments with submission time found in this range.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Submission Date</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Courier</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">Parcels</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">COD</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">Shipping Fee</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">Valuation Fee</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($submissionRows as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap font-medium">{{ \Carbon\Carbon::parse($row->the_date)->format('M d, Y (D)') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $row->courier === 'jnt' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ strtoupper($row->courier) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">{{ number_format($row->total_parcels) }}</td>
                                        <td class="px-4 py-3 text-right">&#8369;{{ number_format($row->total_cod, 2) }}</td>
                                        <td class="px-4 py-3 text-right">&#8369;{{ number_format($row->total_sf, 2) }}</td>
                                        <td class="px-4 py-3 text-right">&#8369;{{ number_format($row->total_valuation_fee, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-100">
                                <tr class="font-bold">
                                    <td class="px-4 py-3" colspan="2">Total</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($submissionRows->sum('total_parcels')) }}</td>
                                    <td class="px-4 py-3 text-right">&#8369;{{ number_format($submissionRows->sum('total_cod'), 2) }}</td>
                                    <td class="px-4 py-3 text-right">&#8369;{{ number_format($submissionRows->sum('total_sf'), 2) }}</td>
                                    <td class="px-4 py-3 text-right">&#8369;{{ number_format($submissionRows->sum('total_valuation_fee'), 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>

    @push('scripts')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr('#dateRange', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                defaultDate: ['{{ $periodStart->format("Y-m-d") }}', '{{ $periodEnd->format("Y-m-d") }}'],
                maxDate: 'today',
                onChange: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        const fmt = d => d.toISOString().split('T')[0];
                        document.getElementById('startDate').value = fmt(selectedDates[0]);
                        document.getElementById('endDate').value = fmt(selectedDates[1]);
                    }
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
