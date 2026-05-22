<?php

use App\Models\User;
use App\Models\ActivityLog;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Hash;

new #[Layout('layouts::app')] #[Title('Profil')] class extends Component {
    use WithPagination;

    public string $name = '';
    public string $email = '';

    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function updateProfile(): void
    {
        $user = auth()->user();

        $this->validate(
            [
                'name' => ['required', 'string', 'min:3', 'max:255'],
                'email' => ['required', 'email', 'max:255', "unique:users,email,{$user->id}"],
            ],
            [
                'name.required' => 'Nama harus diisi',
                'name.min' => 'Nama minimal 3 karakter',
                'email.required' => 'Email harus diisi',
                'email.email' => 'Format email tidak valid',
                'email.unique' => 'Email sudah digunakan',
            ],
        );

        $user->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        ActivityLog::log(type: 'profile_updated', subject: 'Memperbarui profil');

        $this->toastSuccess('Profil berhasil diperbarui');
    }

    public function updatePassword(): void
    {
        $this->validate(
            [
                'current_password' => ['required', 'string'],
                'new_password' => ['required', 'string', 'min:8', 'confirmed'],
            ],
            [
                'current_password.required' => 'Password saat ini harus diisi',
                'new_password.required' => 'Password baru harus diisi',
                'new_password.min' => 'Password baru minimal 8 karakter',
                'new_password.confirmed' => 'Konfirmasi password baru tidak cocok',
            ],
        );

        $user = auth()->user();

        if (!Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Password saat ini salah');
            return;
        }

        $user->update([
            'password' => $this->new_password,
        ]);

        ActivityLog::log(type: 'password_changed', subject: 'Mengganti password');

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        $this->toastSuccess('Password berhasil diperbarui');
    }

    #[Computed]
    public function activities()
    {
        return ActivityLog::forUser(auth()->id())
            ->latest()
            ->paginate(10);
    }
};
?>

