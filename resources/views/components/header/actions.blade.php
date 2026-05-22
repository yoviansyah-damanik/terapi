<div class="flex items-center gap-2">
    {{-- Dark Mode Toggle --}}
    <x-dark-mode-toggle variant="button" />

    {{-- User Menu (Mobile) --}}
    @auth
        <form method="POST" action="{{ route('logout') }}" id="logout-form-header" class="hidden">
            @csrf
        </form>

        <flux:dropdown align="end">
            <flux:button variant="subtle" size="sm">
                <flux:avatar size="xs"
                    src="{{ auth()->user()->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=00923f&color=fff' }}" />
            </flux:button>

            <flux:menu>
                <flux:menu.heading>{{ auth()->user()->name }}</flux:menu.heading>

                <flux:menu.item icon="user-circle" href="{{ route('profile') }}" wire:navigate>
                    Profil
                </flux:menu.item>

                <flux:menu.separator />

                <flux:menu.item icon="arrow-right-start-on-rectangle" variant="danger"
                    x-on:click="document.getElementById('logout-form-header').submit()">
                    Keluar
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    @endauth
</div>
