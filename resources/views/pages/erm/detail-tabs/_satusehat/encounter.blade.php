<div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-primary-dark-700">
    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Detail Encounter</p>
    <div>
        @if ($ssEncounter->status !== 'finished')
            <x-atoms.button wire:click="updateSsEncounterStatus('finished')" wire:loading.attr="disabled" icon="arrow-path"
                size="sm">
                <span wire:loading.remove wire:target="updateSsEncounterStatus">Selesaikan
                    Encounter</span>
                <span wire:loading wire:target="updateSsEncounterStatus">Memperbarui...</span>
            </x-atoms.button>
        @endif
        @if ($ssEncounter->status !== 'canceled')
            <x-atoms.button wire:click="updateSsEncounterStatus('canceled')" wire:loading.attr="disabled"
                icon="arrow-path" size="sm">
                <span wire:loading.remove wire:target="updateSsEncounterStatus">Batalkan
                    Encounter</span>
                <span wire:loading wire:target="updateSsEncounterStatus">Memperbarui...</span>
            </x-atoms.button>
        @endif
    </div>
</div>
<div class="p-4">
    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 text-sm">
        <div>
            <dt class="text-xs text-zinc-400">Status</dt>
            @if ($ssEncounter->status === 'finished')
                <flux:badge color="green">Selesai</flux:badge>
            @elseif ($ssEncounter->status === 'in-progress')
                <flux:badge color="blue">Dalam Proses</flux:badge>
            @else
                <flux:badge color="yellow">{{ $ssEncounter->status }}</flux:badge>
            @endif
        </div>
        <div>
            <dt class="text-xs text-zinc-400">IHS Number</dt>
            <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                {{ $ssEncounter->ihs_number ?? '-' }}
            </dd>
        </div>
        <div>
            <dt class="text-xs text-zinc-400">Kelas</dt>
            <dd class="text-zinc-900 dark:text-primary-dark-100">{{ $ssEncounter->class ?? '-' }}
            </dd>
        </div>
        <div>
            <dt class="text-xs text-zinc-400">Disinkronkan</dt>
            <dd class="text-zinc-900 dark:text-primary-dark-100">
                {{ $ssEncounter->synced_at?->format('d/m/Y H:i') ?? '-' }}
            </dd>
        </div>
        @if ($ssEncounter->period_start)
            <div>
                <dt class="text-xs text-zinc-400">Mulai</dt>
                <dd class="text-zinc-900 dark:text-primary-dark-100">
                    {{ $ssEncounter->period_start->format('d/m/Y H:i') }}
                </dd>
            </div>
        @endif
        @if ($ssEncounter->period_end)
            <div>
                <dt class="text-xs text-zinc-400">Selesai</dt>
                <dd class="text-zinc-900 dark:text-primary-dark-100">
                    {{ $ssEncounter->period_end->format('d/m/Y H:i') }}
                </dd>
            </div>
        @endif
        <div>
            <dt class="text-xs text-zinc-400">Pasien IHS</dt>
            <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                {{ $ssEncounter->patient_ihs ?? '-' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-zinc-400">Dokter IHS</dt>
            <dd class="font-mono text-zinc-900 dark:text-primary-dark-100">
                {{ $ssEncounter->practitioner_ihs ?? '-' }}
            </dd>
        </div>
    </dl>
</div>
