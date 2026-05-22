@props([
    'title'       => 'Upload File',
    'subtitle'    => null,
    'wireModel'   => 'images',
    'accept'      => 'image/jpeg,image/png',
    'maxSizeMb'   => 10,
    'description' => null,
])

@php
    $description ??= implode(' / ', array_map(fn ($t) => strtoupper(explode('/', $t)[1] ?? $t), explode(',', $accept)))
        . ', maks. ' . $maxSizeMb . ' MB per file';
@endphp

<x-ui.section-card :title="$title" :subtitle="$subtitle">
    <x-atoms.image-dropzone
        :wireModel="$wireModel"
        :accept="$accept"
        :maxSizeMb="$maxSizeMb"
        :description="$description"
        {{ $attributes->only(['class']) }}
    />

    {{ $slot }}
</x-ui.section-card>
