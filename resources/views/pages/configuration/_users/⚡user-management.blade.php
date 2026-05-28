<?php

use App\Models\User;
use App\Models\ActivityLog;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

new class extends Component {
    use WithPagination;

    public bool $ready = false;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterRole = '';

    #[Url]
    public string $filterStatus = '';

    public bool $showModal = false;
    public bool $showDeleteModal = false;
    public bool $showResetPasswordModal = false;
    public bool $isEditing = false;

    public ?string $userId = null;
    public ?string $resetPasswordUserId = null;
    public string $resetPasswordUserName = '';
    public string $newPassword = '';
    public string $newPassword_confirmation = '';
    public string $username = '';
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = 'user';
    public bool $is_active = true;

    public function init(): void
    {
        $this->ready = true;
    }

    public function rules(): array
    {
        $rules = [
            'username'  => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($this->userId)],
            'name'      => 'required|string|min:3|max:100',
            'email'     => ['required', 'email', Rule::unique('users')->ignore($this->userId)],
            'is_active' => 'boolean',
        ];

        if (!$this->isEditing || $this->password) {
            $rules['password'] = ['required', 'confirmed', Password::min(8)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'username.required'   => 'Username harus diisi',
            'username.unique'     => 'Username sudah digunakan',
            'username.alpha_dash' => 'Username hanya boleh berisi huruf, angka, dash, dan underscore',
            'name.required'       => 'Nama harus diisi',
            'email.required'      => 'Email harus diisi',
            'email.email'         => 'Format email tidak valid',
            'email.unique'        => 'Email sudah digunakan',
            'password.required'   => 'Password harus diisi',
            'password.confirmed'  => 'Konfirmasi password tidak cocok',
            'password.min'        => 'Password minimal 8 karakter',
        ];
    }

    public function updatedSearch(): void  { $this->resetPage(); }
    public function updatedFilterRole(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    #[On('open-create-user')]
    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function openEditModal(string $id): void
    {
        $user = User::findOrFail($id);

        $this->userId    = $user->id;
        $this->username  = $user->username;
        $this->name      = $user->name;
        $this->email     = $user->email;
        $this->role      = $user->role;
        $this->is_active = $user->is_active;
        $this->password  = '';
        $this->password_confirmation = '';

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        try {
            $targetRole = auth()->user()->isAdmin() ? $this->role : 'user';

            $data = [
                'username'  => $this->username,
                'name'      => $this->name,
                'email'     => $this->email,
                'role'      => $targetRole,
                'is_active' => $this->is_active,
            ];

            if ($this->password) {
                $data['password'] = $this->password;
            }

            if ($this->isEditing) {
                $user = User::findOrFail($this->userId);
                if ($targetRole === 'admin' && $user->role !== 'admin') {
                    $data['permissions'] = null;
                }
                $user->update($data);
                ActivityLog::log(type: 'user_updated', subject: "Mengubah data pengguna: {$user->name}", properties: ['user_id' => $user->id]);
                $this->toastSuccess('Data pengguna berhasil diperbarui');
            } else {
                $user = User::create($data);
                ActivityLog::log(type: 'user_created', subject: "Membuat pengguna baru: {$user->name}", properties: ['user_id' => $user->id]);
                $this->toastSuccess('Pengguna berhasil ditambahkan');
            }

            $this->showModal = false;
            $this->resetForm();
        } catch (\Exception $e) {
            $this->toastError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function confirmDelete(string $id): void
    {
        $this->userId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        try {
            $user = User::findOrFail($this->userId);

            if ($user->id === auth()->id()) {
                $this->toastError('Tidak dapat menghapus akun sendiri');
                return;
            }

            $userName = $user->name;
            $user->delete();

            ActivityLog::log(type: 'user_deleted', subject: "Menghapus pengguna: {$userName}");

            $this->toastSuccess('Pengguna berhasil dihapus');
            $this->showDeleteModal = false;
            $this->userId = null;
        } catch (\Exception $e) {
            $this->toastError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function toggleStatus(string $id): void
    {
        try {
            $user = User::findOrFail($id);

            if ($user->id === auth()->id()) {
                $this->toastError('Tidak dapat menonaktifkan akun sendiri');
                return;
            }

            $user->update(['is_active' => !$user->is_active]);
            $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
            ActivityLog::log(type: 'user_status_changed', subject: "Status pengguna {$user->name} {$status}", properties: ['user_id' => $user->id, 'is_active' => $user->is_active]);
            $this->toastSuccess("Pengguna berhasil {$status}");
        } catch (\Exception $e) {
            $this->toastError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function openResetPasswordModal(string $id): void
    {
        $user = User::findOrFail($id);
        $this->resetPasswordUserId   = $user->id;
        $this->resetPasswordUserName = $user->name;
        $this->newPassword           = '';
        $this->newPassword_confirmation = '';
        $this->resetValidation(['newPassword', 'newPassword_confirmation']);
        $this->showResetPasswordModal = true;
    }

    public function resetPassword(): void
    {
        $this->validate(
            ['newPassword' => ['required', 'string', 'min:8', 'confirmed']],
            [
                'newPassword.required'  => 'Password baru harus diisi',
                'newPassword.min'       => 'Password baru minimal 8 karakter',
                'newPassword.confirmed' => 'Konfirmasi password tidak cocok',
            ],
        );

        try {
            $user = User::findOrFail($this->resetPasswordUserId);
            $user->update(['password' => $this->newPassword]);

            ActivityLog::log(type: 'password_changed', subject: "Reset password pengguna: {$user->name}", properties: ['user_id' => $user->id, 'reset_by' => auth()->id()]);

            $this->toastSuccess("Password {$user->name} berhasil direset");
            $this->showResetPasswordModal   = false;
            $this->resetPasswordUserId      = null;
            $this->resetPasswordUserName    = '';
            $this->newPassword              = '';
            $this->newPassword_confirmation = '';
        } catch (\Exception $e) {
            $this->toastError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function resetForm(): void
    {
        $this->userId               = null;
        $this->username             = '';
        $this->name                 = '';
        $this->email                = '';
        $this->password             = '';
        $this->password_confirmation = '';
        $this->role                 = 'user';
        $this->is_active            = true;
        $this->resetValidation();
    }

    public function with(): array
    {
        $roles = User::getRoles();

        if (!$this->ready) {
            return ['users' => collect(), 'roles' => $roles, 'statTotal' => 0, 'statAdmin' => 0, 'statUser' => 0, 'statInactive' => 0];
        }

        $query = User::query()
            ->when($this->search, fn($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('username', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->when($this->filterRole, fn($q) => $q->where('role', $this->filterRole))
            ->when($this->filterStatus !== '', fn($q) => $q->where('is_active', $this->filterStatus === '1'))
            ->orderBy('name');

        $allUsers = User::get(['role', 'is_active']);

        return [
            'users'       => $query->paginate(10),
            'roles'       => $roles,
            'statTotal'   => $allUsers->count(),
            'statAdmin'   => $allUsers->where('role', 'admin')->count(),
            'statUser'    => $allUsers->where('role', 'user')->count(),
            'statInactive'=> $allUsers->where('is_active', false)->count(),
        ];
    }
};
?>

<div wire:init="init">
    @if (!$ready)
        {{-- Skeleton --}}
        <div class="animate-pulse space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                @foreach (range(1, 4) as $_)
                    <div class="h-24 rounded-2xl bg-white dark:bg-primary-dark-800 border border-zinc-100 dark:border-primary-dark-700/80 shadow-sm"></div>
                @endforeach
            </div>
            <div class="h-80 rounded-xl bg-white dark:bg-primary-dark-800 ring-1 ring-zinc-200/60 dark:ring-primary-dark-700/60"></div>
        </div>
    @else
        <div class="space-y-4">

            {{-- Stat cards --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <x-organisms.stat-card title="Total Pengguna" :value="$statTotal" icon="users" color="zinc" />
                <x-organisms.stat-card title="Administrator" :value="$statAdmin" icon="shield-check" color="amber" />
                <x-organisms.stat-card title="Pengguna Biasa" :value="$statUser" icon="user" color="blue" />
                <x-organisms.stat-card title="Nonaktif" :value="$statInactive" icon="no-symbol" color="red" />
            </div>

            {{-- Tabel --}}
            <x-organisms.data-panel title="Daftar Pengguna" icon="users"
                subtitle="{{ $users->total() }} pengguna{{ $search || $filterRole || $filterStatus !== '' ? ' ditemukan' : ' terdaftar' }}">

                <x-slot:filter>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <flux:input wire:model.live.debounce.300ms="search" class="flex-1"
                            placeholder="Cari nama, username, atau email..." icon="magnifying-glass" clearable />
                        <flux:select wire:model.live="filterRole" class="sm:w-40">
                            <flux:select.option value="">Semua Role</flux:select.option>
                            @foreach ($roles as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model.live="filterStatus" class="sm:w-36">
                            <flux:select.option value="">Semua Status</flux:select.option>
                            <flux:select.option value="1">Aktif</flux:select.option>
                            <flux:select.option value="0">Nonaktif</flux:select.option>
                        </flux:select>
                    </div>
                </x-slot:filter>

                <x-organisms.table>
                    <x-slot:headings>
                        <x-atoms.table-heading>Pengguna</x-atoms.table-heading>
                        <x-atoms.table-heading>Username</x-atoms.table-heading>
                        <x-atoms.table-heading>Role</x-atoms.table-heading>
                        <x-atoms.table-heading>Status</x-atoms.table-heading>
                        <x-atoms.table-heading>Login Terakhir</x-atoms.table-heading>
                        <x-atoms.table-heading align="right">Aksi</x-atoms.table-heading>
                    </x-slot:headings>

                    @forelse ($users as $user)
                        <tr wire:key="user-{{ $user->id }}"
                            class="group transition-colors hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/20">

                            {{-- Pengguna --}}
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center size-9 shrink-0 rounded-full
                                        {{ $user->is_active ? 'bg-primary-100 dark:bg-primary-900' : 'bg-zinc-100 dark:bg-primary-dark-700' }}">
                                        <span class="text-sm font-semibold
                                            {{ $user->is_active ? 'text-primary-700 dark:text-primary-300' : 'text-zinc-400 dark:text-zinc-500' }}">
                                            {{ $user->initials() }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">{{ $user->name }}</p>
                                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Username --}}
                            <td class="px-5 py-3.5">
                                <span class="font-mono text-xs text-zinc-600 dark:text-primary-dark-300">{{ $user->username }}</span>
                            </td>

                            {{-- Role --}}
                            <td class="px-5 py-3.5">
                                <flux:badge :color="$user->role === 'admin' ? 'amber' : 'zinc'" size="sm">
                                    {{ $roles[$user->role] ?? $user->role }}
                                </flux:badge>
                            </td>

                            {{-- Status (klik untuk toggle) --}}
                            <td class="px-5 py-3.5">
                                @if ($user->id === auth()->id())
                                    <flux:badge color="green" size="sm">Aktif</flux:badge>
                                @else
                                    <button type="button"
                                        wire:click="toggleStatus('{{ $user->id }}')"
                                        class="transition-opacity hover:opacity-70">
                                        <flux:badge :color="$user->is_active ? 'green' : 'red'" size="sm">
                                            {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </flux:badge>
                                    </button>
                                @endif
                            </td>

                            {{-- Login Terakhir --}}
                            <td class="px-5 py-3.5">
                                @if ($user->last_login_at)
                                    <p class="text-sm text-zinc-700 dark:text-primary-dark-300">
                                        {{ $user->last_login_at->diffForHumans() }}
                                    </p>
                                    <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                                        {{ $user->last_login_at->format('d/m/Y H:i') }}
                                    </p>
                                @else
                                    <span class="text-xs text-zinc-400 dark:text-primary-dark-500">Belum pernah login</span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="px-5 py-3.5 text-right">
                                @if ($user->role === 'admin' && $user->id === auth()->id())
                                    <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                                @else
                                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <x-atoms.button variant="ghost" size="xs" icon="pencil-square"
                                            tooltip="Edit" wire:click="openEditModal('{{ $user->id }}')" />
                                        <x-atoms.button variant="ghost" size="xs" icon="key"
                                            tooltip="Reset Password" wire:click="openResetPasswordModal('{{ $user->id }}')" />
                                        <x-atoms.button variant="ghost" size="xs" icon="trash"
                                            tooltip="Hapus" wire:click="confirmDelete('{{ $user->id }}')" />
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-ui.empty-state icon="users"
                                    title="Tidak ada pengguna ditemukan"
                                    :description="$search ? 'Coba ubah kata kunci pencarian Anda.' : 'Belum ada pengguna terdaftar.'" />
                            </td>
                        </tr>
                    @endforelse
                </x-organisms.table>

                @if ($users->hasPages())
                    <x-slot:footer>
                        <div class="px-5 py-3">{{ $users->links() }}</div>
                    </x-slot:footer>
                @endif
            </x-organisms.data-panel>
        </div>

        {{-- Create / Edit Modal --}}
        <x-organisms.modal wire:model="showModal" maxWidth="lg" title="">
            <div class="space-y-1 mb-5">
                <flux:heading size="lg">{{ $isEditing ? 'Edit Pengguna' : 'Tambah Pengguna' }}</flux:heading>
                <flux:subheading>{{ $isEditing ? 'Perbarui informasi akun pengguna.' : 'Isi formulir untuk menambahkan pengguna baru.' }}</flux:subheading>
            </div>

            <form id="user-form" wire:submit="save">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-form.input wire:model="username" name="username"
                        label="Username" placeholder="contoh: johndoe" />

                    <x-form.input wire:model="name" name="name"
                        label="Nama Lengkap" placeholder="contoh: John Doe" />

                    <x-form.input wire:model="email" name="email" type="email"
                        label="Email" placeholder="contoh: john@example.com" class="sm:col-span-2" />

                    @if (auth()->user()->isAdmin())
                        <x-form.select wire:model.live="role" name="role" label="Role">
                            @foreach ($roles as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </x-form.select>
                    @endif

                    <x-form.select wire:model="is_active" name="is_active" label="Status Akun">
                        <flux:select.option value="1">Aktif</flux:select.option>
                        <flux:select.option value="0">Nonaktif</flux:select.option>
                    </x-form.select>

                    <x-form.input wire:model="password" name="password" type="password"
                        label="Password"
                        :placeholder="$isEditing ? 'Password baru...' : 'Minimal 8 karakter'"
                        :description="$isEditing ? 'Kosongkan jika tidak ingin mengubah password.' : null" />

                    <x-form.input wire:model="password_confirmation" name="password_confirmation"
                        type="password" label="Konfirmasi Password" placeholder="Ulangi password" />
                </div>
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-3">
                    <x-atoms.button variant="ghost" wire:click="$set('showModal', false)">Batal</x-atoms.button>
                    <x-atoms.button wire:click="save" variant="primary">
                        {{ $isEditing ? 'Simpan Perubahan' : 'Tambah Pengguna' }}
                    </x-atoms.button>
                </div>
            </x-slot:footer>
        </x-organisms.modal>

        {{-- Delete Confirmation Modal --}}
        <x-organisms.modal wire:model="showDeleteModal" maxWidth="sm" title="">
            <div class="flex flex-col items-center text-center gap-4 py-2">
                <div class="flex items-center justify-center size-14 rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="trash" class="size-7 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Pengguna?</flux:heading>
                    <flux:subheading class="mt-1">
                        Tindakan ini permanen dan tidak dapat dibatalkan.
                        Seluruh data pengguna akan dihapus dari sistem.
                    </flux:subheading>
                </div>
            </div>
            <x-slot:footer>
                <div class="flex justify-center gap-3">
                    <x-atoms.button variant="ghost" wire:click="$set('showDeleteModal', false)">Batal</x-atoms.button>
                    <x-atoms.button variant="danger" icon="trash" wire:click="delete">Ya, Hapus</x-atoms.button>
                </div>
            </x-slot:footer>
        </x-organisms.modal>

        {{-- Reset Password Modal --}}
        <x-organisms.modal wire:model="showResetPasswordModal" maxWidth="sm" title="">
            <div class="flex items-start gap-4 mb-5">
                <div class="flex items-center justify-center size-11 shrink-0 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                    <flux:icon name="key" class="size-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <flux:heading size="lg">Reset Password</flux:heading>
                    <flux:subheading>{{ $resetPasswordUserName }}</flux:subheading>
                </div>
            </div>

            <form wire:submit="resetPassword">
                <div class="space-y-4">
                    <x-form.input wire:model="newPassword" name="newPassword"
                        type="password" label="Password Baru" placeholder="Minimal 8 karakter" />
                    <x-form.input wire:model="newPassword_confirmation" name="newPassword_confirmation"
                        type="password" label="Konfirmasi Password Baru" placeholder="Ulangi password baru" />
                </div>
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-3">
                    <x-atoms.button variant="ghost" wire:click="$set('showResetPasswordModal', false)">Batal</x-atoms.button>
                    <x-atoms.button wire:click="resetPassword" variant="primary">Reset Password</x-atoms.button>
                </div>
            </x-slot:footer>
        </x-organisms.modal>
    @endif
</div>
