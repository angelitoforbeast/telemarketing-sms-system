<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Remittance</h2>
    </x-slot>

    <style>
        input.no-spin::-webkit-outer-spin-button,
        input.no-spin::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input.no-spin[type=number] { -moz-appearance: textfield; appearance: textfield; }
    </style>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4"
             x-data="remitUI('{{ $periodStart->format('Y-m-d') }}','{{ $periodEnd->format('Y-m-d') }}')" x-init="init()">

            {{-- Filters --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                <div class="grid md:grid-cols-3 gap-3 items-end">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold mb-1">Date Range</label>
                        <input id="remitRange" type="text" placeholder="Select date range"
                               class="w-full border border-gray-300 p-2 rounded-md shadow-sm cursor-pointer bg-white text-sm" readonly>
                        <div class="text-xs text-gray-500 mt-1" x-text="dateLabel"></div>
                    </div>
                    <div class="flex gap-2 md:justify-end">
                        <button class="px-3 py-2 rounded border text-sm hover:bg-gray-50" @click="thisMonth()">This Month</button>
                        <button class="px-3 py-2 rounded border text-sm hover:bg-gray-50" @click="lastMonth()">Last Month</button>
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-500">
                    COD Fee Rate: <strong>{{ number_format($codFeeRate * 100, 2) }}%</strong> &middot;
                    COD Fee VAT Rate: <strong>{{ number_format($codVatRate * 100, 2) }}%</strong>
                    @can('settings.manage')
                        <a href="{{ route('settings.edit') }}" class="text-indigo-600 hover:underline ml-1">[Change]</a>
                    @endcan
                </div>
            </div>

            {{-- Summary Table --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-semibold text-gray-800">Summary</div>
                    <div class="text-xs text-gray-500">
                        Data pool: <strong>{{ $poolStart->format('M d, Y') }}</strong> to <strong>{{ $poolEnd->format('M d, Y') }}</strong>
                        (selected start &minus; 30 days) &middot; by <em>submission_time</em>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full border border-gray-200 bg-white text-xs">
                        <thead class="bg-gray-50">
                            <tr class="text-left">
                                <th class="px-3 py-2 border-b">Date</th>
                                <th class="px-3 py-2 border-b text-right">Delivered</th>
                                <th class="px-3 py-2 border-b text-right">COD Sum</th>
                                <th class="px-3 py-2 border-b text-right">COD Fee<br><span class="text-[10px] font-normal">({{ number_format($codFeeRate * 100, 2) }}%)</span></th>
                                <th class="px-3 py-2 border-b text-right">COD Fee VAT<br><span class="text-[10px] font-normal">({{ number_format($codVatRate * 100, 2) }}% of Fee)</span></th>
                                <th class="px-3 py-2 border-b text-right">Parcels Picked Up</th>
                                <th class="px-3 py-2 border-b text-right">Total Shipping Cost</th>
                                <th class="px-3 py-2 border-b text-right">Remittance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 border-b whitespace-nowrap">{{ \Carbon\Carbon::parse($r['date'])->format('M d, Y (D)') }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ number_format($r['delivered']) }}</td>
                                    <td class="px-3 py-2 border-b text-right">&#8369;{{ number_format($r['cod_sum'], 2) }}</td>
                                    <td class="px-3 py-2 border-b text-right">&#8369;{{ number_format($r['cod_fee'], 2) }}</td>
                                    <td class="px-3 py-2 border-b text-right">&#8369;{{ number_format($r['cod_fee_vat'], 2) }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ number_format($r['picked']) }}</td>
                                    <td class="px-3 py-2 border-b text-right">&#8369;{{ number_format($r['ship_cost'], 2) }}</td>
                                    <td class="px-3 py-2 border-b text-right font-semibold">&#8369;{{ number_format($r['remittance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-3 py-6 text-center text-gray-500" colspan="8">No data for the selected date(s).</td>
                                </tr>
                            @endforelse
                        </tbody>

                        {{-- TOTALS with editable COD Fee & COD Fee VAT --}}
                        <tfoot class="bg-gray-50"
                               x-data="codFeeTotals({
                                   codSum: {{ json_encode($totals['cod_sum']) }},
                                   codFee: {{ json_encode($totals['cod_fee']) }},
                                   codFeeVat: {{ json_encode($totals['cod_fee_vat']) }},
                                   shipCost: {{ json_encode($totals['ship_cost']) }}
                               })"
                               x-init="init()">
                            <tr>
                                <th class="px-3 py-2 border-t text-right">TOTAL</th>
                                <th class="px-3 py-2 border-t text-right">{{ number_format($totals['delivered']) }}</th>
                                <th class="px-3 py-2 border-t text-right">&#8369;{{ number_format($totals['cod_sum'], 2) }}</th>

                                {{-- Editable TOTAL COD Fee --}}
                                <th class="px-3 py-2 border-t text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <input type="text" inputmode="decimal"
                                               class="no-spin w-28 border rounded px-2 py-1 text-right text-xs"
                                               x-model="codFeeInput"
                                               @blur="formatFee()"
                                               @keydown.enter.prevent="formatFee()">
                                        <button type="button" class="text-[10px] px-1.5 py-0.5 border rounded hover:bg-gray-100" @click="resetFee()">Reset</button>
                                    </div>
                                    <div class="text-[10px] text-gray-500 mt-0.5" x-show="isFeeOverridden()">
                                        overridden (was <span x-text="money(codFeeDefault)"></span>)
                                    </div>
                                </th>

                                {{-- Editable TOTAL COD Fee VAT --}}
                                <th class="px-3 py-2 border-t text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <input type="text" inputmode="decimal"
                                               class="no-spin w-28 border rounded px-2 py-1 text-right text-xs"
                                               x-model="codFeeVatInput"
                                               @blur="formatVat()"
                                               @keydown.enter.prevent="formatVat()">
                                        <button type="button" class="text-[10px] px-1.5 py-0.5 border rounded hover:bg-gray-100" @click="resetVat()">Reset</button>
                                    </div>
                                    <div class="text-[10px] text-gray-500 mt-0.5" x-show="isVatOverridden()">
                                        overridden (was <span x-text="money(codFeeVatDefault)"></span>)
                                    </div>
                                </th>

                                <th class="px-3 py-2 border-t text-right">{{ number_format($totals['picked']) }}</th>
                                <th class="px-3 py-2 border-t text-right">&#8369;{{ number_format($totals['ship_cost'], 2) }}</th>

                                {{-- TOTAL Remittance reacts to overrides --}}
                                <th class="px-3 py-2 border-t text-right font-semibold" x-text="money(remittanceEffective)"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="text-[11px] text-gray-500 mt-3">
                    <span class="font-semibold">Formulas:</span>
                    COD Fee = <code>{{ number_format($codFeeRate * 100, 2) }}% &times; COD Sum</code> &middot;
                    COD Fee VAT = <code>{{ number_format($codVatRate * 100, 2) }}% &times; COD Fee</code> &middot;
                    Remittance = <code>COD Sum &minus; COD Fee &minus; COD Fee VAT &minus; Shipping Cost</code>
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        function remitUI(startDefault, endDefault) {
            return {
                filters: { start_date: startDefault || '', end_date: endDefault || '' },
                dateLabel: 'Select dates',

                ymd(d) {
                    const p = n => String(n).padStart(2, '0');
                    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
                },
                setDateLabel() {
                    if (!this.filters.start_date || !this.filters.end_date) { this.dateLabel = 'Select dates'; return; }
                    const s = new Date(this.filters.start_date + 'T00:00:00');
                    const e = new Date(this.filters.end_date + 'T00:00:00');
                    const M = i => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][i];
                    const same = s.getTime() === e.getTime();
                    this.dateLabel = same
                        ? `${M(s.getMonth())} ${s.getDate()}, ${s.getFullYear()}`
                        : `${M(s.getMonth())} ${s.getDate()}, ${s.getFullYear()} – ${M(e.getMonth())} ${e.getDate()}, ${e.getFullYear()}`;
                },
                go() {
                    const params = new URLSearchParams({
                        start_date: this.filters.start_date || '',
                        end_date: this.filters.end_date || ''
                    });
                    window.location = '{{ route("remittance.index") }}?' + params.toString();
                },
                thisMonth() {
                    const now = new Date();
                    const start = new Date(now.getFullYear(), now.getMonth(), 1);
                    this.filters.start_date = this.ymd(start);
                    this.filters.end_date = this.ymd(now);
                    this.setDateLabel(); this.go();
                },
                lastMonth() {
                    const now = new Date();
                    const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    const end = new Date(now.getFullYear(), now.getMonth(), 0);
                    this.filters.start_date = this.ymd(start);
                    this.filters.end_date = this.ymd(end);
                    this.setDateLabel(); this.go();
                },
                init() {
                    this.setDateLabel();
                    window.flatpickr('#remitRange', {
                        mode: 'range',
                        dateFormat: 'Y-m-d',
                        defaultDate: [this.filters.start_date, this.filters.end_date].filter(Boolean),
                        onClose: (sel) => {
                            if (sel.length === 2) {
                                this.filters.start_date = this.ymd(sel[0]);
                                this.filters.end_date = this.ymd(sel[1]);
                            } else if (sel.length === 1) {
                                this.filters.start_date = this.ymd(sel[0]);
                                this.filters.end_date = this.ymd(sel[0]);
                            } else { return; }
                            this.setDateLabel(); this.go();
                        },
                        onReady: (_sd, _ds, inst) => {
                            if (this.filters.start_date && this.filters.end_date) {
                                inst.input.value = `${this.filters.start_date} to ${this.filters.end_date}`;
                            }
                        }
                    });
                }
            }
        }

        function codFeeTotals(init) {
            return {
                codSum: Number(init.codSum || 0),
                codFeeDefault: Number(init.codFee || 0),
                codFeeVatDefault: Number(init.codFeeVat || 0),
                shipCost: Number(init.shipCost || 0),

                codFeeOverride: null,
                codFeeVatOverride: null,
                codFeeInput: '',
                codFeeVatInput: '',

                init() {
                    this.codFeeInput = this.toFixed2(this.codFeeDefault);
                    this.codFeeVatInput = this.toFixed2(this.codFeeVatDefault);
                },

                get codFeeEffective() { return this.codFeeOverride ?? this.codFeeDefault; },
                get codFeeVatEffective() { return this.codFeeVatOverride ?? this.codFeeVatDefault; },
                get remittanceEffective() {
                    return +(this.codSum - this.codFeeEffective - this.codFeeVatEffective - this.shipCost).toFixed(2);
                },

                toFixed2(v) { return (Number(v || 0)).toFixed(2); },
                money(v) { return '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                parseNum(s) {
                    if (s == null) return null;
                    let str = String(s).trim().replace(/₱/g, '').replace(/\s/g, '');
                    if (!str) return null;
                    if (str.includes(',') && !str.includes('.')) {
                        str = str.replace(/,/g, '.');
                    } else {
                        str = str.replace(/,/g, '');
                    }
                    const v = parseFloat(str);
                    return isNaN(v) ? null : v;
                },

                formatFee() {
                    const v = this.parseNum(this.codFeeInput);
                    if (v === null || !isFinite(v) || v < 0) {
                        this.codFeeOverride = null;
                        this.codFeeInput = this.toFixed2(this.codFeeDefault);
                        return;
                    }
                    const eps = 0.005;
                    this.codFeeOverride = (Math.abs(v - this.codFeeDefault) > eps) ? +v.toFixed(2) : null;
                    this.codFeeInput = this.toFixed2(this.codFeeOverride ?? this.codFeeDefault);
                },
                formatVat() {
                    const v = this.parseNum(this.codFeeVatInput);
                    if (v === null || !isFinite(v) || v < 0) {
                        this.codFeeVatOverride = null;
                        this.codFeeVatInput = this.toFixed2(this.codFeeVatDefault);
                        return;
                    }
                    const eps = 0.005;
                    this.codFeeVatOverride = (Math.abs(v - this.codFeeVatDefault) > eps) ? +v.toFixed(2) : null;
                    this.codFeeVatInput = this.toFixed2(this.codFeeVatOverride ?? this.codFeeVatDefault);
                },
                resetFee() {
                    this.codFeeOverride = null;
                    this.codFeeInput = this.toFixed2(this.codFeeDefault);
                },
                resetVat() {
                    this.codFeeVatOverride = null;
                    this.codFeeVatInput = this.toFixed2(this.codFeeVatDefault);
                },
                isFeeOverridden() { return this.codFeeOverride !== null; },
                isVatOverridden() { return this.codFeeVatOverride !== null; },
            }
        }
    </script>
    @endpush
</x-app-layout>
