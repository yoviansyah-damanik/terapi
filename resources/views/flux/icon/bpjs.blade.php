@php $attributes = $unescapedForwardedAttributes ?? $attributes; @endphp
@props([
    'variant' => 'outline',
])
@php
    $classes = Flux::classes('shrink-0')->add(
        match ($variant) {
            'outline' => '[:where(&)]:size-6',
            'solid' => '[:where(&)]:size-6',
            'mini' => '[:where(&)]:size-5',
            'micro' => '[:where(&)]:size-4',
        },
    );
@endphp
{{-- Your SVG code here: --}}
<svg viewBox="0 0 48 48" fill="none" {{ $attributes->class($classes) }} data-flux-icon aria-hidden="true">
    <rect x="6" y="10" width="18" height="18" rx="4" fill="currentColor" />
    <rect x="24" y="20" width="18" height="18" rx="4" fill="currentColor" />
</svg>
