<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Manajemen Pengguna')] class extends Component {
    #[Url]
    public string $tab = 'users';

    public function with(): array
    {
        return [];
    }
};
?>

<div>
    <x-ui.page-header title="Manajemen Pengguna" subtitle="Kelola pengguna dan hak akses sistem">
        @if ($tab === 'users')
            <x-slot:actions>
                <x-atoms.button variant="primary" icon="plus"
                    wire:click="$dispatchTo('pages::configuration._users.user-management', 'open-create-user')">
                    Tambah Pengguna
                </x-atoms.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <x-molecules.tabs>
        <x-atoms.tab-item wire:click="$set('tab', 'users')" :active="$tab === 'users'" icon="users">
            Pengguna
        </x-atoms.tab-item>
        <x-atoms.tab-item wire:click="$set('tab', 'permissions')" :active="$tab === 'permissions'" icon="shield-check">
            Hak Akses
        </x-atoms.tab-item>
    </x-molecules.tabs>

    @if ($tab === 'users')
        <livewire:pages::configuration._users.user-management />
    @else
        <livewire:pages::configuration._users.user-permissions />
    @endif
</div>
