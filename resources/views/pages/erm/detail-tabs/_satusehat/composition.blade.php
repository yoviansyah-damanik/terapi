@php
    $compositionList = collect();
    if ($this->reg->resumePasien) {
        $compositionList->push(
            (object) [
                'idStr' =>
                    $this->reg->no_rawat .
                    '-RESUME_RALAN-' .
                    $this->reg->tgl_registrasi->format('Ymd') .
                    '-' .
                    str_replace(':', '', $this->reg->jam_reg),
                'judul' => 'Resume Medis Rawat Jalan',
                'tanggal' => $this->reg->tgl_registrasi,
            ],
        );
    }
    if ($this->reg->resumePasienRanap) {
        $compositionList->push(
            (object) [
                'idStr' =>
                    $this->reg->no_rawat .
                    '-RESUME_RANAP-' .
                    $this->reg->tgl_registrasi->format('Ymd') .
                    '-' .
                    str_replace(':', '', $this->reg->jam_reg),
                'judul' => 'Resume Medis Rawat Inap',
                'tanggal' => $this->reg->tgl_registrasi,
            ],
        );
    }
    $adimeList = \App\Models\Simrs\CatatanAdimeGizi::where('no_rawat', $this->reg->no_rawat)
        ->with('petugas')
        ->orderByDesc('tanggal')
        ->get();
    $resumeCompositions = $ssCompositions->whereIn('local_id', ['resume_ralan', 'resume_ranap']);
@endphp

