@auth
<form method="POST" action="{{ route('logout') }}" id="logout-form-sidebar" class="hidden">
    @csrf
</form>

<flux:dropdown position="top" align="start" class="w-full">
    <flux:profile
        class="w-full cursor-pointer"
        avatar="{{ auth()->user()->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&background=00923f&color=fff' }}"
        name="{{ auth()->user()->name }}"
        subtitle="{{ auth()->user()->role ?? 'User' }}"
    />

    <flux:menu class="w-[220px]">
        <flux:menu.item icon="user-circle" href="#">
            Profil Saya
        </flux:menu.item>
        <flux:menu.item icon="cog-6-tooth" href="#">
            Pengaturan
        </flux:menu.item>

        <flux:menu.separator />

        <flux:menu.item
            icon="arrow-right-start-on-rectangle"
            variant="danger"
            x-on:click="document.getElementById('logout-form-sidebar').submit()"
        >
            Keluar
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>
@endauth
