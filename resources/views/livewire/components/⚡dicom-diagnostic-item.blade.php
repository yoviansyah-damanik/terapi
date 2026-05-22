<?php

use App\Services\Dicom\OrthancService;
use Livewire\Component;

new class extends Component {
    public string $type; // echo, find, store
    public string $ae;
    public string $host;
    public int    $port;
    public string $manufacturer = 'STORESCP';

    public ?array $result = null;
    public bool $skipped = false;
    public string $skipReason = '';

    public function mount(): void
    {
        $orthanc = app(OrthancService::class);

        $orthanc->registerModality($this->ae, $this->host, $this->port, $this->manufacturer);

        switch ($this->type) {
            case 'echo':
                $this->result = $orthanc->echoModality($this->ae);
                break;

            case 'find':
                // Gunakan C-FIND standard (Study level) bukan MWL — lebih umum didukung semua modalitas
                $this->result = $orthanc->queryModality($this->ae, 'Study', []);
                break;

            case 'store':
                $studies = $orthanc->getStudies(1);
                if ($studies['success'] && !empty($studies['data'])) {
                    // Gunakan studi yang ada jika tersedia
                    $this->result = $orthanc->storeToModality($this->ae, [$studies['data'][0]], true);
                } else {
                    // PACS kosong — kirim DICOM sintetis sementara untuk uji konektivitas
                    $this->result = $orthanc->storeTestToModality($this->ae);
                }
                break;
        }
    }

    public function placeholder()
    {
        return <<<'HTML'
            <div class="bg-white dark:bg-primary-dark-800 p-4 rounded-xl border border-zinc-200 dark:border-primary-dark-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-zinc-50 dark:bg-primary-dark-900/50">
                            <flux:icon name="arrow-path" variant="mini" class="size-5 text-zinc-300 animate-spin" />
                        </div>
                        <div>
                            <p class="text-xs font-bold text-zinc-400 dark:text-zinc-500 animate-pulse">Menjalankan tes...</p>
                            <p class="text-[10px] text-zinc-300 dark:text-zinc-600 mt-0.5 italic">Menghubungi AE...</p>
                        </div>
                    </div>
                </div>
            </div>
        HTML;
    }
}; ?>

@php
    $labels = [
        'echo'  => 'C-ECHO (Verification)',
        'find'  => 'C-FIND (Query Study)',
        'store' => 'C-STORE (Storage)',
    ];
    $icons = ['echo' => 'signal', 'find' => 'magnifying-glass', 'store' => 'arrow-up-tray'];
    $success = !$skipped && $result && $result['success'];
    $failed  = !$skipped && $result && !$result['success'];
@endphp
<div class="group relative bg-white dark:bg-primary-dark-800 p-4 rounded-xl border border-zinc-200 dark:border-primary-dark-700 hover:border-primary-500/50 transition-all duration-200">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div @class([
                'p-2 rounded-lg shrink-0',
                'bg-green-50 text-green-600 dark:bg-green-950/30 dark:text-green-400'  => $success,
                'bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-400'          => $failed,
                'bg-amber-50 text-amber-500 dark:bg-amber-950/30 dark:text-amber-400'  => $skipped,
            ])>
                <flux:icon name="{{ $icons[$type] }}" variant="mini" class="size-5" />
            </div>
            <div>
                <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100">{{ $labels[$type] }}</p>
                @if ($skipped)
                    <p class="text-[10px] mt-0.5 text-amber-500">Dilewati</p>
                @else
                    <p @class(['text-[10px] mt-0.5', 'text-green-600 dark:text-green-400 font-medium' => $success, 'text-red-500' => $failed])>
                        {{ $success ? 'Sukses' : 'Gagal' }} &bull; {{ $result['response_time'] ?? 0 }}ms
                    </p>
                @endif
            </div>
        </div>

        @if ($skipped)
            <flux:tooltip content="{{ $skipReason }}" position="left">
                <flux:icon name="exclamation-triangle" variant="mini" class="size-5 text-amber-400 cursor-help" />
            </flux:tooltip>
        @elseif ($failed)
            <flux:tooltip content="{{ $result['message'] }}" position="left">
                <flux:icon name="exclamation-circle" variant="mini" class="size-5 text-red-500 cursor-help" />
            </flux:tooltip>
        @else
            <flux:icon name="check-circle" variant="mini" class="size-5 text-green-500" />
        @endif
    </div>

    @if ($skipped)
        <div class="mt-3 p-2 bg-amber-50/50 dark:bg-amber-900/10 rounded-lg border border-amber-100 dark:border-amber-900/20">
            <p class="text-[10px] text-amber-700 dark:text-amber-400 leading-relaxed italic">
                {{ $skipReason }}
            </p>
        </div>
    @elseif ($failed && !empty($result['message']))
        <div class="mt-3 p-2 bg-red-50/50 dark:bg-red-900/10 rounded-lg border border-red-100 dark:border-red-900/20">
            <p class="text-[10px] text-red-700 dark:text-red-400 leading-relaxed font-mono italic">
                {{ Str::limit($result['message'], 150) }}
            </p>
        </div>
    @endif
</div>
