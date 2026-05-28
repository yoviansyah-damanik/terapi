<?php

use App\Models\User;
use App\Models\ActivityLog;
use Livewire\Component;

new class extends Component {

    public bool $ready = false;

    public ?string $selectedUserId = null;
    public array $selectedPermissions = [];
    public bool $isDirty = false;

    public function init(): void
    {
        $this->ready = true;
    }

    public function selectUser(string $id): void
    {
        $this->selectedUserId      = $id;
        $this->selectedPermissions = User::findOrFail($id)->permissions ?? [];
        $this->isDirty             = false;
        $this->resetValidation();
    }

    public function updatedSelectedPermissions(): void
    {
        $this->isDirty = true;
    }

    public function savePermissions(): void
    {
        if (!$this->selectedUserId) return;

        try {
            $user = User::findOrFail($this->selectedUserId);

            if ($user->isAdmin()) {
                $this->toastError('Pengguna admin tidak memerlukan pengaturan hak akses.');
                return;
            }

            $user->update(['permissions' => $this->selectedPermissions]);
            ActivityLog::log(
                type: 'user_permissions_updated',
                subject: "Mengubah hak akses pengguna: {$user->name}",
                properties: ['user_id' => $user->id, 'permissions' => $this->selectedPermissions],
            );

            $this->isDirty = false;
            $this->toastSuccess("Hak akses {$user->name} berhasil disimpan.");
        } catch (\Exception $e) {
            $this->toastError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function clearPermissions(): void
    {
        $this->selectedPermissions = [];
        $this->isDirty = true;
    }

    public function grantAll(): void
    {
        $this->selectedPermissions = collect(config('permissions'))
            ->where('is_parent', false)
            ->pluck('key')
            ->all();
        $this->isDirty = true;
    }

    public function with(): array
    {
        if (!$this->ready) return ['users' => collect(), 'menuGroups' => [], 'selectedUser' => null];

        $users = User::orderBy('name')->get(['id', 'name', 'username', 'role', 'is_active', 'permissions']);

        $parents  = collect(config('permissions'))->where('is_parent', true)->keyBy('key');
        $children = collect(config('permissions'))->where('is_parent', false);

        $menuGroups = $children
            ->groupBy('parent')
            ->map(fn($items, $parentKey) => [
                'label' => $parents->get($parentKey)['label'] ?? $parentKey,
                'items' => $items->values()->all(),
            ])
            ->values()
            ->all();

        $selectedUser = $this->selectedUserId
            ? $users->firstWhere('id', $this->selectedUserId)
            : null;

        return compact('users', 'menuGroups', 'selectedUser');
    }
};
?>

<div wire:init="init">
    @if (!$ready)
        {{-- Skeleton --}}
        <div class="flex gap-6 animate-pulse">
            <div class="w-72 shrink-0 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200/60 dark:bg-primary-dark-800 dark:ring-primary-dark-700/60">
                <div class="flex items-center gap-2 px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/60">
                    <div class="size-4 rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
                    <div class="h-4 w-20 rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
                </div>
                @foreach (range(1, 7) as $i)
                    <div class="flex items-center gap-3 px-4 py-3 border-b border-zinc-50 dark:border-primary-dark-700/30 last:border-0">
                        <div class="size-8 rounded-full bg-zinc-200 dark:bg-primary-dark-700 shrink-0"></div>
                        <div class="flex-1 space-y-1.5 min-w-0">
                            <div class="h-3 {{ $i % 2 === 0 ? 'w-28' : 'w-24' }} rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
                            <div class="h-2.5 w-16 rounded bg-zinc-100 dark:bg-primary-dark-700/40"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex-1 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200/60 dark:bg-primary-dark-800 dark:ring-primary-dark-700/60">
                <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/60">
                    <div class="flex items-center gap-2">
                        <div class="size-4 rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
                        <div class="h-4 w-40 rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
                    </div>
                    <div class="flex gap-2">
                        <div class="h-8 w-24 rounded-lg bg-zinc-100 dark:bg-primary-dark-700/40"></div>
                        <div class="h-8 w-24 rounded-lg bg-zinc-100 dark:bg-primary-dark-700/40"></div>
                        <div class="h-8 w-20 rounded-lg bg-zinc-200 dark:bg-primary-dark-700"></div>
                    </div>
                </div>
                <div class="p-4 space-y-3">
                    @foreach ([8, 6, 4, 5] as $count)
                        <div class="rounded-lg overflow-hidden ring-1 ring-zinc-200 dark:ring-primary-dark-700/60">
                            <div class="flex items-center justify-between px-3 py-2 bg-zinc-50 dark:bg-primary-dark-900/30 border-b border-zinc-100 dark:border-primary-dark-700/40">
                                <div class="h-3 w-28 rounded bg-zinc-200 dark:bg-primary-dark-700"></div>
                                <div class="h-3 w-16 rounded bg-zinc-100 dark:bg-primary-dark-700/40"></div>
                            </div>
                            <div class="p-3 grid grid-cols-4 gap-2">
                                @foreach (range(1, $count) as $_)
                                    <div class="flex items-center gap-2">
                                        <div class="size-3.5 rounded bg-zinc-100 dark:bg-primary-dark-700/40 shrink-0"></div>
                                        <div class="h-2.5 w-16 rounded bg-zinc-100 dark:bg-primary-dark-700/40"></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="flex gap-6 items-start">

            {{-- Kolom kiri: Daftar pengguna --}}
            <div class="w-72 shrink-0">
                <x-organisms.data-panel title="Pengguna" icon="users" :subtitle="null">
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($users as $user)
                            <button type="button"
                                wire:click="selectUser('{{ $user->id }}')"
                                class="w-full flex items-center gap-3 px-4 py-3 text-left transition-colors
                                    hover:bg-zinc-50 dark:hover:bg-zinc-800/50
                                    {{ $selectedUserId === $user->id ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}">
                                <div class="flex items-center justify-center size-8 shrink-0 rounded-full
                                    {{ $selectedUserId === $user->id ? 'bg-primary-500' : 'bg-zinc-100 dark:bg-zinc-800' }}">
                                    <span class="text-xs font-semibold
                                        {{ $selectedUserId === $user->id ? 'text-white' : 'text-zinc-600 dark:text-zinc-400' }}">
                                        {{ $user->initials() }}
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <p class="text-sm font-medium truncate
                                            {{ $selectedUserId === $user->id
                                                ? 'text-primary-700 dark:text-primary-300'
                                                : 'text-zinc-800 dark:text-primary-dark-100' }}">
                                            {{ $user->name }}
                                        </p>
                                        @if (!$user->is_active)
                                            <flux:badge color="red" size="sm">Nonaktif</flux:badge>
                                        @endif
                                    </div>
                                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500 truncate">
                                        @if ($user->role === 'admin')
                                            <span class="text-amber-500">Admin</span>
                                        @else
                                            {{ count($user->permissions ?? []) }} hak akses
                                        @endif
                                    </p>
                                </div>
                                @if ($selectedUserId === $user->id)
                                    <flux:icon name="chevron-right" class="size-4 text-primary-500 shrink-0" />
                                @endif
                            </button>
                        @endforeach
                    </div>
                </x-organisms.data-panel>
            </div>

            {{-- Kolom kanan: Panel hak akses --}}
            <div class="flex-1 min-w-0">
                @if (!$selectedUser)
                    <div class="flex flex-col items-center justify-center h-80 rounded-xl border-2 border-dashed
                        border-zinc-200 dark:border-zinc-700 text-center">
                        <flux:icon name="shield-check" class="size-12 text-zinc-300 dark:text-zinc-600 mb-3" />
                        <p class="text-sm font-medium text-zinc-500 dark:text-primary-dark-400">Pilih pengguna</p>
                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mt-1">
                            Pilih pengguna dari daftar untuk mengatur hak aksesnya
                        </p>
                    </div>
                @elseif ($selectedUser->role === 'admin')
                    <x-organisms.data-panel :title="$selectedUser->name" icon="shield-check" subtitle="Administrator">
                        <div class="p-6 flex items-start gap-3">
                            <flux:icon name="shield-check" class="size-5 text-amber-500 shrink-0 mt-0.5" />
                            <div>
                                <p class="text-sm font-medium text-zinc-800 dark:text-primary-dark-100">
                                    Administrator mendapatkan akses penuh
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-1">
                                    Pengguna dengan role <strong>Admin</strong> secara otomatis dapat mengakses
                                    seluruh modul. Ubah role ke <strong>User</strong> untuk mengatur hak akses manual.
                                </p>
                            </div>
                        </div>
                    </x-organisms.data-panel>
                @else
                    <x-organisms.data-panel :title="$selectedUser->name" icon="shield-check"
                        subtitle="{{ count($selectedPermissions) }} hak akses aktif">

                        <x-slot:action>
                            <div class="flex items-center gap-2">
                                <x-atoms.button variant="ghost" size="sm" wire:click="clearPermissions">
                                    Hapus Semua
                                </x-atoms.button>
                                <x-atoms.button variant="ghost" size="sm" wire:click="grantAll">
                                    Pilih Semua
                                </x-atoms.button>
                                <x-atoms.button variant="primary" size="sm" icon="check"
                                    wire:click="savePermissions" :disabled="!$isDirty">
                                    Simpan
                                </x-atoms.button>
                            </div>
                        </x-slot:action>

                        <div class="p-4 space-y-3">
                            @if ($isDirty)
                                <div class="flex items-center gap-2 px-3 py-2 rounded-lg
                                    bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800
                                    text-xs text-amber-700 dark:text-amber-300">
                                    <flux:icon name="exclamation-triangle" class="size-3.5 shrink-0" />
                                    Ada perubahan yang belum disimpan.
                                </div>
                            @endif

                            @foreach ($menuGroups as $group)
                                @php $groupKeys = collect($group['items'])->pluck('key')->toArray(); @endphp
                                <div x-data="{
                                    get allSelected() {
                                        const keys = {{ Js::from($groupKeys) }};
                                        return keys.every(k => $wire.selectedPermissions.includes(k));
                                    },
                                    toggleAll() {
                                        const keys = {{ Js::from($groupKeys) }};
                                        const allIn = keys.every(k => $wire.selectedPermissions.includes(k));
                                        if (allIn) {
                                            $wire.selectedPermissions = $wire.selectedPermissions.filter(p => !keys.includes(p));
                                        } else {
                                            $wire.selectedPermissions = [...new Set([...$wire.selectedPermissions, ...keys])];
                                        }
                                        $wire.isDirty = true;
                                    }
                                }" class="rounded-lg overflow-hidden ring-1 ring-zinc-200 dark:ring-primary-dark-700/60">
                                    <div class="flex items-center justify-between px-3 py-2 bg-zinc-50 dark:bg-primary-dark-900/30">
                                        <p class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 uppercase tracking-wide">
                                            {{ $group['label'] }}
                                        </p>
                                        <button type="button" @click="toggleAll()"
                                            class="text-xs text-primary-600 dark:text-primary-400 hover:underline"
                                            x-text="allSelected ? 'Batalkan Semua' : 'Pilih Semua'">
                                        </button>
                                    </div>
                                    <div class="p-3 grid grid-cols-2 gap-1.5 sm:grid-cols-3 lg:grid-cols-4">
                                        @foreach ($group['items'] as $perm)
                                            <label class="flex items-center gap-2 cursor-pointer group">
                                                <input type="checkbox"
                                                    wire:model.live="selectedPermissions"
                                                    value="{{ $perm['key'] }}"
                                                    class="rounded border-zinc-300 dark:border-zinc-600 text-primary-600
                                                        focus:ring-primary-500 size-3.5 shrink-0" />
                                                <span class="text-xs text-zinc-700 dark:text-zinc-300
                                                    group-hover:text-zinc-900 dark:group-hover:text-white leading-tight">
                                                    {{ $perm['label'] }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-organisms.data-panel>
                @endif
            </div>
        </div>
    @endif
</div>