<div>
    <x-ui.page-header title="Profil" subtitle="Kelola informasi akun Anda" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Kolom Kiri: Info User --}}
        <div class="space-y-6 lg:col-span-1">
            <div
                class="overflow-hidden bg-white border rounded-xl dark:bg-primary-dark-800 border-zinc-200 dark:border-primary-dark-700">
                <div
                    class="p-6 text-center bg-gradient-to-br from-primary-500 to-primary-700 dark:from-primary-700 dark:to-primary-900">
                    <div
                        class="inline-flex items-center justify-center w-20 h-20 mx-auto text-2xl font-bold text-white rounded-full bg-white/20 ring-4 ring-white/30">
                        {{ auth()->user()->initials() }}
                    </div>
                    <h2 class="mt-3 text-lg font-semibold text-white">{{ auth()->user()->name }}</h2>
                    <p class="text-sm text-primary-100">{{ '@' . auth()->user()->username }}</p>
                </div>

                <div class="p-5 space-y-3">
                    <div class="flex items-center gap-3 text-sm">
                        <flux:icon name="envelope" class="w-4 h-4 text-zinc-400" />
                        <span class="text-zinc-600 dark:text-primary-dark-400">{{ auth()->user()->email }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <flux:icon name="shield-check" class="w-4 h-4 text-zinc-400" />
                        <flux:badge :color="auth()->user()->role === 'admin' ? 'amber' : 'zinc'" size="sm">
                            {{ User::getRoles()[auth()->user()->role] ?? auth()->user()->role }}
                        </flux:badge>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <flux:icon name="check-circle" class="w-4 h-4 text-zinc-400" />
                        <flux:badge :color="auth()->user()->is_active ? 'green' : 'red'" size="sm">
                            {{ auth()->user()->is_active ? 'Aktif' : 'Nonaktif' }}
                        </flux:badge>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <flux:icon name="clock" class="w-4 h-4 text-zinc-400" />
                        <span class="text-zinc-600 dark:text-primary-dark-400">
                            Login terakhir: {{ auth()->user()->last_login_at?->diffForHumans() ?? '-' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <flux:icon name="calendar" class="w-4 h-4 text-zinc-400" />
                        <span class="text-zinc-600 dark:text-primary-dark-400">
                            Bergabung: {{ auth()->user()->created_at->format('d M Y') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kolom Kanan: Form & Aktivitas --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Form Edit Profil --}}
            <x-ui.card title="Informasi Profil">
                <form wire:submit="updateProfile" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Username</flux:label>
                            <flux:input value="{{ auth()->user()->username }}" disabled />
                            <flux:description>Username tidak dapat diubah</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>Nama Lengkap</flux:label>
                            <flux:input wire:model="name" placeholder="Masukkan nama lengkap" />
                            @error('name')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>

                        <flux:field class="sm:col-span-2">
                            <flux:label>Email</flux:label>
                            <flux:input type="email" wire:model="email" placeholder="Masukkan email" />
                            @error('email')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>
                    </div>

                    <div class="flex justify-end pt-2">
                        <x-atoms.button type="submit" variant="primary">
                            Simpan Perubahan
                        </x-atoms.button>
                    </div>
                </form>
            </x-ui.card>

            {{-- Form Ganti Password --}}
            <x-ui.card title="Ganti Password">
                <form wire:submit="updatePassword" class="space-y-4">
                    <flux:field>
                        <flux:label>Password Saat Ini</flux:label>
                        <flux:input type="password" wire:model="current_password"
                            placeholder="Masukkan password saat ini" />
                        @error('current_password')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Password Baru</flux:label>
                            <flux:input type="password" wire:model="new_password" placeholder="Minimal 8 karakter" />
                            @error('new_password')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Konfirmasi Password Baru</flux:label>
                            <flux:input type="password" wire:model="new_password_confirmation"
                                placeholder="Ulangi password baru" />
                        </flux:field>
                    </div>

                    <div class="flex justify-end pt-2">
                        <x-atoms.button type="submit" variant="primary">
                            Perbarui Password
                        </x-atoms.button>
                    </div>
                </form>
            </x-ui.card>

            {{-- Riwayat Aktivitas --}}
            <x-ui.card title="Riwayat Aktivitas">
                @php
                    $colorMap = [
                        'blue' => [
                            'bg' => 'bg-blue-100 dark:bg-blue-900/30',
                            'text' => 'text-blue-600 dark:text-blue-400',
                        ],
                        'purple' => [
                            'bg' => 'bg-purple-100 dark:bg-purple-900/30',
                            'text' => 'text-purple-600 dark:text-purple-400',
                        ],
                        'green' => [
                            'bg' => 'bg-green-100 dark:bg-green-900/30',
                            'text' => 'text-green-600 dark:text-green-400',
                        ],
                        'cyan' => [
                            'bg' => 'bg-cyan-100 dark:bg-cyan-900/30',
                            'text' => 'text-cyan-600 dark:text-cyan-400',
                        ],
                        'amber' => [
                            'bg' => 'bg-amber-100 dark:bg-amber-900/30',
                            'text' => 'text-amber-600 dark:text-amber-400',
                        ],
                        'red' => ['bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-600 dark:text-red-400'],
                        'indigo' => [
                            'bg' => 'bg-indigo-100 dark:bg-indigo-900/30',
                            'text' => 'text-indigo-600 dark:text-indigo-400',
                        ],
                        'zinc' => [
                            'bg' => 'bg-zinc-100 dark:bg-primary-dark-700',
                            'text' => 'text-zinc-500 dark:text-primary-dark-400',
                        ],
                    ];
                @endphp

                <div class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                    @forelse($this->activities as $activity)
                        @php $colors = $colorMap[$activity->color] ?? $colorMap['zinc']; @endphp
                        <div class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="flex-shrink-0 p-1.5 mt-0.5 rounded-full {{ $colors['bg'] }}">
                                <flux:icon :name="$activity->icon" class="w-4 h-4 {{ $colors['text'] }}" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-zinc-900 dark:text-primary-dark-100">
                                    {{ $activity->subject }}
                                </p>
                                @if ($activity->type === 'user_login' && $activity->properties)
                                    <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">
                                        IP: {{ $activity->properties['ip_address'] ?? '-' }}
                                    </p>
                                @endif
                                <p class="mt-0.5 text-xs text-zinc-400 dark:text-primary-dark-500">
                                    {{ $activity->created_at->format('d M Y H:i') }}
                                    <span class="text-zinc-300 dark:text-primary-dark-600">&middot;</span>
                                    {{ $activity->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center">
                            <flux:icon name="clock"
                                class="w-8 h-8 mx-auto mb-2 text-zinc-300 dark:text-primary-dark-600" />
                            <p class="text-sm text-zinc-500 dark:text-primary-dark-400">Belum ada riwayat aktivitas</p>
                        </div>
                    @endforelse
                </div>

                @if ($this->activities->hasPages())
                    <x-slot:footer>
                        {{ $this->activities->links() }}
                    </x-slot:footer>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
