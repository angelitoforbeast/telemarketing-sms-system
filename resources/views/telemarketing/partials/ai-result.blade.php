{{-- AI Analysis Result Panel - used inline and via AJAX injection --}}
<div class="mx-5 my-3 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl border border-indigo-200 shadow-sm overflow-hidden">
    {{-- Summary Section --}}
    <div class="px-5 py-4">
        <div class="flex items-center gap-2 mb-2">
            <div class="w-6 h-6 rounded-lg bg-indigo-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            </div>
            <h4 class="text-xs font-semibold text-indigo-600 uppercase tracking-wider">AI Summary</h4>
            @if(isset($log->ai_analyzed_at))
                <span class="text-xs text-gray-400 ml-auto">{{ $log->ai_analyzed_at instanceof \Carbon\Carbon ? $log->ai_analyzed_at->diffForHumans() : \Carbon\Carbon::parse($log->ai_analyzed_at)->diffForHumans() }}</span>
            @endif
        </div>
        <p class="text-sm text-gray-700 leading-relaxed bg-white rounded-lg p-4 border border-indigo-100 shadow-sm">{{ $log->ai_summary }}</p>
    </div>

    {{-- Transcription Section --}}
    <div class="px-5 pb-4">
        <div class="flex items-center gap-2 mb-2">
            <div class="w-6 h-6 rounded-lg bg-purple-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            </div>
            <h4 class="text-xs font-semibold text-purple-600 uppercase tracking-wider">Full Transcription</h4>
        </div>
        <div class="text-sm text-gray-600 bg-white rounded-lg p-4 border border-gray-200 max-h-72 overflow-y-auto leading-relaxed">
            @foreach(explode("\n", $log->transcription ?? '') as $line)
                @php $line = trim($line); @endphp
                @if(empty($line))
                    @continue
                @endif
                <div class="mb-1.5 last:mb-0">
                    @if(str_starts_with($line, 'AGENT:'))
                        <span class="font-semibold text-indigo-600">AGENT:</span><span class="text-gray-700">{{ substr($line, 6) }}</span>
                    @elseif(str_starts_with($line, 'CUSTOMER:'))
                        <span class="font-semibold text-green-600">CUSTOMER:</span><span class="text-gray-700">{{ substr($line, 9) }}</span>
                    @elseif(str_starts_with($line, 'UNKNOWN:'))
                        <span class="font-semibold text-gray-500">UNKNOWN:</span><span class="text-gray-700">{{ substr($line, 8) }}</span>
                    @else
                        <span class="text-gray-700">{{ $line }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
