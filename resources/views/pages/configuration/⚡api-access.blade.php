<?php

use App\Models\Api\ApiUser;
use App\Models\Api\ApiToken;
use App\Models\ActivityLog;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

new #[Layout('layouts::app')] #[Title('Akses API')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterStatus = '';

    // User form
    public bool $showUserModal = false;
    public bool $showDeleteModal = false;
    public bool $isEditing = false;
    public ?string $userId = null;
    public string $name = '';
    public string $username = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $is_active = true;
    public array $userScopes = [];

    // Token management
    public bool $showTokensModal = false;
    public ?string $tokenUserId = null;
    public string $tokenUserName = '';
    public string $tokenName = '';
    public ?string $newlyCreatedToken = null;

    // Token expiry
    public string $tokenExpiry = '';

    public function rules()
    {
        $rules = [
            'name' => 'required|string|min:3|max:100',
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('api_users')->ignore($this->userId)],
            'userScopes' => 'required|array|min:1',
            'userScopes.*' => 'in:' . implode(',', array_keys(ApiUser::scopesMap())),
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
            'name.required' => 'Nama harus diisi',
            'username.required' => 'Username harus diisi',
            'username.unique' => 'Username sudah digunakan',
            'username.alpha_dash' => 'Username hanya boleh berisi huruf, angka, dash, dan underscore',
            'userScopes.required' => 'Pilih minimal satu kategori akses',
            'userScopes.min' => 'Pilih minimal satu kategori akses',
            'password.required' => 'Password harus diisi',
            'password.confirmed' => 'Konfirmasi password tidak cocok',
            'password.min' => 'Password minimal 8 karakter',
        ];
    }

    public function updatedSearch()
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
        $this->showUserModal = true;
    }

    public function openEditModal(string $id)
    {
        $user = ApiUser::findOrFail($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->userScopes = $user->scopes ?? [];
        $this->is_active = $user->is_active;
        $this->password = '';
        $this->password_confirmation = '';
        $this->isEditing = true;
        $this->showUserModal = true;
    }

    public function save()
    {
        $this->validate();

        try {
            $data = [
                'name' => $this->name,
                'username' => $this->username,
                'scopes' => $this->userScopes,
                'is_active' => $this->is_active,
            ];

            if ($this->password) {
                $data['password'] = $this->password;
            }

            if ($this->isEditing) {
                $user = ApiUser::findOrFail($this->userId);
                $user->update($data);

                ActivityLog::log(type: 'api_user_updated', subject: "Mengubah data user API: {$user->name}", properties: ['api_user_id' => $user->id]);

                $this->toastSuccess('User API berhasil diperbarui');
            } else {
                $user = ApiUser::create($data);

                ActivityLog::log(type: 'api_user_created', subject: "Membuat user API baru: {$user->name}", properties: ['api_user_id' => $user->id]);

                $this->toastSuccess('User API berhasil ditambahkan');
            }

            $this->showUserModal = false;
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
            $user = ApiUser::findOrFail($this->userId);
            $userName = $user->name;
            $user->delete();

            ActivityLog::log(type: 'api_user_deleted', subject: "Menghapus user API: {$userName}");

            $this->toastSuccess('User API berhasil dihapus');
            $this->showDeleteModal = false;
            $this->userId = null;
        } catch (\Exception $e) {
            $this->toastError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function toggleStatus(string $id)
    {
        try {
            $user = ApiUser::findOrFail($id);
            $user->update(['is_active' => !$user->is_active]);

            $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
            ActivityLog::log(type: 'api_user_status_changed', subject: "Status user API {$user->name} {$status}", properties: ['api_user_id' => $user->id, 'is_active' => $user->is_active]);

            $this->toastSuccess("User API berhasil {$status}");
        } catch (\Exception $e) {
            $this->toastError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function openTokensModal(string $id)
    {
        $user = ApiUser::findOrFail($id);
        $this->tokenUserId = $user->id;
        $this->tokenUserName = $user->name;
        $this->tokenName = '';
        $this->tokenExpiry = '';
        $this->newlyCreatedToken = null;
        $this->showTokensModal = true;
    }

    public function createToken()
    {
        $this->validate(
            [
                'tokenName' => 'required|string|max:100',
            ],
            [
                'tokenName.required' => 'Nama token harus diisi',
            ],
        );

        try {
            $plainToken = Str::random(64);
            $hashedToken = hash('sha256', $plainToken);

            $expiresAt = match ($this->tokenExpiry) {
                '24h' => now()->addHours(24),
                '7d' => now()->addDays(7),
                '30d' => now()->addDays(30),
                '90d' => now()->addDays(90),
                '1y' => now()->addYear(),
                default => null,
            };

            ApiToken::create([
                'api_user_id' => $this->tokenUserId,
                'token' => $hashedToken,
                'name' => $this->tokenName,
                'expires_at' => $expiresAt,
            ]);

            $this->newlyCreatedToken = $plainToken;
            $this->tokenName = '';
            $this->tokenExpiry = '';

            ActivityLog::log(type: 'api_token_created', subject: "Membuat token API untuk: {$this->tokenUserName}", properties: ['api_user_id' => $this->tokenUserId]);

            $this->toastSuccess('Token berhasil dibuat');
        } catch (\Exception $e) {
            $this->toastError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function deleteToken(string $tokenId)
    {
        try {
            $token = ApiToken::findOrFail($tokenId);
            $token->delete();

            ActivityLog::log(type: 'api_token_deleted', subject: "Menghapus token API untuk: {$this->tokenUserName}", properties: ['api_user_id' => $this->tokenUserId]);

            $this->toastSuccess('Token berhasil dihapus');
        } catch (\Exception $e) {
            $this->toastError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function resetForm()
    {
        $this->userId = null;
        $this->name = '';
        $this->username = '';
        $this->userScopes = [];
        $this->password = '';
        $this->password_confirmation = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function with(): array
    {
        $query = ApiUser::query()
            ->withCount('tokens')
            ->when(
                $this->search,
                fn($q) => $q->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")->orWhere('username', 'like', "%{$this->search}%");
                }),
            )
            ->when($this->filterStatus !== '', fn($q) => $q->where('is_active', $this->filterStatus === '1'))
            ->orderBy('name');

        $tokens = [];
        if ($this->tokenUserId) {
            $tokens = ApiToken::where('api_user_id', $this->tokenUserId)->orderByDesc('created_at')->get();
        }

        return [
            'apiUsers' => $query->paginate(10),
            'tokens' => $tokens,
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Akses API" subtitle="Kelola user dan token untuk akses API eksternal">
        <x-slot:actions>
            <x-atoms.button variant="primary" icon="plus" wire:click="openCreateModal">
                Tambah User API
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Filters --}}
    <div class="p-4 mb-6 bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama atau username..."
                    icon="magnifying-glass" />
            </div>
            <flux:select wire:model.live="filterStatus">
                <flux:select.option value="">Semua Status</flux:select.option>
                <flux:select.option value="1">Aktif</flux:select.option>
                <flux:select.option value="0">Nonaktif</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-primary-dark-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            User API
                        </th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Username
                        </th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Kategori Akses
                        </th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Status
                        </th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Token
                        </th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-left uppercase text-zinc-500 dark:text-primary-dark-400">
                            Login Terakhir
                        </th>
                        <th
                            class="px-6 py-3 text-xs font-medium tracking-wider text-right uppercase text-zinc-500 dark:text-primary-dark-400">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-zinc-200 dark:bg-primary-dark-800 dark:divide-primary-dark-700">
                    @forelse ($apiUsers as $user)
                        <tr :key="$user->id" class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div
                                        class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30">
                                        <flux:icon name="cpu-chip" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                            {{ $user->name }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <code
                                    class="px-2 py-1 text-xs rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-700 dark:text-primary-dark-300">
                                    {{ $user->username }}
                                </code>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->scopes ?? [] as $scope)
                                        @php $scopeMeta = ApiUser::scopesMap()[$scope] ?? null; @endphp
                                        @if ($scopeMeta)
                                            <flux:badge :color="$scopeMeta['color']" size="sm">
                                                {{ $scopeMeta['label'] }}
                                            </flux:badge>
                                        @endif
                                    @empty
                                        <span class="text-xs text-zinc-400 dark:text-primary-dark-500">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-atoms.button wire:click="toggleStatus('{{ $user->id }}')"
                                    class="focus:outline-none">
                                    <flux:badge :color="$user->is_active ? 'green' : 'red'" size="sm">
                                        {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </flux:badge>
                                </x-atoms.button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-atoms.button wire:click="openTokensModal('{{ $user->id }}')"
                                    class="inline-flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:underline">
                                    <flux:icon name="key" class="w-4 h-4" />
                                    {{ $user->tokens_count }} token
                                </x-atoms.button>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap text-zinc-500 dark:text-primary-dark-400">
                                {{ $user->last_login_at?->diffForHumans() ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <x-atoms.button variant="ghost" size="sm" icon="pencil"
                                        wire:click="openEditModal('{{ $user->id }}')" title="Edit" />
                                    <x-atoms.button variant="ghost" size="sm" icon="key"
                                        wire:click="openTokensModal('{{ $user->id }}')" title="Kelola Token" />
                                    <x-atoms.button variant="ghost" size="sm" icon="trash"
                                        wire:click="confirmDelete('{{ $user->id }}')"
                                        class="text-red-600 hover:text-red-700 dark:text-red-400" title="Hapus" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="key"
                                        class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">
                                        Belum ada user API
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($apiUsers->hasPages())
            <div class="px-6 py-4 border-t border-zinc-200 dark:border-primary-dark-700">
                {{ $apiUsers->links() }}
            </div>
        @endif
    </div>

    {{-- Create/Edit User Modal --}}
    <x-organisms.modal wire:model="showUserModal" maxWidth="lg" title="">
        <div class="space-y-6">
            <flux:heading size="lg">
                {{ $isEditing ? 'Edit User API' : 'Tambah User API' }}
            </flux:heading>

            <form wire:submit="save" class="space-y-4">
                <flux:field>
                    <flux:label>Nama</flux:label>
                    <flux:input wire:model="name" placeholder="Nama aplikasi / sistem" />
                    @error('name')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Username</flux:label>
                    <flux:input wire:model="username" placeholder="Username untuk autentikasi" />
                    @error('username')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Kategori Akses <span class="text-red-500">*</span></flux:label>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach (ApiUser::scopesMap() as $scopeKey => $scope)
                            <label
                                class="flex items-center gap-2 px-3 py-2 text-sm border rounded-lg cursor-pointer transition-colors
                                {{ in_array($scopeKey, $userScopes)
                                    ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                                    : 'border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-primary-dark-800 text-zinc-600 dark:text-primary-dark-400 hover:border-zinc-300 dark:hover:border-primary-dark-600' }}">
                                <input type="checkbox" wire:model.live="userScopes" value="{{ $scopeKey }}"
                                    class="w-4 h-4 rounded accent-blue-600" />
                                <flux:icon name="{{ $scope['icon'] }}" class="w-4 h-4" />
                                <span>
                                    <span class="font-medium">{{ $scope['label'] }}</span>
                                    <span class="block text-xs opacity-70">{{ $scope['description'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('userScopes')
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
                    <flux:input type="password" wire:model="password_confirmation"
                        placeholder="Konfirmasi password" />
                </flux:field>

                
        <x-slot:footer>
            <div class="flex justify-end gap-3 pt-4">
                    <x-atoms.button variant="ghost" wire:click="$set('showUserModal', false)">
                        Batal
                    </x-atoms.button>
                    <x-atoms.button type="submit" variant="primary">
                        {{ $isEditing ? 'Simpan Perubahan' : 'Tambah User' }}
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
                    <flux:heading size="lg">Hapus User API</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-primary-dark-400">
                        Tindakan ini tidak dapat dibatalkan.
                    </p>
                </div>
            </div>

            <p class="text-zinc-700 dark:text-primary-dark-300">
                Apakah Anda yakin ingin menghapus user API ini? Semua token terkait juga akan dihapus secara permanen.
            </p>

            
        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showDeleteModal', false)">
                    Batal
                </x-atoms.button>
                <x-atoms.button variant="danger" wire:click="delete">
                    Hapus User API
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Tokens Management Modal --}}
    <x-organisms.modal wire:model="showTokensModal" maxWidth="2xl" title="">
        <div class="space-y-6">
            <flux:heading size="lg">
                Token API — {{ $tokenUserName }}
            </flux:heading>

            {{-- Token baru yang baru dibuat --}}
            @if ($newlyCreatedToken)
                <div
                    class="p-4 border rounded-lg bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800">
                    <div class="flex items-start gap-3">
                        <flux:icon name="check-circle"
                            class="flex-shrink-0 w-5 h-5 mt-0.5 text-emerald-600 dark:text-emerald-400" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-200">
                                Token berhasil dibuat! Salin token ini sekarang — tidak akan ditampilkan lagi.
                            </p>
                            <div class="flex items-center gap-2 mt-2">
                                <code
                                    class="flex-1 block p-2 text-xs break-all rounded bg-emerald-100 dark:bg-emerald-900/50 text-emerald-900 dark:text-emerald-100 font-mono">
                                    {{ $newlyCreatedToken }}
                                </code>
                                <x-atoms.button size="sm" icon="clipboard-document"
                                    x-on:click="navigator.clipboard.writeText('{{ $newlyCreatedToken }}').then(() => $flux.toast('Token disalin ke clipboard'))"
                                    title="Salin" />
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Form buat token baru --}}
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4 class="mb-3 text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Buat Token Baru</h4>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-1">
                        <flux:input wire:model="tokenName" placeholder="Nama token" size="sm" />
                        @error('tokenName')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <flux:select wire:model="tokenExpiry" size="sm">
                            <flux:select.option value="">Tidak kedaluwarsa</flux:select.option>
                            <flux:select.option value="24h">24 jam</flux:select.option>
                            <flux:select.option value="7d">7 hari</flux:select.option>
                            <flux:select.option value="30d">30 hari</flux:select.option>
                            <flux:select.option value="90d">90 hari</flux:select.option>
                            <flux:select.option value="1y">1 tahun</flux:select.option>
                        </flux:select>
                    </div>
                    <div>
                        <x-atoms.button variant="primary" size="sm" icon="plus" wire:click="createToken"
                            class="w-full">
                            Buat Token
                        </x-atoms.button>
                    </div>
                </div>
            </div>

            {{-- Daftar token --}}
            <div class="space-y-2">
                <h4 class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Token Aktif</h4>
                @forelse ($tokens as $token)
                    <div wire:key="token-{{ $token->id }}"
                        class="flex items-center justify-between p-3 rounded-lg bg-white dark:bg-primary-dark-800 border border-zinc-200 dark:border-primary-dark-700">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                    {{ $token->name ?? 'Token tanpa nama' }}
                                </span>
                                @if ($token->isExpired())
                                    <flux:badge color="red" size="sm">Kedaluwarsa</flux:badge>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 mt-1 text-xs text-zinc-500 dark:text-primary-dark-400">
                                <span>
                                    Dibuat: {{ $token->created_at->format('d/m/Y H:i') }}
                                </span>
                                @if ($token->expires_at)
                                    <span>
                                        Kedaluwarsa: {{ $token->expires_at->format('d/m/Y H:i') }}
                                    </span>
                                @else
                                    <span>Tidak kedaluwarsa</span>
                                @endif
                                @if ($token->last_used_at)
                                    <span>
                                        Terakhir digunakan: {{ $token->last_used_at->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <x-atoms.button variant="ghost" size="sm" icon="trash"
                            wire:click="deleteToken('{{ $token->id }}')"
                            wire:confirm="Yakin ingin menghapus token ini?"
                            class="text-red-600 hover:text-red-700 dark:text-red-400" />
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <flux:icon name="key" class="w-8 h-8 mx-auto text-zinc-300 dark:text-primary-dark-600" />
                        <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Belum ada token</p>
                    </div>
                @endforelse
            </div>

            
        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button variant="ghost" wire:click="$set('showTokensModal', false)">
                    Tutup
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>
</div>
