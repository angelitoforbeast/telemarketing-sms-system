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
             x-data="remitUI('{{ $start }}','{{ $end }}')" x-init="init()">

            {{-- ─── Filters ─── --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="grid md:grid-cols-3 gap-3 items-end">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Date Range</label>
                        <input id="remitRange" type="text" placeholder="Select date range"
                               class="w-full border border-gray-300 p-2.5 rounded-lg shadow-sm cursor-pointer bg-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" readonly>
                        <div class="text-xs text-gray-500 mt-1.5" x-text="dateLabel"></div>
                    </div>
                    <div class="flex gap-2 md:justify-end">
                        <button class="px-4 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-400 transition-colors" @click="thisMonth()">This Month</button>
                        <button class="px-4 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-400 transition-colors" @click="lastMonth()">Last Month</button>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-4 text-xs text-gray-500">
                    <span>
                        COD Fee Rate: <strong class="text-gray-700">{{ number_format($codFeeRate * 100, 2) }}%</strong>
                    </span>
                    <span class="text-gray-300">|</span>
                    <span>
                        COD Fee VAT Rate: <strong class="text-gray-700">{{ number_format($codVatRate * 100, 2) }}%</strong>
                    </span>
                    @can('settings.manage')
                        <a href="{{ route('settings.edit') }}" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 hover:underline font-medium transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Settings
                        </a>
                    @endcan
                </div>
            </div>

            {{-- ─── Summary Table ─── --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="font-semibold text-gray-800 text-base">Summary</div>
                    <div class="text-xs text-gray-500">
                        Delivered by <em>signing_time</em> &middot; Pickups by <em>submission_time</em>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full bg-white text-xs">
                        <thead>
                            <tr class="bg-gray-50 text-gray-600 uppercase tracking-wider text-[11px]">
                                <th class="px-4 py-3 text-left font-semibold border-b border-gray-200">Date</th>
                                <th class="px-4 py-3 text-right font-semibold border-b border-gray-200">Delivered</th>
                                <th class="px-4 py-3 text-right font-semibold border-b border-gray-200">COD Sum</th>
                                <th class="px-4 py-3 text-right font-semibold border-b border-gray-200">
                                    COD Fee
                                    <div class="text-[10px] font-normal text-gray-400 normal-case">({{ number_format($codFeeRate * 100, 2) }}%)</div>
                                </th>
                                <th class="px-4 py-3 text-right font-semibold border-b border-gray-200">
                                    COD Fee VAT
                                    <div class="text-[10px] font-normal text-gray-400 normal-case">({{ number_format($codVatRate * 100, 2) }}% of Fee)</div>
                                </th>
                                <th class="px-4 py-3 text-right font-semibold border-b border-gray-200">Parcels Picked Up</th>
                                <th class="px-4 py-3 text-right font-semibold border-b border-gray-200">Total Shipping Cost</th>
                                <th class="px-4 py-3 text-right font-semibold border-b border-gray-200">Remittance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rows as $r)
                                <tr class="hover:bg-indigo-50/30 transition-colors">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-gray-800 font-medium">{{ \Carbon\Carbon::parse($r['date'])->format('M d, Y (D)') }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-700">{{ number_format($r['delivered']) }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-700">&#8369;{{ number_format($r['cod_sum'], 2) }}</td>
                                    <td class="px-4 py-2.5 text-right text-red-600">&#8369;{{ number_format($r['cod_fee'], 2) }}</td>
                                    <td class="px-4 py-2.5 text-right text-red-600">&#8369;{{ number_format($r['cod_fee_vat'], 2) }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-700">{{ number_format($r['picked']) }}</td>
                                    <td class="px-4 py-2.5 text-right text-red-600">&#8369;{{ number_format($r['ship_cost'], 2) }}</td>
                                    <td class="px-4 py-2.5 text-right font-bold {{ $r['remittance'] >= 0 ? 'text-green-700' : 'text-red-700' }}">&#8369;{{ number_format($r['remittance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-8 text-center text-gray-400" colspan="8">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            <span>No data for the selected date range.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        {{-- ─── TOTALS with editable COD Fee & COD Fee VAT ─── --}}
                        <tfoot
                            x-data="codFeeTotals({
                                codSum: {{ json_encode($totals['cod_sum']) }},
                                codFee: {{ json_encode($totals['cod_fee']) }},
                                codFeeVat: {{ json_encode($totals['cod_fee_vat']) }},
                                shipCost: {{ json_encode($totals['ship_cost']) }}
                            })"
                            x-init="init()">
                            <tr class="bg-gray-50 border-t-2 border-gray-300">
                                <th class="px-4 py-3 text-right font-bold text-gray-800 uppercase tracking-wider text-[11px]">Total</th>
                                <th class="px-4 py-3 text-right font-bold text-gray-800">{{ number_format($totals['delivered']) }}</th>
                                <th class="px-4 py-3 text-right font-bold text-gray-800">&#8369;{{ number_format($totals['cod_sum'], 2) }}</th>

                                {{-- Editable TOTAL COD Fee --}}
                                <th class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <span class="text-red-600">&#8369;</span>
                                        <input type="text" inputmode="decimal"
                                               class="no-spin w-28 border border-gray-300 rounded-md px-2 py-1 text-right text-xs font-bold text-red-600 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                               x-model="codFeeInput"
                                               @blur="formatFee()"
                                               @keydown.enter.prevent="formatFee()">
                                        <button type="button" class="text-[10px] px-1.5 py-1 border border-gray-300 rounded-md hover:bg-gray-100 text-gray-500 transition-colors" @click="resetFee()">Reset</button>
                                    </div>
                                    <div class="text-[10px] text-amber-600 mt-1" x-show="isFeeOverridden()" x-cloak>
                                        overridden (was <span x-text="money(codFeeDefault)"></span>)
                                    </div>
                                </th>

                                {{-- Editable TOTAL COD Fee VAT --}}
                                <th class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <span class="text-red-600">&#8369;</span>
                                        <input type="text" inputmode="decimal"
                                               class="no-spin w-28 border border-gray-300 rounded-md px-2 py-1 text-right text-xs font-bold text-red-600 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                               x-model="codFeeVatInput"
                                               @blur="formatVat()"
                                               @keydown.enter.prevent="formatVat()">
                                        <button type="button" class="text-[10px] px-1.5 py-1 border border-gray-300 rounded-md hover:bg-gray-100 text-gray-500 transition-colors" @click="resetVat()">Reset</button>
                                    </div>
                                    <div class="text-[10px] text-amber-600 mt-1" x-show="isVatOverridden()" x-cloak>
                                        overridden (was <span x-text="money(codFeeVatDefault)"></span>)
                                    </div>
                                </th>

                                <th class="px-4 py-3 text-right font-bold text-gray-800">{{ number_format($totals['picked']) }}</th>
                                <th class="px-4 py-3 text-right font-bold text-red-600">&#8369;{{ number_format($totals['ship_cost'], 2) }}</th>

                                {{-- TOTAL Remittance reacts to overrides --}}
                                <th class="px-4 py-3 text-right text-base font-bold"
                                    :class="remittanceEffective >= 0 ? 'text-green-700' : 'text-red-700'"
                                    x-text="money(remittanceEffective)"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Formula reference --}}
                <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-100">
                    <div class="text-[11px] text-gray-500">
                        <span class="font-semibold text-gray-600">Formulas:</span>
                        <span class="ml-2">COD Fee = <code class="bg-white px-1 py-0.5 rounded border text-gray-700">{{ number_format($codFeeRate * 100, 2) }}% &times; COD Sum</code></span>
                        <span class="mx-1.5 text-gray-300">&middot;</span>
                        <span>COD Fee VAT = <code class="bg-white px-1 py-0.5 rounded border text-gray-700">{{ number_format($codVatRate * 100, 2) }}% &times; COD Fee</code></span>
                        <span class="mx-1.5 text-gray-300">&middot;</span>
                        <span>Remittance = <code class="bg-white px-1 py-0.5 rounded border text-gray-700">COD Sum &minus; COD Fee &minus; COD Fee VAT &minus; Shipping Cost</code></span>
                    </div>
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
