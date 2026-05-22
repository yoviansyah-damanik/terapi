<?php

use App\Models\User;
use App\Models\ActivityLog;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

new #[Layout('layouts::app')] #[Title('Manajemen Pengguna')] class extends Component {
    use WithPagination;

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

    public function rules()
    {
        $rules = [
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($this->userId)],
            'name' => 'required|string|min:3|max:100',
            'email' => ['required', 'email', Rule::unique('users')->ignore($this->userId)],
            'is_active' => 'boolean',
        ];

        if (!$this->isEditing || $this->password) {
            $rules['password'] = ['required', 'confirmed', Password::min(8)];
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'username.required' => 'Username harus diisi',
            'username.unique' => 'Username sudah digunakan',
            'username.alpha_dash' => 'Username hanya boleh berisi huruf, angka, dash, dan underscore',
            'name.required' => 'Nama harus diisi',
            'email.required' => 'Email harus diisi',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah digunakan',
            'password.required' => 'Password harus diisi',
            'password.confirmed' => 'Konfirmasi password tidak cocok',
            'password.min' => 'Password minimal 8 karakter',
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterRole()
    {
        $this->resetPage();
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function openEditModal(string $id)
    {
        $user = User::findOrFail($id);

        $this->userId = $user->id;
        $this->username = $user->username;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->is_active = $user->is_active;
        $this->password = '';
        $this->password_confirmation = '';

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        try {
            $data = [
                'username' => $this->username,
                'name' => $this->name,
                'email' => $this->email,
                'role' => 'user',
                'is_active' => $this->is_active,
            ];

            if ($this->password) {
                $data['password'] = $this->password;
            }

            if ($this->isEditing) {
                $user = User::findOrFail($this->userId);
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

    public function confirmDelete(string $id)
    {
        $this->userId = $id;
        $this->showDeleteModal = true;
    }

    public function delete()
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

    public function toggleStatus(string $id)
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

    public function openResetPasswordModal(string $id)
    {
        $user = User::findOrFail($id);
        $this->resetPasswordUserId = $user->id;
        $this->resetPasswordUserName = $user->name;
        $this->newPassword = '';
        $this->newPassword_confirmation = '';
        $this->resetValidation(['newPassword', 'newPassword_confirmation']);
        $this->showResetPasswordModal = true;
    }

    public function resetPassword()
    {
        $this->validate(
            [
                'newPassword' => ['required', 'string', 'min:8', 'confirmed'],
            ],
            [
                'newPassword.required' => 'Password baru harus diisi',
                'newPassword.min' => 'Password baru minimal 8 karakter',
                'newPassword.confirmed' => 'Konfirmasi password tidak cocok',
            ],
        );

        try {
            $user = User::findOrFail($this->resetPasswordUserId);
            $user->update(['password' => $this->newPassword]);

            ActivityLog::log(type: 'password_changed', subject: "Reset password pengguna: {$user->name}", properties: ['user_id' => $user->id, 'reset_by' => auth()->id()]);

            $this->toastSuccess("Password {$user->name} berhasil direset");
            $this->showResetPasswordModal = false;
            $this->resetPasswordUserId = null;
            $this->resetPasswordUserName = '';
            $this->newPassword = '';
            $this->newPassword_confirmation = '';
        } catch (\Exception $e) {
            $this->toastError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function resetForm()
    {
        $this->userId = null;
        $this->username = '';
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->role = 'user';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function with(): array
    {
        $query = User::query()
            ->when(
                $this->search,
                fn($q) => $q->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('username', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                }),
            )
            ->when($this->filterRole, fn($q) => $q->where('role', $this->filterRole))
            ->when($this->filterStatus !== '', fn($q) => $q->where('is_active', $this->filterStatus === '1'))
            ->orderBy('name');

        return [
            'users' => $query->paginate(10),
            'roles' => User::getRoles(),
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Manajemen Pengguna" subtitle="Kelola pengguna yang dapat mengakses sistem">
        <x-slot:actions>
            <x-atoms.button variant="primary" icon="plus" wire:click="openCreateModal">
                Tambah Pengguna
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Data Panel: Filter + Tabel --}}
    <x-organisms.data-panel title="Daftar Pengguna" icon="users" subtitle="{{ $users->total() }} pengguna terdaftar">

        <x-slot:action>
            <x-atoms.button variant="primary" icon="plus" wire:click="openCreateModal" size="sm">
                Tambah Pengguna
            </x-atoms.button>
        </x-slot:action>

        <x-slot:filter>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div class="sm:col-span-2">
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama, username, atau email..."
                        icon="magnifying-glass" size="sm" />
                </div>
                <flux:select wire:model.live="filterRole" size="sm">
                    <flux:select.option value="">Semua Role</flux:select.option>
                    @foreach ($roles as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="filterStatus" size="sm">
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
                <x-molecules.table-row>
                    <x-atoms.table-cell nowrap>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center size-9 shrink-0 rounded-full bg-primary-100 dark:bg-primary-900">
                                <span class="text-sm font-medium text-primary-700 dark:text-primary-300">
                                    {{ $user->initials() }}
                                </span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                    {{ $user->name }}
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                    {{ $user->email }}
                                </div>
                            </div>
                        </div>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs">
                        {{ $user->username }}
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <flux:badge :color="$user->role === 'admin' ? 'amber' : 'zinc'" size="sm">
                            {{ $roles[$user->role] ?? $user->role }}
                        </flux:badge>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <x-atoms.button variant="ghost" size="xs" wire:click="toggleStatus('{{ $user->id }}')"
                            :disabled="$user->id === auth()->id()" class="disabled:cursor-not-allowed disabled:opacity-50">
                            @if ($user->is_active)
                                <flux:badge color="green" size="sm">Aktif</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">Nonaktif</flux:badge>
                            @endif
                        </x-atoms.button>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="text-xs text-zinc-500 dark:text-primary-dark-400">
                        {{ $user->last_login_at?->diffForHumans() ?? '—' }}
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="right" action>
                        @if ($user->role === 'admin' && $user->id === auth()->id())
                            <span class="text-xs text-zinc-400">—</span>
                        @else
                            <div class="flex items-center justify-end gap-1">
                                <x-atoms.button variant="ghost" size="xs" icon="pencil-square"
                                    tooltip="Edit Pengguna" wire:click="openEditModal('{{ $user->id }}')" />
                                <x-atoms.button variant="ghost" size="xs" icon="key" tooltip="Reset Password"
                                    wire:click="openResetPasswordModal('{{ $user->id }}')" />
                                <x-atoms.button variant="ghost" size="xs" icon="trash" tooltip="Hapus Pengguna"
                                    wire:click="confirmDelete('{{ $user->id }}')" :disabled="$user->id === auth()->id()" />
                            </div>
                        @endif
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="6" align="center" class="py-12">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="users" class="size-10 text-zinc-300 dark:text-primary-dark-600" />
                            <p class="text-sm text-zinc-500 dark:text-primary-dark-400">Tidak ada pengguna ditemukan</p>
                        </div>
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        @if ($users->hasPages())
            <x-slot:footer>
                <div class="px-5 py-3">
                    {{ $users->links() }}
                </div>
            </x-slot:footer>
        @endif
    </x-organisms.data-panel>

    {{-- Create/Edit Modal --}}
    <x-organisms.modal wire:model="showModal" maxWidth="lg" title="">
        <div class="space-y-6">
            <flux:heading size="lg">
                {{ $isEditing ? 'Edit Pengguna' : 'Tambah Pengguna' }}
            </flux:heading>

            <form wire:submit="save" class="space-y-4">
                <flux:field>
                    <flux:label>Username</flux:label>
                    <flux:input wire:model="username" placeholder="Masukkan username" />
                    @error('username')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Nama Lengkap</flux:label>
                    <flux:input wire:model="name" placeholder="Masukkan nama lengkap" />
                    @error('name')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input type="email" wire:model="email" placeholder="Masukkan email" />
                    @error('email')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model="is_active">
                        <flux:select.option value="1">Aktif</flux:select.option>
                        <flux:select.option value="0">Nonaktif</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>
                        Password
                        @if ($isEditing)
                            <span class="text-xs text-zinc-500">(Kosongkan jika tidak ingin mengubah)</span>
                        @endif
                    </flux:label>
                    <flux:input type="password" wire:model="password" placeholder="Masukkan password" />
                    @error('password')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Konfirmasi Password</flux:label>
                    <flux:input type="password" wire:model="password_confirmation" placeholder="Konfirmasi password" />
                </flux:field>

                
        <x-slot:footer>
            <div class="flex justify-end gap-3 pt-4">
                    <x-atoms.button variant="ghost" wire:click="$set('showModal', false)">
                        Batal
                    </x-atoms.button>
                    <x-atoms.button type="submit" variant="primary">
                        {{ $isEditing ? 'Simpan Perubahan' : 'Tambah Pengguna' }}
                    </x-atoms.button>
                </div>
            </form>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Delete Confirmation Modal --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="md" title="">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="exclamation-triangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Pengguna</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        Tindakan ini tidak dapat dibatalkan.
                    </p>
                </div>
            </div>

            <p class="text-zinc-700 dark:text-primary-dark-300">
                Apakah Anda yakin ingin menghapus pengguna ini? Semua data terkait pengguna akan dihapus secara
                permanen.
            </p>

            
        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showDeleteModal', false)">
                    Batal
                </x-atoms.button>
                <x-atoms.button variant="danger" wire:click="delete">
                    Hapus Pengguna
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Reset Password Modal --}}
    <x-organisms.modal wire:model="showResetPasswordModal" maxWidth="md" title="">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-900/30">
                    <flux:icon name="key" class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <flux:heading size="lg">Reset Password</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        {{ $resetPasswordUserName }}
                    </p>
                </div>
            </div>

            <form wire:submit="resetPassword" class="space-y-4">
                <flux:field>
                    <flux:label>Password Baru</flux:label>
                    <flux:input type="password" wire:model="newPassword" placeholder="Minimal 8 karakter" />
                    @error('newPassword')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Konfirmasi Password Baru</flux:label>
                    <flux:input type="password" wire:model="newPassword_confirmation"
                        placeholder="Ulangi password baru" />
                </flux:field>

                
        <x-slot:footer>
            <div class="flex justify-end gap-3 pt-2">
                    <x-atoms.button variant="ghost" wire:click="$set('showResetPasswordModal', false)">
                        Batal
                    </x-atoms.button>
                    <x-atoms.button type="submit" variant="primary">
                        Reset Password
                    </x-atoms.button>
                </div>
            </form>
        </x-slot:footer>
    </div>
    </x-organisms.modal>
</div>
