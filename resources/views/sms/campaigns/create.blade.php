<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create SMS Campaign</h2>
            <a href="{{ route('sms.campaigns.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">

            @if($errors->any())
                <x-alert type="error">
                    @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
                </x-alert>
            @endif

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-6 py-6">
                    <form method="POST" action="{{ route('sms.campaigns.store') }}">
                        @csrf

                        <div class="mb-4">
                            <x-input-label for="name" value="Campaign Name *" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                        </div>

                        <div class="mb-4">
                            <x-input-label for="trigger_status_id" value="Trigger Status *" />
                            <select id="trigger_status_id" name="trigger_status_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- Select Status --</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->id }}" {{ old('trigger_status_id') == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">SMS will be sent to shipments with this status.</p>
                        </div>

                        <div class="mb-4">
                            <x-input-label for="sms_template" value="SMS Template *" />
                            <textarea id="sms_template" name="sms_template" rows="4" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Hi {consignee_name}, your package {waybill_no} is {status}. Please contact us at...">{{ old('sms_template') }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">Available placeholders: <code>{consignee_name}</code>, <code>{waybill_no}</code>, <code>{status}</code>, <code>{courier}</code>, <code>{cod_amount}</code></p>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <x-input-label for="daily_send_limit" value="Daily Send Limit" />
                                <x-text-input id="daily_send_limit" name="daily_send_limit" type="number" class="mt-1 block w-full" :value="old('daily_send_limit')" min="1" placeholder="Unlimited" />
                            </div>
                            <div class="flex items-end gap-4 pb-2">
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <span class="ml-2 text-sm text-gray-600">Active</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="send_daily_repeat" value="1" {{ old('send_daily_repeat') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                    <span class="ml-2 text-sm text-gray-600">Daily Repeat</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Create Campaign</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
