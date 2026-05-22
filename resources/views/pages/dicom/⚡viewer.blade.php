<?php

use App\Helpers\ConfigurationHelper;
use App\Models\Dicom\DicomStudy;
use App\Services\Dicom\OrthancService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('DICOM — Viewer PACS')] class extends Component {
    use WithPagination;

    #[Url]
    public string $searchPatient = '';

    #[Url]
    public string $filterModality = '';

    #[Url]
    public string $filterStartDate = '';

    #[Url]
    public string $filterEndDate = '';

    public bool $isConfigured = false;
    public string $viewerUrl = '';

    public function mount(): void
    {
        $this->filterStartDate = '2026-02-01';
        $this->filterEndDate = now()->format('Y-m-d');

        $orthanc = app(OrthancService::class);
        $this->isConfigured = $orthanc->isConfigured();
        $this->viewerUrl = ConfigurationHelper::get('dicom.viewer_url', '');
    }

    public function updatedSearchPatient(): void
    {
        $this->resetPage();
    }
    public function updatedFilterModality(): void
    {
        $this->resetPage();
    }
    public function updatedFilterStartDate(): void
    {
        $this->resetPage();
    }
    public function updatedFilterEndDate(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        if (!$this->isConfigured) {
            return ['studies' => collect(), 'localMap' => []];
        }

        $orthanc = app(OrthancService::class);

        $query = [];
        if ($this->searchPatient) {
            $query['PatientName'] = '*' . $this->searchPatient . '*';
        }
        if ($this->filterModality) {
            $query['ModalitiesInStudy'] = $this->filterModality;
        }

        if ($this->filterStartDate && $this->filterEndDate) {
            if ($this->filterStartDate === $this->filterEndDate) {
                $query['StudyDate'] = \Carbon\Carbon::parse($this->filterStartDate)->format('Ymd');
            } else {
                $d1 = \Carbon\Carbon::parse($this->filterStartDate)->format('Ymd');
                $d2 = \Carbon\Carbon::parse($this->filterEndDate)->format('Ymd');
                $query['StudyDate'] = "{$d1}-{$d2}";
            }
        } elseif ($this->filterStartDate) {
            $query['StudyDate'] = \Carbon\Carbon::parse($this->filterStartDate)->format('Ymd');
        }

        $result = $orthanc->findStudies($query);
        $studies = $result['success'] && is_array($result['data']) ? collect($result['data']) : collect();

        // Map orthanc_study_id → DicomStudy lokal (untuk link ke no_rawat)
        $orthancIds = $studies->pluck('ID')->filter()->values()->all();
        $localMap = DicomStudy::whereIn('orthanc_study_id', $orthancIds)->pluck('no_rawat', 'orthanc_study_id')->all();

        return compact('studies', 'localMap');
    }

    /** Bangun URL viewer: Stone Web Viewer pakai ?study={StudyInstanceUID}, OHIF pakai ?StudyInstanceUIDs= */
    public function buildViewerUrl(string $studyUid, string $orthancId): string
    {
        if (empty($this->viewerUrl)) {
            return '#';
        }

        $url = $this->viewerUrl;

        // Ganti placeholder eksplisit jika dikonfigurasi manual
        if (str_contains($url, '{')) {
            return str_replace(['{StudyInstanceUID}', '{orthancId}', '{study_id}'], [$studyUid, $orthancId, $orthancId], $url);
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        // OHIF Viewer — param: StudyInstanceUIDs=
        if (stripos($url, 'ohif') !== false) {
            return $url . $separator . 'StudyInstanceUIDs=' . $studyUid;
        }

        // Stone Web Viewer — param: study= dengan StudyInstanceUID (bukan Orthanc ID)
        return $url . $separator . 'study=' . $studyUid;
    }
}; ?>

<div>
    <x-ui.page-header title="Viewer PACS"
        subtitle="Daftar studi DICOM dari Orthanc. Klik 'Buka Viewer' untuk melihat gambar." />

    @if (!$isConfigured)
        <x-ui.empty-state icon="photo" title="Orthanc Belum Dikonfigurasi"
            description="Atur koneksi ke server Orthanc terlebih dahulu di halaman Konfigurasi → DICOM.">
            <x-atoms.button icon="cog-6-tooth" href="{{ route('configuration.connectivity', ['tab' => 'dicom']) }}"
                wire:navigate>
                Buka Konfigurasi
            </x-atoms.button>
        </x-ui.empty-state>
    @else
        <x-organisms.data-panel title="Daftar Studi PACS" subtitle="Pencarian dan filter studi DICOM" x-data="{
        openViewer(url) {
            // Fallback cerdas untuk mencegah Mixed Content Error iframe:
            if (window.location.protocol === 'https:' && url.startsWith('http://')) {
                window.open(url, '_blank');
            } else {
                $dispatch('open-viewer', url);
            }
        }
    }">
            <x-slot:filter>
                <div class="flex flex-wrap items-center gap-3">
                    {{-- Search --}}
                    <div class="flex-1 min-w-[200px]">
                        <flux:input wire:model.live.debounce.400ms="searchPatient" placeholder="Nama pasien atau RM..."
                            icon="magnifying-glass" />
                    </div>

                    {{-- Range Tanggal --}}
                    <div class="flex items-center gap-2 shrink-0">
                        <flux:input type="date" wire:model.live="filterStartDate" class="w-40" />
                        <span class="text-xs text-zinc-400 dark:text-primary-dark-500 shrink-0">s/d</span>
                        <flux:input type="date" wire:model.live="filterEndDate" class="w-40" />
                    </div>

                    {{-- Modality --}}
                    <div class="w-40 shrink-0">
                        <flux:select wire:model.live="filterModality">
                            <flux:select.option value="">Semua Modality</flux:select.option>
                            @foreach (['CT', 'MR', 'DR', 'CR', 'US', 'PT', 'NM', 'DX', 'MG', 'OT'] as $m)
                                <flux:select.option value="{{ $m }}">{{ $m }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            </x-slot:filter>

            {{-- Tabel --}}
            <x-organisms.table>
                <x-slot:headings>
                    <x-atoms.table-heading>Pasien</x-atoms.table-heading>
                    <x-atoms.table-heading>No. RM</x-atoms.table-heading>
                    <x-atoms.table-heading>Deskripsi</x-atoms.table-heading>
                    <x-atoms.table-heading>Modality</x-atoms.table-heading>
                    <x-atoms.table-heading>Tgl Studi</x-atoms.table-heading>
                    <x-atoms.table-heading align="center">Series</x-atoms.table-heading>
                    <x-atoms.table-heading align="center">Instance</x-atoms.table-heading>
                    <x-atoms.table-heading>Aksi</x-atoms.table-heading>
                </x-slot:headings>
                @forelse ($studies as $study)
                        @php
                            $tags = $study['MainDicomTags'] ?? [];
                            $patientTags = $study['PatientMainDicomTags'] ?? [];
                            $orthancId = $study['ID'] ?? '';
                            $studyUid = $tags['StudyInstanceUID'] ?? '';
                            $patientName = str_replace('^', ' ', $patientTags['PatientName'] ?? '-');
                            $patientId = $patientTags['PatientID'] ?? '-';
                            $desc = $tags['StudyDescription'] ?? '-';
                            $modality = $tags['ModalitiesInStudy'] ?? ($tags['Modality'] ?? '-');
                            $studyDate =
                                isset($tags['StudyDate']) && strlen($tags['StudyDate']) === 8
                                    ? \Carbon\Carbon::createFromFormat('Ymd', $tags['StudyDate'])?->format('d/m/Y')
                                    : $tags['StudyDate'] ?? '-';
                            $seriesCount = count($study['Series'] ?? []);
                            $noRawat = $localMap[$orthancId] ?? null;
                            $viewerLink = $this->buildViewerUrl($studyUid, $orthancId);
                        @endphp
                        <x-molecules.table-row>
                            <x-atoms.table-cell
                                class="font-medium text-zinc-900 dark:text-primary-dark-100">{{ $patientName }}</x-atoms.table-cell>
                            <x-atoms.table-cell class="font-mono">{{ $patientId }}</x-atoms.table-cell>
                            <x-atoms.table-cell class="max-w-[180px] truncate"
                                title="{{ $desc }}">{{ $desc }}</x-atoms.table-cell>
                            <x-atoms.table-cell>
                                <flux:badge color="blue" size="sm">{{ $modality }}</flux:badge>
                            </x-atoms.table-cell>
                            <x-atoms.table-cell>{{ $studyDate }}</x-atoms.table-cell>
                            <x-atoms.table-cell align="center">{{ $seriesCount }}</x-atoms.table-cell>
                            <x-atoms.table-cell align="center">
                                {{ $study['Statistics']['CountInstances'] ?? '-' }}
                            </x-atoms.table-cell>
                            <x-atoms.table-cell>
                                <div class="flex items-center gap-2">
                                    @if ($viewerLink !== '#')
                                        <x-atoms.button size="sm" icon="eye" variant="ghost" tooltip="Buka Viewer"
                                            x-on:click="openViewer('{{ $viewerLink }}')" />
                                @endif
                                @if ($noRawat)
                                    <flux:badge color="teal" size="sm" icon="link">
                                        {{ $noRawat }}
                                    </flux:badge>
                                @endif
                            </div>
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @empty
                    <x-molecules.table-row>
                        <x-atoms.table-cell colspan="8" align="center"
                            class="text-zinc-400 dark:text-primary-dark-500 py-12">
                            Tidak ada studi ditemukan dengan filter yang dipilih.
                        </x-atoms.table-cell>
                    </x-molecules.table-row>
                @endforelse
            </x-organisms.table>

            @if ($studies->isEmpty() && empty($this->searchPatient) && empty($this->filterModality))
                <x-slot:footer>
                    <div class="text-center text-sm text-zinc-400 dark:text-primary-dark-500">
                        Belum ada studi di Orthanc, atau server tidak dapat dijangkau.
                    </div>
                </x-slot:footer>
            @endif
        </x-organisms.data-panel>
    @endif

    {{-- Modal Viewer --}}
    <x-organisms.pacs-viewer-modal />
</div>
