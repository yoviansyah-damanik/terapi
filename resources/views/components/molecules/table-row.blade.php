{{-- Table row molecule; forwards all attributes (wire:key, class, etc.) --}}
<tr {{ $attributes->merge([
    'class' => 'group transition-colors hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50',
]) }}>
    {{ $slot }}
</tr>
