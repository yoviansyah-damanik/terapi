@props([
    'align' => 'left', // left, right, center
])

@php
    $alignClass = match($align) {
        'right'  => 'text-right',
        'center' => 'text-center',
        default  => 'text-left',
    };
@endphp

<th {{ $attributes->merge([
    'class' => "px-6 py-3 text-xs font-semibold tracking-wider uppercase text-zinc-500 dark:text-primary-dark-400 {$alignClass}",
]) }}>
    {{ $slot }}
</th>
