<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Remittance Report</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Date Range Filter --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('remittance.index') }}" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                        <input type="text" id="dateRange" name="date_range"
                               class="border-gray-300 rounded-md shadow-sm text-sm w-72"
                               placeholder="Select date range..." readonly>
                        <input type="hidden" name="start_date" id="startDate" value="{{ $startDate->format('Y-m-d') }}">
                        <input type="hidden" name="end_date" id="endDate" value="{{ $endDate->format('Y-m-d') }}">
                    </div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                        Apply Filter
                    </button>
                </form>

                <div class="mt-3 text-sm text-gray-500">
                    Showing data from <strong>{{ $startDate->format('M d, Y') }}</strong> to <strong>{{ $endDate->format('M d, Y') }}</strong>
                    &mdash; COD Fee Rate: <strong>{{ number_format($codFeeRate * 100, 2) }}%</strong>,
                    VAT Rate: <strong>{{ number_format($codVatRate * 100, 2) }}%</strong>
                    <a href="{{ route('settings.edit') }}" class="text-indigo-600 hover:underline ml-2">[Change]</a>
                </div>
            </div>

            {{-- Grand Totals --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Summary</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <p class="text-sm text-blue-600 font-medium">Total Parcels</p>
                        <p class="text-2xl font-bold text-blue-800">{{ number_format($grandTotals->total_parcels) }}</p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4">
                        <p class="text-sm text-green-600 font-medium">Total COD</p>
                        <p class="text-2xl font-bold text-green-800">&#8369;{{ number_format($grandTotals->total_cod, 2) }}</p>
                    </div>
                    <div class="bg-red-50 rounded-lg p-4">
                        <p class="text-sm text-red-600 font-medium">Total Deductions</p>
                        <p class="text-2xl font-bold text-red-800">&#8369;{{ number_format($grandTotals->total_deductions, 2) }}</p>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4">
                        <p class="text-sm text-purple-600 font-medium">Net Remittance</p>
                        <p class="text-2xl font-bold text-purple-800">&#8369;{{ number_format($grandTotals->net_remittance, 2) }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-500">Shipping Fee (SF)</p>
                        <p class="text-lg font-semibold">&#8369;{{ number_format($grandTotals->total_sf, 2) }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-500">Valuation Fee</p>
                        <p class="text-lg font-semibold">&#8369;{{ number_format($grandTotals->total_valuation_fee, 2) }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-500">COD Fee ({{ number_format($codFeeRate * 100, 2) }}%)</p>
                        <p class="text-lg font-semibold">&#8369;{{ number_format($grandTotals->cod_fee, 2) }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-500">COD VAT ({{ number_format($codVatRate * 100, 2) }}%)</p>
                        <p class="text-lg font-semibold">&#8369;{{ number_format($grandTotals->cod_vat, 2) }}</p>
                    </div>
                </div>
            </div>

            {{-- Daily Breakdown by Signing Time (Delivery Date) --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">By Delivery Date (Signing Time)</h3>

                @if($dailyData->isEmpty())
                    <p class="text-gray-500 text-sm">No delivered shipments found in this date range.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Courier</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">Parcels</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">COD</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">SF</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">Val. Fee</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">COD Fee</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">VAT</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">Deductions</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">Net Remittance</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($dailyData as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap font-medium">{{ \Carbon\Carbon::parse($row->delivery_date)->format('M d, Y') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $row->courier === 'jnt' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ strtoupper($row->courier) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">{{ number_format($row->total_parcels) }}</td>
                                        <td class="px-4 py-3 text-right">&#8369;{{ number_format($row->total_cod, 2) }}</td>
                                        <td class="px-4 py-3 text-right">&#8369;{{ number_format($row->total_sf, 2) }}</td>
                                        <td class="px-4 py-3 text-right">&#8369;{{ number_format($row->total_valuation_fee, 2) }}</td>
                                        <td class="px-4 py-3 text-right">&#8369;{{ number_format($row->cod_fee, 2) }}</td>
                                        <td class="px-4 py-3 text-right">&#8369;{{ number_format($row->cod_vat, 2) }}</td>
                                        <td class="px-4 py-3 text-right text-red-600 font-medium">&#8369;{{ number_format($row->total_deductions, 2) }}</td>
                                        <td class="px-4 py-3 text-right font-bold {{ $row->net_remittance >= 0 ? 'text-green-600' : 'text-red-600' }}">&#8369;{{ number_format($row->net_remittance, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Daily Breakdown by Submission Time --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">By Submission Date</h3>

                @if($submissionData->isEmpty())
                    <p class="text-gray-500 text-sm">No shipments with submission time found in this date range.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Courier</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">Parcels</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">COD</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">SF</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">Val. Fee</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">COD Fee</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">VAT</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">Deductions</th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-500">Net Remittance</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($submissionData as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap font-medium">{{ \Carbon\Carbon::parse($row->submission_date)->format('M d, Y') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $row->courier === 'jnt' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ strtoupper($row->courier) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">{{ number_format($row->total_parcels) }}</td>
                                        <td class="px-4 py-3 text-right">&#8369;{{ number_format($row->total_cod, 2) }}</td>
                                        <td class="px-4 py-3 text-right">&#8369;{{ number_format($row->total_sf, 2) }}</td>
                                        <td class="px-4 py-3 text-right">&#8369;{{ number_format($row->total_valuation_fee, 2) }}</td>
                                        <td class="px-4 py-3 text-right">&#8369;{{ number_format($row->cod_fee, 2) }}</td>
                                        <td class="px-4 py-3 text-right">&#8369;{{ number_format($row->cod_vat, 2) }}</td>
                                        <td class="px-4 py-3 text-right text-red-600 font-medium">&#8369;{{ number_format($row->total_deductions, 2) }}</td>
                                        <td class="px-4 py-3 text-right font-bold {{ $row->net_remittance >= 0 ? 'text-green-600' : 'text-red-600' }}">&#8369;{{ number_format($row->net_remittance, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
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
                defaultDate: ['{{ $startDate->format("Y-m-d") }}', '{{ $endDate->format("Y-m-d") }}'],
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
