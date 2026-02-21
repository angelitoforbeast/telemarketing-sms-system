@props(['color' => 'gray'])

@php
$classes = match($color) {
    'green' => 'bg-green-100 text-green-800',
    'emerald' => 'bg-emerald-100 text-emerald-800',
    'red' => 'bg-red-100 text-red-800',
    'yellow' => 'bg-yellow-100 text-yellow-800',
    'blue' => 'bg-blue-100 text-blue-800',
    'indigo' => 'bg-indigo-100 text-indigo-800',
    'purple' => 'bg-purple-100 text-purple-800',
    'orange' => 'bg-orange-100 text-orange-800',
    default => 'bg-gray-100 text-gray-800',
};
@endphp

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $classes }}">
    {{ $slot }}
</span>
