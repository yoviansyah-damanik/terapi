@props([
    'align' => 'left', // left, right, center
    'nowrap' => false,
    'action' => false, // Jika true, konten (opsi aksi) hanya visibel bila baris di-hover
])

@php
    $alignClass = match ($align) {
        'right' => 'text-right',
        'center' => 'text-center',
        default => '',
    };
    $nowrapClass = $nowrap ? 'whitespace-nowrap' : '';
@endphp

<td
    {{ $attributes->merge([
        'class' => "px-6 py-4 text-sm text-zinc-700 dark:text-primary-dark-200 {$alignClass} {$nowrapClass}",
    ]) }}>
    @if ($action)
        <div
            class="transition-opacity gap-1 duration-200 opacity-0 group-hover:opacity-100 focus-within:opacity-100 flex {{ match ($align) {'right' => 'justify-end','center' => 'justify-center',default => 'justify-start'} }}">
            {{ $slot }}
        </div>
    @else
        {{ $slot }}
    @endif
</td>
