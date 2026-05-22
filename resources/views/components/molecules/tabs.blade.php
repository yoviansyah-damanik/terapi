{{-- Tabs container molecule --}}
<div {{ $attributes->merge(['class' => 'mb-6 border-b border-zinc-200 dark:border-primary-dark-700']) }}>
    <nav class="flex -mb-px gap-x-1">
        {{ $slot }}
    </nav>
</div>
