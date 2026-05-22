{{--
    x-atoms.method-badge
    Usage: <x-atoms.method-badge method="GET" />
--}}
@props(['method' => null])

@php
    $method = strtoupper($method ?? '');
    $colorClass = match ($method) {
        'GET' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'POST' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'PUT', 'PATCH' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'DELETE' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        '' => 'hidden',
        default => 'bg-zinc-100 text-zinc-700 dark:bg-primary-dark-700 dark:text-primary-dark-300',
    };
@endphp

@if($method)
<span {{ $attributes->merge(['class' => "inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-bold {$colorClass}"]) }}>
    {{ $method }}
</span>
@endif
