<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Company Settings</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if(session('success'))
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                        {{ session('success') }}
                    </div>
                @endif

                <h3 class="text-lg font-semibold text-gray-800 mb-6">COD Fee Configuration</h3>

                <form method="POST" action="{{ route('settings.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-6">
                        {{-- COD Fee Rate --}}
                        <div>
                            <label for="cod_fee_rate" class="block text-sm font-medium text-gray-700">COD Fee Rate</label>
                            <div class="mt-1 flex items-center gap-3">
                                <input type="number" name="cod_fee_rate" id="cod_fee_rate"
                                       value="{{ old('cod_fee_rate', $company->cod_fee_rate) }}"
                                       step="0.0001" min="0" max="1"
                                       class="border-gray-300 rounded-md shadow-sm w-40 text-sm">
                                <span class="text-sm text-gray-500">
                                    = <strong id="codFeePercent">{{ number_format(($company->cod_fee_rate ?? 0.015) * 100, 2) }}%</strong>
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                Enter as decimal (e.g., 0.015 = 1.50%). This rate is applied to the total COD amount per day.
                            </p>
                            <p class="mt-1 text-xs text-gray-400">
                                Formula: COD Fee = Total COD Amount &times; COD Fee Rate
                            </p>
                            @error('cod_fee_rate')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- COD VAT Rate --}}
                        <div>
                            <label for="cod_vat_rate" class="block text-sm font-medium text-gray-700">COD VAT Rate</label>
                            <div class="mt-1 flex items-center gap-3">
                                <input type="number" name="cod_vat_rate" id="cod_vat_rate"
                                       value="{{ old('cod_vat_rate', $company->cod_vat_rate) }}"
                                       step="0.0001" min="0" max="1"
                                       class="border-gray-300 rounded-md shadow-sm w-40 text-sm">
                                <span class="text-sm text-gray-500">
                                    = <strong id="codVatPercent">{{ number_format(($company->cod_vat_rate ?? 0.12) * 100, 2) }}%</strong>
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                Enter as decimal (e.g., 0.12 = 12.00%). This VAT rate is applied to the COD Fee.
                            </p>
                            <p class="mt-1 text-xs text-gray-400">
                                Formula: COD VAT = COD Fee &times; COD VAT Rate
                            </p>
                            @error('cod_vat_rate')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Example Calculation --}}
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Example Calculation</h4>
                            <p class="text-xs text-gray-500" id="exampleCalc">
                                If total COD for the day = &#8369;100,000:<br>
                                COD Fee = &#8369;100,000 &times; {{ number_format($company->cod_fee_rate * 100, 2) }}% = &#8369;{{ number_format(100000 * $company->cod_fee_rate, 2) }}<br>
                                COD VAT = &#8369;{{ number_format(100000 * $company->cod_fee_rate, 2) }} &times; {{ number_format($company->cod_vat_rate * 100, 2) }}% = &#8369;{{ number_format(100000 * $company->cod_fee_rate * $company->cod_vat_rate, 2) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>

            {{-- Telemarketing Settings Link --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mt-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Telemarketing Settings</h3>
                        <p class="text-sm text-gray-500 mt-1">Configure auto-call mode, queue mode, and disposition mapping.</p>
                    </div>
                    <a href="{{ route('settings.telemarketing') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition">
                        Manage &rarr;
                    </a>
                </div>
            </div>

            {{-- Role Permissions Link --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mt-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Role Permissions</h3>
                        <p class="text-sm text-gray-500 mt-1">Manage what each role can view, access, and edit in your company.</p>
                    </div>
                    <a href="{{ route('settings.role-permissions') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-md hover:bg-emerald-700 transition">
                        Manage &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const feeInput = document.getElementById('cod_fee_rate');
            const vatInput = document.getElementById('cod_vat_rate');
            const feePercent = document.getElementById('codFeePercent');
            const vatPercent = document.getElementById('codVatPercent');

            feeInput.addEventListener('input', function() {
                feePercent.textContent = (parseFloat(this.value || 0) * 100).toFixed(2) + '%';
            });
            vatInput.addEventListener('input', function() {
                vatPercent.textContent = (parseFloat(this.value || 0) * 100).toFixed(2) + '%';
            });
        });
    </script>
    @endpush
</x-app-layout>
