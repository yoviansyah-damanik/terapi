<a href="{{ route('home') }}" wire:navigate class="block">
    {{-- Expanded: logo + nama --}}
    <div class="in-data-flux-sidebar-collapsed-desktop:hidden">
        <img src="{{ Vite::image('logo.png') }}" alt="Logo">
        <p class="text-xs mt-3 dark:text-neutral-300 text-neutral-900 text-center leading-4">
            {{ config('app.name') }}
        </p>
    </div>
    {{-- Collapsed: icon only --}}
    <div class="hidden in-data-flux-sidebar-collapsed-desktop:flex justify-center">
        <img src="{{ Vite::image('logo-icon.png') }}" alt="Logo" class="w-8 h-8">
    </div>
</a>
