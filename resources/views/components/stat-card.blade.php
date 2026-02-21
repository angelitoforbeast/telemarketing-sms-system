@props(['title', 'value', 'color' => 'indigo', 'subtitle' => null])

@php
$borderClass = "border-{$color}-500";
$textClass = "text-{$color}-600";
@endphp

<div class="bg-white overflow-hidden shadow rounded-lg border-l-4 {{ $borderClass }}">
    <div class="p-5">
        <div class="flex items-center">
            <div class="flex-1">
                <dt class="text-sm font-medium text-gray-500 truncate">{{ $title }}</dt>
                <dd class="mt-1 text-3xl font-semibold {{ $textClass }}">{{ $value }}</dd>
                @if($subtitle)
                    <dd class="mt-1 text-xs text-gray-400">{{ $subtitle }}</dd>
                @endif
            </div>
        </div>
    </div>
</div>