{{-- Sub-panel: Resume Medis --}}
<div
    class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
    <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
        <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">
            Resume Medis
            <flux:badge color="{{ $resumeCompositions->count() > 0 ? 'primary' : 'zinc' }}" size="sm">
                {{ $resumeCompositions->count() }}
            </flux:badge>
        </p>
        <x-atoms.button wire:click="sendSsCompositions" wire:loading.attr="disabled" icon="paper-airplane" size="sm">
            <span wire:loading.remove wire:target="sendSsCompositions">Kirim Resume</span>
            <span wire:loading wire:target="sendSsCompositions">Mengirim...</span>
        </x-atoms.button>
    </div>
    @if ($compositionList->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th class="{{ $thClass }} w-16 text-center">
                            <input type="checkbox"
                                x-on:change="$el.checked ? $wire.set('ssSelectedCompositions', {{ $compositionList->pluck('idStr')->toJson() }}) : $wire.set('ssSelectedCompositions', [])"
                                class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                        </th>
                        <th class="{{ $thClass }}">Tipe Resume</th>
                        <th class="{{ $thClass }}">Tanggal</th>
                        <th class="{{ $thClass }}">Status Sinkronisasi</th>
                        <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                    @foreach ($compositionList as $cmp)
                        @php $syncedComp = $ssCompositions->where('local_id', $cmp->idStr)->first(); @endphp
                        <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-2 text-center">
                                @if ($syncedComp)
                                    <flux:icon name="check-circle" variant="solid"
                                        class="w-5 h-5 text-green-500 mx-auto" />
                                @else
                                    <input type="checkbox" wire:model="ssSelectedCompositions"
                                        value="{{ $cmp->idStr }}"
                                        class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                                @endif
                            </td>
                            <td class="{{ $tdText }}">{{ $cmp->judul }}</td>
                            <td class="{{ $tdMuted }} text-xs">
                                {{ $cmp->tanggal ? \Carbon\Carbon::parse($cmp->tanggal)->format('d/m/Y') : '-' }}
                            </td>
                            <td class="{{ $tdMuted }}">
                                @if ($syncedComp)
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                        <span class="text-[10px] font-mono">{{ $syncedComp->ihs_number }}</span>
                                        <span
                                            class="text-[10px]">{{ $syncedComp->synced_at?->format('d/m/Y H:i') }}</span>
                                    </div>
                                @else
                                    <span class="text-zinc-400">Belum dikirim</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if ($syncedComp)
                                    <x-atoms.button icon="eye" variant="ghost" size="sm"
                                        wire:click="openSsDetail('{{ $syncedComp->ihs_number }}')"
                                        tooltip="Lihat detail">
                                    </x-atoms.button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="flex flex-col items-center py-8">
            <flux:icon name="document-text" class="w-8 h-8 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-400 dark:text-primary-dark-500">Tidak ada data resume
                medis untuk kunjungan ini.</p>
        </div>
    @endif
</div>

{{-- Sub-panel: Catatan Gizi ADIME --}}
<div
    class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
    <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
        <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">
            Catatan Gizi ADIME
            <flux:badge color="{{ $ssAdimeGiziCompositions->count() > 0 ? 'primary' : 'zinc' }}" size="sm">
                {{ $ssAdimeGiziCompositions->count() }}
            </flux:badge>
        </p>
        <x-atoms.button wire:click="sendSsAdimeGiziCompositions" wire:loading.attr="disabled" icon="paper-airplane"
            size="sm">
            <span wire:loading.remove wire:target="sendSsAdimeGiziCompositions">Kirim Catatan
                Gizi</span>
            <span wire:loading wire:target="sendSsAdimeGiziCompositions">Mengirim...</span>
        </x-atoms.button>
    </div>
    @if ($adimeList->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-primary-dark-700">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900">
                    <tr>
                        <th class="{{ $thClass }} w-16 text-center">
                            <input type="checkbox"
                                x-on:change="$el.checked ? $wire.set('ssSelectedAdimeGizis', {{ $adimeList->map(fn($a) => $this->reg->no_rawat . '-ADIME-' . $a->tanggal->format('Ymd') . '-' . str_replace(':', '', $a->jam ?? '000000'))->toJson() }}) : $wire.set('ssSelectedAdimeGizis', [])"
                                class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                        </th>
                        <th class="{{ $thClass }}">Tanggal</th>
                        <th class="{{ $thClass }}">A — Asesmen</th>
                        <th class="{{ $thClass }}">D — Diagnosis</th>
                        <th class="{{ $thClass }}">I — Intervensi</th>
                        <th class="{{ $thClass }}">M/E — Monitoring & Evaluasi</th>
                        <th class="{{ $thClass }}">Petugas Gizi</th>
                        <th class="{{ $thClass }}">Status Sinkronisasi</th>
                        <th class="{{ $thClass }} w-16 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700">
                    @foreach ($adimeList as $adime)
                        @php
                            $adimeIdStr =
                                $this->reg->no_rawat .
                                '-ADIME-' .
                                $adime->tanggal->format('Ymd') .
                                '-' .
                                str_replace(':', '', $adime->jam ?? '000000');
                            $adimeSynced = $ssAdimeGiziCompositions->where('local_id', $adimeIdStr)->first();
                        @endphp
                        <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/50">
                            <td class="px-4 py-2 text-center">
                                @if ($adimeSynced)
                                    <flux:icon name="check-circle" variant="solid"
                                        class="w-5 h-5 text-green-500 mx-auto" />
                                @else
                                    <input type="checkbox" wire:model="ssSelectedAdimeGizis"
                                        value="{{ $adimeIdStr }}"
                                        class="w-4 h-4 text-primary-600 bg-zinc-100 border-zinc-300 rounded focus:ring-primary-500 dark:bg-primary-dark-700 dark:border-primary-dark-600">
                                @endif
                            </td>
                            <td class="{{ $tdMuted }} text-xs font-medium whitespace-nowrap">
                                {{ $adime->tanggal?->format('d/m/Y') }}
                            </td>
                            <td class="{{ $tdMuted }} max-w-[140px]">
                                <span class="line-clamp-2 text-xs">{{ $adime->asesmen ?: '-' }}</span>
                            </td>
                            <td class="{{ $tdMuted }} max-w-[140px]">
                                <span class="line-clamp-2 text-xs">{{ $adime->diagnosis ?: '-' }}</span>
                            </td>
                            <td class="{{ $tdMuted }} max-w-[140px]">
                                <span class="line-clamp-2 text-xs">
                                    {{ $adime->intervensi ?: '-' }}
                                    @if ($adime->instruksi)
                                        <br><em class="text-zinc-400">{{ $adime->instruksi }}</em>
                                    @endif
                                </span>
                            </td>
                            <td class="{{ $tdMuted }} max-w-[140px]">
                                <span class="line-clamp-2 text-xs">
                                    {{ trim(($adime->monitoring ?? '') . ' ' . ($adime->evaluasi ?? '')) ?: '-' }}
                                </span>
                            </td>
                            <td class="{{ $tdMuted }} text-xs whitespace-nowrap">
                                {{ $adime->petugas?->nama ?? ($adime->nip ?? '-') }}
                            </td>
                            <td class="{{ $tdMuted }}">
                                @if ($adimeSynced)
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-semibold text-green-600 dark:text-green-400">Terkirim</span>
                                        <span class="text-[10px] font-mono">{{ $adimeSynced->ihs_number }}</span>
                                        <span
                                            class="text-[10px]">{{ $adimeSynced->synced_at?->format('d/m/Y H:i') }}</span>
                                    </div>
                                @else
                                    <span class="text-zinc-400">Belum dikirim</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if ($adimeSynced)
                                    <x-atoms.button variant="ghost" size="sm" icon="eye"
                                        wire:click="openSsDetail('{{ $adimeSynced->ihs_number }}')"
                                        tooltip="Lihat detail">
                                    </x-atoms.button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="flex flex-col items-center py-8">
            <flux:icon name="cake" class="w-8 h-8 text-zinc-300 dark:text-primary-dark-600" />
            <p class="mt-2 text-sm text-zinc-400 dark:text-primary-dark-500">Tidak ada catatan gizi ADIME untuk
                kunjungan ini.</p>
        </div>
    @endif
</div>

{{-- Sub-panel: Medication Composition --}}
@php
    // Guard untuk IDE static analysis — variabel diinjeksi via with() Livewire
    $ssMedications ??= collect();
    $ssMedicationDispenses ??= collect();
    $ssMedicationAdministrations ??= collect();
    $ssMedicationCompositions ??= collect();

    $medCompSynced = $ssMedicationCompositions->first();
    $medCompLocalId = $this->reg->no_rawat . '-COMP_MEDICATION-' . $this->reg->tgl_registrasi->format('Ymd');

    // Kelompokkan per MedicationRequest → Dispense(s) → Administration(s)
    $medGroups = $ssMedications->map(
        fn($req) => [
            'request' => $req,
            'dispenses' => $ssMedicationDispenses->where('medication_request_ihs', $req->ihs_number)->values(),
            'admins' => $ssMedicationAdministrations->where('medication_request_ihs', $req->ihs_number)->values(),
        ],
    );

    $medReqCount = $ssMedications->count();
    $medDispCount = $ssMedicationDispenses->count();
    $medAdminCount = $ssMedicationAdministrations->count();
    $totalMedRefs = $medReqCount + $medDispCount + $medAdminCount;

    // Jumlah obat yang diharapkan dari SIMRS (untuk validasi kelengkapan)
    $medListCount = 0;
    try {
        $medListCount = \App\Models\Simrs\DetailPemberianObat::where('no_rawat', $this->reg->no_rawat)->count();
    } catch (\Exception) {
    }

    $reqComplete = $medListCount > 0 && $medReqCount >= $medListCount;
    $dispComplete = $medListCount > 0 && $medDispCount >= $medListCount;
    $admComplete = $medListCount > 0 && $medAdminCount >= $medListCount;
    $canSend = $reqComplete && $dispComplete && $admComplete;
@endphp

<div
    class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">

    {{-- Header --}}
    <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
        <div class="flex items-center gap-2">
            <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Medication Composition</p>
            <flux:badge color="{{ $medCompSynced ? 'primary' : 'zinc' }}" size="sm">
                {{ $medCompSynced ? 'Terkirim' : 'Belum' }}
            </flux:badge>
            {{-- Ringkasan entry count --}}
            <div class="flex items-center gap-1.5 text-xs text-zinc-400 dark:text-primary-dark-500">
                <span
                    class="inline-flex items-center gap-0.5 rounded-full bg-blue-50 dark:bg-blue-900/20 px-1.5 py-0.5 text-blue-600 dark:text-blue-400 font-mono font-semibold">
                    Req&nbsp;{{ $medReqCount }}
                </span>
                <span
                    class="inline-flex items-center gap-0.5 rounded-full bg-violet-50 dark:bg-violet-900/20 px-1.5 py-0.5 text-violet-600 dark:text-violet-400 font-mono font-semibold">
                    Dis&nbsp;{{ $medDispCount }}
                </span>
                <span
                    class="inline-flex items-center gap-0.5 rounded-full bg-teal-50 dark:bg-teal-900/20 px-1.5 py-0.5 text-teal-600 dark:text-teal-400 font-mono font-semibold">
                    Adm&nbsp;{{ $medAdminCount }}
                </span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if ($medCompSynced)
                <x-atoms.button variant="ghost" size="sm" icon="eye"
                    wire:click="openSsDetail('{{ $medCompSynced->ihs_number }}')" tooltip="Lihat detail Composition">
                </x-atoms.button>
            @endif
            <x-atoms.button wire:click="sendMedicationCompositions" wire:loading.attr="disabled"
                icon="paper-airplane" size="sm" :disabled="!$canSend">
                <span wire:loading.remove wire:target="sendMedicationCompositions">
                    {{ $medCompSynced ? 'Kirim Ulang' : 'Kirim Medication' }}
                </span>
                <span wire:loading wire:target="sendMedicationCompositions">Mengirim...</span>
            </x-atoms.button>
        </div>
    </div>

    {{-- Status sync composition --}}
    @if ($medCompSynced)
        <div
            class="flex items-center gap-3 px-4 py-2.5 bg-emerald-50/60 dark:bg-emerald-900/10 border-b border-emerald-100 dark:border-emerald-800/30">
            <flux:icon name="check-circle" class="size-4 shrink-0 text-emerald-500" />
            <div class="text-xs text-emerald-700 dark:text-emerald-300 flex flex-wrap gap-x-3 gap-y-0.5">
                <span>IHS: <span class="font-mono font-semibold">{{ $medCompSynced->ihs_number }}</span></span>
                <span>Type: <span class="font-mono">TK000013 / Obat</span></span>
                <span>Dikirim: {{ $medCompSynced->synced_at?->format('d/m/Y H:i') }}</span>
            </div>
        </div>
    @endif

    {{-- Validasi kelengkapan resource per tipe --}}
    @if ($medListCount > 0)
        <div
            class="flex flex-wrap items-center gap-x-4 gap-y-1.5 px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700/60
            {{ $canSend ? 'bg-emerald-50/30 dark:bg-emerald-900/5' : 'bg-amber-50/30 dark:bg-amber-900/5' }}">
            <span
                class="text-xs font-medium {{ $canSend ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }} shrink-0">
                Kelengkapan ({{ $medListCount }} obat):
            </span>
            @foreach ([['label' => 'MedRequest', 'ok' => $reqComplete, 'sent' => $medReqCount], ['label' => 'MedDispense', 'ok' => $dispComplete, 'sent' => $medDispCount], ['label' => 'MedAdministration', 'ok' => $admComplete, 'sent' => $medAdminCount]] as $chk)
                <span
                    class="inline-flex items-center gap-1 text-xs {{ $chk['ok'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                    <flux:icon name="{{ $chk['ok'] ? 'check-circle' : 'exclamation-circle' }}"
                        class="size-3.5 shrink-0" />
                    {{ $chk['label'] }}: <span
                        class="font-mono font-semibold">{{ $chk['sent'] }}/{{ $medListCount }}</span>
                </span>
            @endforeach
            @if (!$canSend)
                <span class="text-[11px] text-amber-500 dark:text-amber-400 italic ml-auto">Kirim semua resource
                    terlebih dahulu.</span>
            @endif
        </div>
    @elseif ($medReqCount === 0)
        <div
            class="flex items-center gap-2 px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700/60 bg-zinc-50/60 dark:bg-primary-dark-900/20">
            <flux:icon name="information-circle" class="size-4 shrink-0 text-zinc-400" />
            <p class="text-xs text-zinc-500 dark:text-primary-dark-400">Tidak ada data obat untuk kunjungan ini.</p>
        </div>
    @endif

    {{-- Daftar entries yang akan disertakan --}}
    @if ($medGroups->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-zinc-50 dark:bg-primary-dark-900/50">
                    <tr>
                        <th class="{{ $thClass }}">Resource</th>
                        <th class="{{ $thClass }}">Local ID</th>
                        <th class="{{ $thClass }}">IHS Number</th>
                        <th class="{{ $thClass }}">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-primary-dark-700/50">
                    @foreach ($medGroups as $group)
                        @php
                            $req = $group['request'];
                            $reqSt = $req->status ?? '-';
                            $reqStColor = match ($reqSt) {
                                'active', 'completed' => 'emerald',
                                'cancelled', 'stopped' => 'red',
                                default => 'zinc',
                            };
                        @endphp

                        {{-- MedicationRequest row --}}
                        <tr class="bg-blue-50/40 dark:bg-blue-900/10">
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <flux:badge size="sm" color="blue">MedicationRequest</flux:badge>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs text-zinc-500 dark:text-primary-dark-400 max-w-[200px] truncate"
                                title="{{ $req->local_id }}">{{ $req->local_id }}</td>
                            <td
                                class="px-4 py-2.5 font-mono text-xs text-zinc-700 dark:text-primary-dark-200 whitespace-nowrap">
                                {{ $req->ihs_number ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <flux:badge size="sm" :color="$reqStColor">{{ $reqSt }}</flux:badge>
                            </td>
                        </tr>

                        {{-- MedicationDispense rows --}}
                        @foreach ($group['dispenses'] as $disp)
                            @php
                                $dispSt = $disp->status ?? '-';
                                $dispStColor = match ($dispSt) {
                                    'completed' => 'emerald',
                                    'cancelled', 'declined' => 'red',
                                    'in-progress' => 'blue',
                                    default => 'zinc',
                                };
                            @endphp
                            <tr class="bg-violet-50/30 dark:bg-violet-900/5">
                                <td class="py-2 pr-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="w-6 shrink-0"></span>
                                        <span class="w-px h-4 bg-violet-300 dark:bg-violet-700 mr-2 shrink-0"></span>
                                        <flux:badge size="sm" color="violet">MedicationDispense</flux:badge>
                                    </div>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs text-zinc-500 dark:text-primary-dark-400 max-w-[200px] truncate"
                                    title="{{ $disp->local_id }}">{{ $disp->local_id }}</td>
                                <td
                                    class="px-4 py-2 font-mono text-xs text-zinc-700 dark:text-primary-dark-200 whitespace-nowrap">
                                    {{ $disp->ihs_number ?? '—' }}
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <flux:badge size="sm" :color="$dispStColor">{{ $dispSt }}</flux:badge>
                                </td>
                            </tr>
                        @endforeach

                        {{-- MedicationAdministration rows --}}
                        @foreach ($group['admins'] as $adm)
                            @php
                                $admSt = $adm->status ?? '-';
                                $admStColor = match ($admSt) {
                                    'completed' => 'emerald',
                                    'not-done' => 'red',
                                    'in-progress' => 'blue',
                                    default => 'zinc',
                                };
                            @endphp
                            <tr class="bg-teal-50/30 dark:bg-teal-900/5">
                                <td class="py-2 pr-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="w-6 shrink-0"></span>
                                        <span class="w-px h-4 bg-teal-300 dark:bg-teal-700 mr-2 shrink-0"></span>
                                        <flux:badge size="sm" color="teal">MedicationAdministration
                                        </flux:badge>
                                    </div>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs text-zinc-500 dark:text-primary-dark-400 max-w-[200px] truncate"
                                    title="{{ $adm->local_id }}">{{ $adm->local_id }}</td>
                                <td
                                    class="px-4 py-2 font-mono text-xs text-zinc-700 dark:text-primary-dark-200 whitespace-nowrap">
                                    {{ $adm->ihs_number ?? '—' }}
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <flux:badge size="sm" :color="$admStColor">{{ $admSt }}</flux:badge>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach

                    {{-- MedicationDispense & Administration tanpa MedReq (orphan) --}}
                    @php
                        $linkedReqIhs = $ssMedications->pluck('ihs_number')->filter()->all();
                        $orphanDisp = $ssMedicationDispenses
                            ->whereNotIn('medication_request_ihs', $linkedReqIhs)
                            ->whereNull('medication_request_ihs');
                        $orphanAdm = $ssMedicationAdministrations
                            ->whereNotIn('medication_request_ihs', $linkedReqIhs)
                            ->whereNull('medication_request_ihs');
                    @endphp
                    @foreach ($orphanDisp as $disp)
                        <tr class="bg-violet-50/30 dark:bg-violet-900/5">
                            <td class="px-4 py-2 whitespace-nowrap">
                                <flux:badge size="sm" color="violet">MedicationDispense</flux:badge>
                            </td>
                            <td class="px-4 py-2 font-mono text-xs text-zinc-500 dark:text-primary-dark-400 max-w-[200px] truncate"
                                title="{{ $disp->local_id }}">{{ $disp->local_id }}</td>
                            <td
                                class="px-4 py-2 font-mono text-xs text-zinc-700 dark:text-primary-dark-200 whitespace-nowrap">
                                {{ $disp->ihs_number ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <flux:badge size="sm" color="zinc">{{ $disp->status ?? '-' }}</flux:badge>
                            </td>
                        </tr>
                    @endforeach
                    @foreach ($orphanAdm as $adm)
                        <tr class="bg-teal-50/30 dark:bg-teal-900/5">
                            <td class="px-4 py-2 whitespace-nowrap">
                                <flux:badge size="sm" color="teal">MedicationAdministration</flux:badge>
                            </td>
                            <td class="px-4 py-2 font-mono text-xs text-zinc-500 dark:text-primary-dark-400 max-w-[200px] truncate"
                                title="{{ $adm->local_id }}">{{ $adm->local_id }}</td>
                            <td
                                class="px-4 py-2 font-mono text-xs text-zinc-700 dark:text-primary-dark-200 whitespace-nowrap">
                                {{ $adm->ihs_number ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <flux:badge size="sm" color="zinc">{{ $adm->status ?? '-' }}</flux:badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
