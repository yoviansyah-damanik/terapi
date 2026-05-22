<?php

use App\Models\SatuSehat\KycLog;
use App\Models\Mapping\EmployeeMap;
use App\Models\Simrs\Pegawai;
use App\Services\SatuSehat\Kyc\KycService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('KYC — Verifikasi Identitas Pasien')] class extends Component {
    // --- Generate URL Section ---
    public string $urlAgentNik = '';
    public bool $isGeneratingUrl = false;
    public ?string $generatedUrl = null;
    public ?string $urlError = null;

    // --- Challenge Code Section ---
    public string $codeAgentNik = '';
    public string $patientNik = '';
    public string $patientName = '';
    public bool $isGeneratingCode = false;
    public ?string $challengeCode = null;
    public ?string $challengeIhs = null;
    public ?string $challengeExpiry = null;
    public ?string $codeError = null;

    private array $employees = [];

    public function mount(): void
    {
        $this->loadEmployees();
    }

    private function loadEmployees(): void
    {
        $maps = EmployeeMap::orderBy('employee_id')->get()->keyBy('employee_id');

        if ($maps->isEmpty()) {
            $this->employees = [];
            return;
        }

        $niks = $maps->keys()->toArray();

        try {
            $pegawaiList = Pegawai::whereIn('nik', $niks)->where('stts_aktif', 'AKTIF')->orderBy('nama')->get()->keyBy('nik');
        } catch (\Exception) {
            $pegawaiList = collect();
        }

        $this->employees = $maps
            ->map(function ($map) use ($pegawaiList) {
                $pegawai = $pegawaiList->get($map->employee_id);
                return [
                    'nik' => $map->employee_id,
                    'name' => $pegawai?->nama ?? '(NIK: ' . $map->employee_id . ')',
                ];
            })
            ->values()
            ->toArray();
    }

    public function with(): array
    {
        $recentLogs = KycLog::latest()->limit(10)->get();
        return [
            'employees' => $this->employees,
            'recentLogs' => $recentLogs,
        ];
    }

    public function generateUrl(): void
    {
        $this->validate(
            [
                'urlAgentNik' => 'required|string',
            ],
            [
                'urlAgentNik.required' => 'Pilih petugas terlebih dahulu.',
            ],
        );

        $agentName = collect($this->employees)->firstWhere('nik', $this->urlAgentNik)['name'] ?? $this->urlAgentNik;

        $this->isGeneratingUrl = true;
        $this->generatedUrl = null;
        $this->urlError = null;

        try {
            $result = app(KycService::class)->generateUrl($agentName, $this->urlAgentNik);

            $url = $result['data']['url'] ?? null;

            KycLog::record([
                'agent_name' => $agentName,
                'agent_nik' => $this->urlAgentNik,
                'kyc_type' => 'generate_url',
                'kyc_url' => $url,
                'is_success' => true,
            ]);

            $this->generatedUrl = $url;
            $this->toastSuccess('URL KYC berhasil digenerate', 'Sukses');
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();

            KycLog::record([
                'agent_name' => $agentName,
                'agent_nik' => $this->urlAgentNik,
                'kyc_type' => 'generate_url',
                'is_success' => false,
                'error_message' => $errorMsg,
            ]);

            $this->urlError = $errorMsg;
            $this->toastError('Gagal generate URL KYC', 'Error');
        } finally {
            $this->isGeneratingUrl = false;
        }
    }

    public function generateCode(): void
    {
        $this->validate(
            [
                'codeAgentNik' => 'required|string',
                'patientNik' => 'required|digits:16',
                'patientName' => 'required|string|min:3',
            ],
            [
                'codeAgentNik.required' => 'Pilih petugas terlebih dahulu.',
                'patientNik.required' => 'NIK pasien wajib diisi.',
                'patientNik.digits' => 'NIK pasien harus 16 digit.',
                'patientName.required' => 'Nama pasien wajib diisi.',
                'patientName.min' => 'Nama pasien minimal 3 karakter.',
            ],
        );

        $agentName = collect($this->employees)->firstWhere('nik', $this->codeAgentNik)['name'] ?? $this->codeAgentNik;

        $this->isGeneratingCode = true;
        $this->challengeCode = null;
        $this->challengeIhs = null;
        $this->challengeExpiry = null;
        $this->codeError = null;

        try {
            $result = app(KycService::class)->challengeCode($this->codeAgentNik, $this->patientNik, $this->patientName);

            $code = $result['challenge_code'] ?? null;
            $ihs = $result['ihs_number'] ?? null;
            $expiry = $result['expired_at'] ?? null;

            KycLog::record([
                'agent_name' => $agentName,
                'agent_nik' => $this->codeAgentNik,
                'kyc_type' => 'challenge_code',
                'patient_nik' => $this->patientNik,
                'patient_name' => $this->patientName,
                'challenge_code' => $code,
                'ihs_number' => $ihs,
                'expires_at' => $expiry ? \Carbon\Carbon::parse($expiry) : null,
                'is_success' => true,
            ]);

            $this->challengeCode = $code;
            $this->challengeIhs = $ihs;
            $this->challengeExpiry = $expiry;
            $this->toastSuccess('Kode challenge berhasil digenerate', 'Sukses');
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();

            KycLog::record([
                'agent_name' => $agentName,
                'agent_nik' => $this->codeAgentNik,
                'kyc_type' => 'challenge_code',
                'patient_nik' => $this->patientNik,
                'patient_name' => $this->patientName,
                'is_success' => false,
                'error_message' => $errorMsg,
            ]);

            $this->codeError = $errorMsg;
            $this->toastError('Gagal generate kode challenge', 'Error');
        } finally {
            $this->isGeneratingCode = false;
        }
    }
}; ?>
<div>
    <x-ui.page-header title="Verifikasi Identitas Pasien (KYC)"
        subtitle="Verifikasi identitas pasien secara digital melalui SatuSehat KYC">
        <x-slot:actions>
            <x-atoms.button wire:navigate href="{{ route('satusehat.kyc.logs') }}" icon="clipboard-document-list"
                variant="ghost" size="sm">
                Riwayat KYC
            </x-atoms.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- Section 1: Generate URL --}}
        <div
            class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-5">
                <div
                    class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 shrink-0">
                    <flux:icon name="link" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">Generate URL</h3>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Pasien mengisi form verifikasi mandiri
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <flux:label>Petugas / Agent</flux:label>
                    <flux:select wire:model="urlAgentNik" class="mt-1">
                        <flux:select.option value="">— Pilih Petugas —</flux:select.option>
                        @foreach ($employees as $emp)
                            <flux:select.option value="{{ $emp['nik'] }}">{{ $emp['name'] }} ({{ $emp['nik'] }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('urlAgentNik')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <x-atoms.button wire:click="generateUrl" wire:loading.attr="disabled" icon="arrow-path" variant="primary"
                    class="w-full">
                    <span wire:loading.remove wire:target="generateUrl">Generate URL</span>
                    <span wire:loading wire:target="generateUrl">Generating...</span>
                </x-atoms.button>

                @if ($urlError)
                    <div class="p-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                        <p class="text-xs text-red-600 dark:text-red-400">{{ $urlError }}</p>
                    </div>
                @endif

                @if ($generatedUrl)
                    <div
                        class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                            <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">URL Berhasil
                                Digenerate</span>
                        </div>
                        <p class="text-xs font-mono text-zinc-600 dark:text-primary-dark-300 break-all leading-relaxed">
                            {{ $generatedUrl }}
                        </p>
                        <div class="flex gap-2">
                            <x-atoms.button href="{{ $generatedUrl }}" target="_blank" icon="arrow-top-right-on-square"
                                size="sm" variant="primary">
                                Buka
                            </x-atoms.button>
                            <x-atoms.button
                                x-on:click="navigator.clipboard.writeText('{{ $generatedUrl }}').then(() => $wire.toastSuccess('URL berhasil disalin', 'Disalin'))"
                                icon="clipboard" size="sm" variant="ghost">
                                Salin
                            </x-atoms.button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Section 2: Challenge Code --}}
        <div
            class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-5">
                <div
                    class="flex items-center justify-center w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/30 shrink-0">
                    <flux:icon name="device-phone-mobile" class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-primary-dark-100">Challenge Code</h3>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Pasien dengan aplikasi SATUSEHAT Mobile
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <flux:label>Petugas / Agent</flux:label>
                    <flux:select wire:model="codeAgentNik" class="mt-1">
                        <flux:select.option value="">— Pilih Petugas —</flux:select.option>
                        @foreach ($employees as $emp)
                            <flux:select.option value="{{ $emp['nik'] }}">{{ $emp['name'] }} ({{ $emp['nik'] }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('codeAgentNik')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <flux:label>NIK Pasien</flux:label>
                    <flux:input wire:model="patientNik" placeholder="16 digit NIK" maxlength="16"
                        class="mt-1 font-mono" />
                    @error('patientNik')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <flux:label>Nama Pasien</flux:label>
                    <flux:input wire:model="patientName" placeholder="Nama lengkap sesuai KTP" class="mt-1" />
                    @error('patientName')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <x-atoms.button wire:click="generateCode" wire:loading.attr="disabled" icon="arrow-path" variant="primary"
                    class="w-full">
                    <span wire:loading.remove wire:target="generateCode">Generate Kode</span>
                    <span wire:loading wire:target="generateCode">Generating...</span>
                </x-atoms.button>

                @if ($codeError)
                    <div class="p-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                        <p class="text-xs text-red-600 dark:text-red-400">{{ $codeError }}</p>
                    </div>
                @endif

                @if ($challengeCode)
                    <div
                        class="p-4 rounded-xl bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-purple-700 dark:text-purple-400">Kode
                                Challenge</span>
                            <x-atoms.button
                                x-on:click="navigator.clipboard.writeText('{{ $challengeCode }}').then(() => $wire.toastSuccess('Kode berhasil disalin', 'Disalin'))"
                                icon="clipboard" size="xs" variant="ghost">
                                Salin
                            </x-atoms.button>
                        </div>
                        <div class="flex items-center justify-center">
                            <span
                                class="font-mono text-4xl font-bold tracking-[0.5em] text-purple-700 dark:text-purple-300">
                                {{ $challengeCode }}
                            </span>
                        </div>
                        @if ($challengeIhs || $challengeExpiry)
                            <div
                                class="pt-2 border-t border-purple-200 dark:border-purple-700/50 grid grid-cols-2 gap-2 text-xs">
                                @if ($challengeIhs)
                                    <div>
                                        <span class="text-zinc-400 dark:text-primary-dark-500">IHS Number</span>
                                        <p
                                            class="font-mono font-medium text-zinc-700 dark:text-primary-dark-300 truncate">
                                            {{ $challengeIhs }}</p>
                                    </div>
                                @endif
                                @if ($challengeExpiry)
                                    <div>
                                        <span class="text-zinc-400 dark:text-primary-dark-500">Kadaluarsa</span>
                                        <p class="font-medium text-zinc-700 dark:text-primary-dark-300">
                                            {{ \Carbon\Carbon::parse($challengeExpiry)->format('H:i d/m/Y') }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Recent KYC Activity --}}
    <div
        class="bg-white dark:bg-primary-dark-800 rounded-2xl border border-zinc-200/80 dark:border-primary-dark-700/60 shadow-sm overflow-hidden">
        <div
            class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-primary-dark-700/60">
            <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">Aktivitas KYC Terbaru</h3>
            <x-atoms.button wire:navigate href="{{ route('satusehat.kyc.logs') }}" size="sm" variant="ghost"
                icon="arrow-right">
                Lihat Semua
            </x-atoms.button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr
                        class="border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/70 dark:bg-primary-dark-900/40">
                        <th
                            class="px-5 py-3 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Waktu</th>
                        <th
                            class="px-5 py-3 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Petugas</th>
                        <th
                            class="px-5 py-3 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Tipe</th>
                        <th
                            class="px-5 py-3 text-xs font-semibold tracking-wider text-left uppercase text-zinc-400 dark:text-primary-dark-500">
                            Pasien</th>
                        <th
                            class="px-5 py-3 text-xs font-semibold tracking-wider text-center uppercase text-zinc-400 dark:text-primary-dark-500 w-24">
                            Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100/80 dark:divide-primary-dark-700/40">
                    @forelse ($recentLogs as $log)
                        <tr class="hover:bg-zinc-50/60 dark:hover:bg-primary-dark-700/20 transition-colors">
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span class="text-xs text-zinc-500 dark:text-primary-dark-400">
                                    {{ $log->created_at->diffForHumans() }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-200">
                                    {{ $log->agent_name }}
                                </p>
                                <p class="text-xs font-mono text-zinc-400 dark:text-primary-dark-500">
                                    {{ $log->agent_nik }}
                                </p>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <flux:badge color="{{ $log->type_badge_color }}" size="sm">
                                    {{ $log->type_label }}</flux:badge>
                            </td>
                            <td class="px-5 py-3">
                                @if ($log->patient_name || $log->patient_nik)
                                    <p class="text-sm text-zinc-700 dark:text-primary-dark-200">
                                        {{ $log->patient_name ?? '—' }}</p>
                                    <p class="text-xs font-mono text-zinc-400 dark:text-primary-dark-500">
                                        {{ $log->patient_nik }}</p>
                                @else
                                    <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if ($log->is_success)
                                    <flux:badge color="green" size="sm">Sukses</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm">Gagal</flux:badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon name="clipboard-document-list"
                                        class="w-8 h-8 text-zinc-300 dark:text-primary-dark-600" />
                                    <p class="text-sm text-zinc-400 dark:text-primary-dark-500">Belum ada aktivitas KYC
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
