<x-app-layout>
    <x-slot name="title">AI Settings</x-slot>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">AI Settings</h2>
            <a href="{{ route('platform.dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md bg-gray-100 hover:bg-gray-200 transition">&larr; Back to Dashboard</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('platform.ai-settings.update') }}">
                @csrf
                @method('PUT')

                {{-- Call Analysis Prompt --}}
                <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">Call Analysis Prompt</h3>
                        <p class="text-sm text-gray-500 mt-1">This prompt is sent to the AI along with the call transcription to generate a summary. It applies globally to all companies.</p>
                    </div>
                    <div class="px-6 py-5">
                        <textarea
                            name="call_analysis_prompt"
                            rows="10"
                            class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono leading-relaxed"
                            placeholder="Enter the AI prompt for call analysis..."
                        >{{ old('call_analysis_prompt', $callAnalysisPrompt) }}</textarea>
                        @error('call_analysis_prompt')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <div class="mt-3 p-3 bg-blue-50 rounded-lg">
                            <p class="text-xs font-semibold text-blue-700 mb-1">Tip:</p>
                            <p class="text-xs text-blue-600">The call transcription will be appended after this prompt. You can instruct the AI on what to focus on, the language to use, and the format of the summary.</p>
                        </div>
                    </div>
                </div>

                {{-- AI Model Info --}}
                <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">AI Configuration</h3>
                    </div>
                    <div class="px-6 py-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Transcription Engine</label>
                                <div class="px-3 py-2 bg-gray-100 rounded-md text-sm text-gray-600">OpenAI Whisper (automatic)</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Analysis Model</label>
                                <div class="px-3 py-2 bg-gray-100 rounded-md text-sm text-gray-600">GPT-4.1-mini</div>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-gray-500">Transcription converts audio recordings to text. The analysis model then processes the transcript using the prompt above to generate a summary.</p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition shadow-sm">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
