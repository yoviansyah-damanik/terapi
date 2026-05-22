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

new #[Layout('layouts::app')] #[Title('Manajemen API')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'users';

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
    public string $is_active = '1';
    public array $userScopes = [];

    // Token management
    public bool $showTokensModal = false;
    public ?string $tokenUserId = null;
    public string $tokenUserName = '';
    public string $tokenName = '';
    public ?string $newlyCreatedToken = null;
    public string $tokenExpiry = '';

    public function updatedTab(): void
    {
        $this->resetPage();
        $this->search = '';
        $this->filterStatus = '';
    }

    public function rules()
    {
        $validKeys = array_column(ApiUser::SCOPES, 'key');

        $rules = [
            'name' => 'required|string|min:3|max:100',
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('api_users')->ignore($this->userId)],
            'userScopes' => 'required|array|min:1',
            'userScopes.*' => $validKeys ? Rule::in($validKeys) : 'string',
            'is_active' => 'in:0,1',
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
            'userScopes.required' => 'Pilih minimal satu scope',
            'userScopes.min' => 'Pilih minimal satu scope',
            'userScopes.*.in' => 'Salah satu scope yang dipilih tidak valid',
            'password.required' => 'Password harus diisi',
            'password.confirmed' => 'Konfirmasi password tidak cocok',
            'password.min' => 'Password minimal 8 karakter',
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    // ── User CRUD ─────────────────────────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showUserModal = true;
    }

    public function openEditModal(string $id): void
    {
        $validKeys = array_column(ApiUser::SCOPES, 'key');

        $user = ApiUser::findOrFail($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->username = $user->username;
        // Filter scope usang yang tidak ada di daftar saat ini
        $this->userScopes = array_values(array_filter($user->scopes ?? [], fn($s) => in_array($s, $validKeys)));
        $this->is_active = $user->is_active ? '1' : '0';
        $this->password = '';
        $this->password_confirmation = '';
        $this->isEditing = true;
        $this->showUserModal = true;
    }

    public function save(): void
    {
        $this->validate();

        try {
            $data = [
                'name' => $this->name,
                'username' => $this->username,
                'scopes' => $this->userScopes,
                'is_active' => $this->is_active === '1',
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

    public function confirmDelete(string $id): void
    {
        $this->userId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
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

    public function toggleStatus(string $id): void
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

    public function resetForm(): void
    {
        $this->userId = null;
        $this->name = '';
        $this->username = '';
        $this->userScopes = [];
        $this->password = '';
        $this->password_confirmation = '';
        $this->is_active = '1';
        $this->resetValidation();
    }

    // ── Token management ──────────────────────────────────────────────────────

    public function openTokensModal(string $id): void
    {
        $user = ApiUser::findOrFail($id);
        $this->tokenUserId = $user->id;
        $this->tokenUserName = $user->name;
        $this->tokenName = '';
        $this->tokenExpiry = '';
        $this->newlyCreatedToken = null;
        $this->showTokensModal = true;
    }

    public function createToken(): void
    {
        $this->validate(['tokenName' => 'required|string|max:100'], ['tokenName.required' => 'Nama token harus diisi']);

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

    public function deleteToken(string $tokenId): void
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

    // ── Data ──────────────────────────────────────────────────────────────────

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

        // Buat map key => meta dari SCOPES constant
        $scopeMap = collect(ApiUser::SCOPES)->keyBy('key')->all();

        return [
            'apiUsers' => $query->paginate(10),
            'tokens' => $tokens,
            'scopeMap' => $scopeMap,
            'allScopes' => ApiUser::SCOPES,
        ];
    }
};
?>

<div>
    {{-- Header --}}
    <x-ui.page-header title="Manajemen API" subtitle="Kelola user, token, dan scope API eksternal">
        <x-slot:actions>
            @if ($tab === 'users')
                <x-atoms.button variant="primary" icon="plus" wire:click="openCreateModal">
                    Tambah User API
                </x-atoms.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Tabs --}}
    <x-molecules.tabs>
        <x-atoms.tab-item wire:click="$set('tab', 'users')" :active="$tab === 'users'">
            User API
        </x-atoms.tab-item>
        <x-atoms.tab-item wire:click="$set('tab', 'scopes')" :active="$tab === 'scopes'">
            Scope
        </x-atoms.tab-item>
    </x-molecules.tabs>

    {{-- Tab: User API --}}
    @if ($tab === 'users')
        <x-organisms.data-panel title="Daftar User API" subtitle="Kelola akses dan token untuk setiap user API">
            <x-slot:filter>
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
            </x-slot:filter>

            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>User API</x-atoms.table-heading>
                    <x-atoms.table-heading>Username</x-atoms.table-heading>
                    <x-atoms.table-heading>Scope</x-atoms.table-heading>
                    <x-atoms.table-heading>Status</x-atoms.table-heading>
                    <x-atoms.table-heading>Token</x-atoms.table-heading>
                    <x-atoms.table-heading>Login Terakhir</x-atoms.table-heading>
                    <x-atoms.table-heading align="right">Aksi</x-atoms.table-heading>
                </x-slot:headings>

                @forelse ($apiUsers as $user)
                    <x-molecules.table-row wire:key="{{ $user->id }}">
                        <x-atoms.table-cell :nowrap="true">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex items-center justify-center flex-shrink-0 w-9 h-9 rounded-full bg-blue-100 dark:bg-blue-900/30">
                                    <flux:icon name="cpu-chip" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <span
                                    class="font-medium text-zinc-900 dark:text-primary-dark-100">{{ $user->name }}</span>
                            </div>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :nowrap="true">
                            <code
                                class="px-2 py-1 text-xs rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-700 dark:text-primary-dark-300">{{ $user->username }}</code>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <div class="flex flex-wrap gap-1">
                                @forelse ($user->scopes ?? [] as $scope)
                                    @php $meta = $scopeMap[$scope] ?? null; @endphp
                                    <flux:badge :color="$meta['color'] ?? 'zinc'" size="sm">
                                        {{ $meta['label'] ?? $scope }}</flux:badge>
                                @empty
                                    <span class="text-xs text-zinc-400 dark:text-primary-dark-500">—</span>
                                @endforelse
                            </div>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :nowrap="true">
                            <x-atoms.button :color="$user->is_active ? 'green' : 'red'" size="sm"
                                wire:click="toggleStatus('{{ $user->id }}')" class="focus:outline-none">
                                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                            </x-atoms.button>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :nowrap="true">
                            <x-atoms.button wire:click="openTokensModal('{{ $user->id }}')" icon="key"
                                size="sm"
                                class="inline-flex items-center gap-1 text-primary-600 dark:text-primary-400">
                                {{ $user->tokens_count }} Token
                            </x-atoms.button>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :nowrap="true" class="text-zinc-500 dark:text-primary-dark-400">
                            {{ $user->last_login_at?->diffForHumans() ?? '-' }}
                        </x-atoms.table-cell>
                        <x-atoms.table-cell align="right" :nowrap="true" action>
                            <div class="flex items-center justify-end gap-1">
                                <x-atoms.button variant="ghost" size="sm" icon="pencil"
                                    wire:click="openEditModal('{{ $user->id }}')" title="Edit" />
                                <x-atoms.button variant="ghost" size="sm" icon="key"
                                    wire:click="openTokensModal('{{ $user->id }}')" title="Kelola Token" />
                                <x-atoms.button variant="ghost" size="sm" icon="trash"
                                    wire:click="confirmDelete('{{ $user->id }}')"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400" title="Hapus" />
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="7" class="py-12 text-center">
                            <div class="flex flex-col items-center">
                                <flux:icon name="key" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                                <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Belum ada user API</p>
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse

                @if ($apiUsers->hasPages())
                    <x-slot:footer>
                        <div class="px-6 py-4">
                            {{ $apiUsers->links() }}
                        </div>
                    </x-slot:footer>
                @endif
            </x-organisms.table>
        </x-organisms.data-panel>
    @endif

    {{-- Tab: Scope --}}
    @if ($tab === 'scopes')
        <x-organisms.data-panel title="Daftar Scope API" subtitle="Scope didefinisikan di ApiUser::SCOPES">
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Key</x-atoms.table-heading>
                    <x-atoms.table-heading>Nama & Keterangan</x-atoms.table-heading>
                    <x-atoms.table-heading>Badge</x-atoms.table-heading>
                </x-slot:headings>

                @foreach ($allScopes as $scope)
                    <x-molecules.table-row wire:key="scope-{{ $scope['key'] }}">
                        <x-atoms.table-cell :nowrap="true">
                            <code
                                class="px-2 py-1 text-xs rounded bg-zinc-100 dark:bg-primary-dark-700 text-zinc-700 dark:text-primary-dark-300">{{ $scope['key'] }}</code>
                        </x-atoms.table-cell>
                        <x-atoms.table-cell>
                            <span
                                class="font-medium text-zinc-900 dark:text-primary-dark-100">{{ $scope['label'] }}</span>
                            @if (!empty($scope['description']))
                                <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">
                                    {{ $scope['description'] }}</p>
                            @endif
                        </x-atoms.table-cell>
                        <x-atoms.table-cell :nowrap="true">
                            <flux:badge :color="$scope['color']" size="sm">{{ $scope['label'] }}</flux:badge>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforeach

            </x-organisms.table>
            <x-slot:footer>
                Scope didefinisikan di <code class="font-mono">ApiUser::SCOPES</code>. Untuk menambah atau
                mengubah scope, edit konstanta tersebut.
            </x-slot:footer>
        </x-organisms.data-panel>
    @endif

    {{-- Modal: Create/Edit User --}}
    <x-organisms.modal wire:model="showUserModal" maxWidth="2xl" :title="$isEditing ? 'Edit User API' : 'Tambah User API'">
        <form id="userForm" wire:submit="save" class="space-y-4">
            <flux:field>
                <flux:label>Nama</flux:label>
                <flux:input wire:model="name" placeholder="Nama aplikasi / sistem" />
                <flux:error name='name' />
            </flux:field>

            <flux:field>
                <flux:label>Username</flux:label>
                <flux:input wire:model="username" placeholder="Username untuk autentikasi" />
                <flux:error name='username' />
            </flux:field>

            <flux:field>
                <flux:label>Scope <span class="text-red-500">*</span></flux:label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                    @foreach ($allScopes as $scope)
                        <x-molecules.selectable-card model="userScopes" value="{{ $scope['key'] }}"
                            label="{{ $scope['label'] }}" description="{{ $scope['description'] }}"
                            icon="{{ $scope['icon'] }}" />
                    @endforeach
                </div>
                @php
                    $errors = $this->getErrorBag();
                    $scopeError =
                        $errors->first('userScopes') ??
                        collect($errors->keys())
                            ->filter(fn($k) => str_starts_with($k, 'userScopes.'))
                            ->map(fn($k) => $errors->first($k))
                            ->first();
                @endphp
                @if ($scopeError)
                    <flux:error>{{ $scopeError }}</flux:error>
                @endif
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
                <flux:error name='password' />
            </flux:field>

            <flux:field>
                <flux:label>Konfirmasi Password</flux:label>
                <flux:input type="password" wire:model="password_confirmation" placeholder="Konfirmasi password" />
                <flux:error name='password_confirmation' />
            </flux:field>

        </form>

        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showUserModal', false)">Batal</x-atoms.button>
                <x-atoms.button type="submit" form="userForm" variant="primary">
                    {{ $isEditing ? 'Simpan Perubahan' : 'Tambah User' }}
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Delete User --}}
    <x-organisms.modal wire:model="showDeleteModal" maxWidth="md" title="Hapus User API"
        description="Tindakan ini tidak dapat dibatalkan.">
        <div class="py-4">
            <p class="text-zinc-700 dark:text-primary-dark-300">
                Apakah Anda yakin ingin menghapus user API ini? Semua token terkait juga akan dihapus secara permanen.
            </p>
        </div>
        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="ghost" wire:click="$set('showDeleteModal', false)">Batal</x-atoms.button>
                <x-atoms.button variant="danger" wire:click="delete">Hapus User API</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal: Tokens --}}
    <x-organisms.modal wire:model="showTokensModal" maxWidth="3xl" title="Token API — {{ $tokenUserName }}">
        <div class="space-y-6">
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
                                <x-atoms.button size="sm" icon="clipboard-document" variant="secondary"
                                    x-on:click="navigator.clipboard.writeText('{{ $newlyCreatedToken }}').then(() => $flux.toast('Token disalin ke clipboard'))"
                                    title="Salin" />
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                <h4 class="mb-3 text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Buat Token Baru</h4>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-1">
                        <flux:input wire:model="tokenName" placeholder="Nama token" size="sm" />
                        <flux:error name='tokenName' />
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

            <div class="space-y-2">
                <h4 class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Token Aktif</h4>

                <div class="pr-1 space-y-1">
                    @forelse ($tokens as $token)
                        <div wire:key="token-{{ $token->id }}"
                            class="group flex items-center justify-between p-4 bg-transparent hover:bg-zinc-50 dark:hover:bg-primary-dark-800/50 transition-colors border border-transparent hover:border-zinc-100 dark:hover:border-primary-dark-700/50 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-zinc-900 dark:text-primary-dark-100">
                                        {{ $token->name ?? 'Token tanpa nama' }}
                                    </span>
                                    @if ($token->isExpired())
                                        <flux:badge color="red" size="sm">Kedaluwarsa</flux:badge>
                                    @endif
                                </div>
                                <div
                                    class="flex items-center gap-3 mt-1 text-xs text-zinc-500 dark:text-primary-dark-400">
                                    <span>Dibuat: {{ $token->created_at->format('d/m/Y H:i') }}</span>
                                    @if ($token->expires_at)
                                        <span>Kedaluwarsa: {{ $token->expires_at->format('d/m/Y H:i') }}</span>
                                    @else
                                        <span>Tidak kedaluwarsa</span>
                                    @endif
                                    @if ($token->last_used_at)
                                        <span>Terakhir digunakan: {{ $token->last_used_at->diffForHumans() }}</span>
                                    @endif
                                </div>
                            </div>
                            <x-atoms.button variant="ghost" size="sm" icon="trash"
                                wire:click="deleteToken('{{ $token->id }}')"
                                class="opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity text-red-600 hover:text-red-700 dark:text-red-400" />
                        </div>
                    @empty
                        <div
                            class="py-8 text-center rounded-lg border border-dashed border-zinc-200 dark:border-primary-dark-700">
                            <flux:icon name="key"
                                class="w-8 h-8 mx-auto text-zinc-300 dark:text-primary-dark-600" />
                            <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Belum ada token</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-3">
                <x-atoms.button variant="secondary" wire:click="$set('showTokensModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
