<?php

use App\Helpers\ConfigurationHelper;
use App\Models\Dicom\Worklist;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('DICOM — Worklist')] class extends Component {
    #[Url]
    public string $filterStartDate = '';

    #[Url]
    public string $filterEndDate = '';

    #[Url]
    public string $filterModality = '';

    #[Url]
    public string $filterStatus = '';

    public array $orders = [];
    public array $wlStatus = []; // noorder -> DicomStudy attributes
    public string $errorMessage = '';
    public string $viewerUrl = '';
    public ?array $selectedOrder = null;
    public bool $showDetailModal = false;

    public ?array $checkStudyResponse = null;
    public bool $showCheckStudyModal = false;

    public bool $showViewerModal = false;
    public string $viewerIframeUrl = '';
    public string $viewerLabel = '';

    public function mount(): void
    {
        if (!isset($this->filterStartDate)) {
            $this->filterStartDate = now()->format('Y-m-d');
        }
        if (!isset($this->filterEndDate)) {
            $this->filterEndDate = now()->format('Y-m-d');
        }
        $this->viewerUrl = ConfigurationHelper::get('dicom.orthanc.viewer_url', '');
        $this->loadOrders();
    }

    public function updatedFilterStartDate(): void
    {
        $this->loadOrders();
    }
    public function updatedFilterEndDate(): void
    {
        $this->loadOrders();
    }
    public function updatedFilterModality(): void
    {
        $this->loadOrders();
    }
    public function updatedFilterStatus(): void
    {
        $this->loadOrders();
    }

    public function loadOrders(): void
    {
        $this->errorMessage = '';

        try {
            $query = Worklist::query();

            if ($this->filterStartDate) {
                $query->whereDate('scheduled_date', '>=', $this->filterStartDate);
            }
            if ($this->filterEndDate) {
                $query->whereDate('scheduled_date', '<=', $this->filterEndDate);
            }
            if ($this->filterModality) {
                $query->where('modality', $this->filterModality);
            }
            if ($this->filterStatus) {
                $query->where('status', $this->filterStatus);
            }

            $this->orders = $query->orderByDesc('scheduled_date')->get()->toArray();
        } catch (\Throwable $e) {
            $this->errorMessage = 'Error: ' . $e->getMessage();
        }
    }

    public function buildViewerUrl(string $studyUid, string $orthancId): string
    {
        if (empty($this->viewerUrl)) {
            return '#';
        }

        $url = $this->viewerUrl;

        if (str_contains($url, '{')) {
            return str_replace(['{StudyInstanceUID}', '{orthancId}', '{study_id}'], [$studyUid, $orthancId, $orthancId], $url);
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        if (stripos($url, 'ohif') !== false) {
            return $url . $separator . 'StudyInstanceUIDs=' . $studyUid;
        }

        return $url . $separator . 'study=' . $studyUid;
    }
    public function viewDetail(string $accessionNumber): void
    {
        $order = collect($this->orders)->firstWhere('accession_number', $accessionNumber);

        if ($order) {
            $this->selectedOrder = is_array($order) ? $order : $order->toArray();
            $this->showDetailModal = true;
        }
    }

    public function openViewer(string $studyUid, string $orthancId, string $label = ''): void
    {
        $this->viewerIframeUrl = $this->buildViewerUrl($studyUid, $orthancId);
        $this->viewerLabel = $label ?: $studyUid;
        $this->showViewerModal = true;
    }

    public function checkStudyStatus(string $accessionNumber): void
    {
        $this->errorMessage = '';
        $this->checkStudyResponse = null;
        try {
            $syncService = new \App\Services\Dicom\OrthancSyncService();
            $check = $syncService->checkAndUpdateStudy($accessionNumber);

            $this->checkStudyResponse = $check;
            $this->showCheckStudyModal = true;

            if ($check['success'] && $check['exists'] && !empty($check['data']['study_id'])) {
                $this->loadOrders();
            } else {
                $this->errorMessage = "Study untuk Accession Number {$accessionNumber} belum tersedia di PACS.";
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal mengecek study: ' . $e->getMessage();
        }
    }
}; ?>

<div>
    <x-ui.page-header title="Worklist DICOM"
        subtitle="Order radiologi dari SIMRS. Tambahkan ke worklist agar tersedia di modalitas." />

    <x-organisms.data-panel title="Daftar Worklist"
        subtitle="Menampilkan order radiologi dari SIMRS dan data masuk via API">
        <x-slot:filter>
            <div class="flex flex-wrap items-center gap-3">
                {{-- Range Tanggal --}}
                <div class="flex items-center gap-2 shrink-0">
                    <flux:input type="date" wire:model.live="filterStartDate" class="w-40" />
                    <span class="text-xs text-zinc-400 dark:text-primary-dark-500 shrink-0">s/d</span>
                    <flux:input type="date" wire:model.live="filterEndDate" class="w-40" />
                </div>

                {{-- Modality --}}
                <div class="w-36 shrink-0">
                    <flux:select wire:model.live="filterModality">
                        <flux:select.option value="">Semua Modality</flux:select.option>
                        @foreach (['CT', 'MR', 'DR', 'CR', 'US', 'OT', 'DX'] as $m)
                            <flux:select.option value="{{ $m }}">{{ $m }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                {{-- Status --}}
                <div class="w-40 shrink-0">
                    <flux:select wire:model.live="filterStatus">
                        <flux:select.option value="">Semua Status</flux:select.option>
                        <flux:select.option value="worklist">Worklist</flux:select.option>
                        <flux:select.option value="pending">Pending</flux:select.option>
                        <flux:select.option value="received">Received</flux:select.option>
                        <flux:select.option value="sent">Sent</flux:select.option>
                        <flux:select.option value="error">Error</flux:select.option>
                    </flux:select>
                </div>

                {{-- Refresh --}}
                <x-atoms.button icon="arrow-path" wire:click="loadOrders" wire:loading.attr="disabled"
                    wire:target="loadOrders">Refresh</x-atoms.button>
            </div>

            @if ($errorMessage)
                <div
                    class="mt-3 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
                    {{ $errorMessage }}
                </div>
            @endif
        </x-slot:filter>

        {{-- Tabel SIMRS --}}
        <x-organisms.table>
            <x-slot:headings>
                <x-atoms.table-heading>No. Order</x-atoms.table-heading>
                <x-atoms.table-heading>Pasien</x-atoms.table-heading>
                <x-atoms.table-heading>No. RM</x-atoms.table-heading>
                <x-atoms.table-heading>No. Rawat</x-atoms.table-heading>
                <x-atoms.table-heading>Jenis Pemeriksaan</x-atoms.table-heading>
                <x-atoms.table-heading>Tgl Permintaan</x-atoms.table-heading>
                <x-atoms.table-heading>AE Title</x-atoms.table-heading>
                <x-atoms.table-heading>Received / Sent</x-atoms.table-heading>
                <x-atoms.table-heading align="center">Status</x-atoms.table-heading>
                <x-atoms.table-heading align="center">Aksi</x-atoms.table-heading>
            </x-slot:headings>

            @forelse ($orders as $order)
                @php
                    $accessionNumber = $order['accession_number'] ?? '-';
                    $noorder = $order['noorder'] ?? '-';
                    $nmPasien = $order['patient_name'] ?? '-';
                    $noRm = $order['patient_id'] ?? '-';
                    $noRawat = $order['no_rawat'] ?? '-';
                    $jenisPrw = $order['procedure_desc'] ?? '-';

                    $tglPerm = isset($order['scheduled_date'])
                        ? \Carbon\Carbon::parse($order['scheduled_date'])->format('d/m/Y H:i')
                        : '-';

                    $status = $order['status'] ?? null;
                    $aeTitle = $order['ae_title'] ?? '-';
                    $studyUid = $order['study_instance_uid'] ?? null;
                    $orthancId = $order['orthanc_study_id'] ?? null;
                    $modality = $order['modality'] ?? 'OT';
                @endphp
                <x-molecules.table-row>
                    <x-atoms.table-cell
                        class="font-mono text-zinc-700 dark:text-primary-dark-200">{{ $noorder }}</x-atoms.table-cell>
                    <x-atoms.table-cell
                        class="font-medium text-zinc-900 dark:text-primary-dark-100">{{ $nmPasien }}</x-atoms.table-cell>
                    <x-atoms.table-cell
                        class="font-mono text-zinc-600 dark:text-primary-dark-300">{{ $noRm }}</x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-zinc-600 dark:text-primary-dark-300">
                        <span class="block text-xs font-semibold">{{ $noRawat }}</span>
                        <flux:badge color="blue" size="sm" class="mt-1">{{ $modality }}</flux:badge>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell class="max-w-[180px] truncate"
                        title="{{ $jenisPrw }}">{{ $jenisPrw }}</x-atoms.table-cell>
                    <x-atoms.table-cell
                        class="text-zinc-600 dark:text-primary-dark-300">{{ $tglPerm }}</x-atoms.table-cell>
                    <x-atoms.table-cell class="font-mono text-xs text-zinc-600 dark:text-primary-dark-400">
                        {{ $aeTitle !== '-' ? $aeTitle : '-' }}
                    </x-atoms.table-cell>
                    <x-atoms.table-cell>
                        <div class="space-y-1">
                            <div class="flex items-center gap-1 text-[10px] text-zinc-500">
                                <flux:icon name="inbox-arrow-down" class="w-3 h-3" />
                                <span>{{ isset($order['created_at']) ? \Carbon\Carbon::parse($order['created_at'])->format('d/m H:i') : '—' }}</span>
                            </div>
                            <div class="flex items-center gap-1 text-[10px] text-teal-600 dark:text-teal-400">
                                <flux:icon name="check-badge" class="w-3 h-3" />
                                <span>{{ isset($order['sent_to_router_at']) ? \Carbon\Carbon::parse($order['sent_to_router_at'])->format('d/m H:i') : '—' }}</span>
                            </div>
                        </div>
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="center">
                        @include('pages.dicom.partials._dicom-status-badge', ['status' => $status])
                    </x-atoms.table-cell>
                    <x-atoms.table-cell align="center" action>
                        <x-atoms.button size="sm" variant="ghost" icon="arrow-path"
                            wire:click="checkStudyStatus('{{ $accessionNumber }}')" wire:loading.attr="disabled"
                            wire:target="checkStudyStatus('{{ $accessionNumber }}')" tooltip="Cek Study" />

                        <x-atoms.button size="sm" variant="ghost" icon="eye"
                            wire:click="viewDetail('{{ $accessionNumber }}')" tooltip="Detail Lengkap" />

                        @if ($studyUid && $orthancId && $viewerUrl)
                            <x-atoms.button size="sm" variant="ghost" icon="photo"
                                wire:click="openViewer('{{ $studyUid }}', '{{ $orthancId }}', '{{ $noorder }}')"
                                tooltip="Buka Viewer DICOM" />
                        @else
                            <x-atoms.button size="sm" variant="ghost" icon="photo" :disabled="true"
                                tooltip="Study belum tersedia di PACS" />
                        @endif
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @empty
                <x-molecules.table-row>
                    <x-atoms.table-cell colspan="10" align="center"
                        class="text-zinc-400 dark:text-primary-dark-500 py-12">
                        Tidak ada order radiologi pada tanggal yang dipilih.
                    </x-atoms.table-cell>
                </x-molecules.table-row>
            @endforelse
        </x-organisms.table>

        <div
            class="px-6 py-4 border-t border-zinc-100 dark:border-primary-dark-700 bg-zinc-50/50 dark:bg-primary-dark-800/20">
            <h4 class="text-xs font-semibold text-zinc-700 dark:text-primary-dark-300 mb-2">Keterangan Status DICOM:
            </h4>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[10px]">
                <div class="flex items-center gap-1.5 text-zinc-500 dark:text-primary-dark-400">
                    <span class="w-2 h-2 rounded-full bg-zinc-400"></span>
                    <span><strong>Worklist:</strong> Permintaan terkirim ke alat.</span>
                </div>
                <div class="flex items-center gap-1.5 text-zinc-500 dark:text-primary-dark-400">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    <span><strong>Received:</strong> Gambar sudah masuk ke PACS.</span>
                </div>
                <div class="flex items-center gap-1.5 text-zinc-500 dark:text-primary-dark-400">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    <span><strong>Sent:</strong> Order terkirim ke DICOM Router.</span>
                </div>
                <div class="flex items-center gap-1.5 text-zinc-500 dark:text-primary-dark-400">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    <span><strong>Error:</strong> Gagal kirim ke router.</span>
                </div>
            </div>
        </div>
    </x-organisms.data-panel>



    {{-- Modal Detail --}}
    <x-organisms.modal wire:model="showDetailModal" name="modal-worklist-detail" title="Detail Order DICOM"
        maxWidth="2xl">
        @if ($selectedOrder)
            <div class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div
                        class="p-3 bg-zinc-50 dark:bg-primary-dark-900/40 rounded-xl border border-zinc-100 dark:border-primary-dark-800">
                        <p class="text-[10px] uppercase font-bold text-zinc-400 mb-1">Informasi Order</p>
                        <dl class="space-y-1">
                            <div class="flex justify-between text-xs">
                                <dt class="text-zinc-500">No. Order:</dt>
                                <dd class="font-mono font-bold">{{ $selectedOrder['noorder'] ?? '-' }}</dd>
                            </div>
                            <div class="flex justify-between text-xs">
                                <dt class="text-zinc-500">No. Rawat:</dt>
                                <dd class="font-mono">{{ $selectedOrder['no_rawat'] ?? '-' }}</dd>
                            </div>
                            <div class="flex justify-between text-xs">
                                <dt class="text-zinc-500">Tanggal:</dt>
                                <dd class="font-medium">
                                    {{ isset($selectedOrder['scheduled_date']) ? \Carbon\Carbon::parse($selectedOrder['scheduled_date'])->format('d/m/Y H:i') : '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div
                        class="p-3 bg-zinc-50 dark:bg-primary-dark-900/40 rounded-xl border border-zinc-100 dark:border-primary-dark-800">
                        <p class="text-[10px] uppercase font-bold text-zinc-400 mb-1">Pasien</p>
                        <dl class="space-y-1">
                            <div class="flex justify-between text-xs">
                                <dt class="text-zinc-500">Nama:</dt>
                                <dd class="font-bold">
                                    {{ $selectedOrder['patient_name'] ?? '-' }}
                                </dd>
                            </div>
                            <div class="flex justify-between text-xs">
                                <dt class="text-zinc-500">No. RM:</dt>
                                <dd class="font-mono">
                                    {{ $selectedOrder['patient_id'] ?? '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div
                    class="p-4 bg-blue-50 dark:bg-blue-900/10 rounded-xl border border-blue-100 dark:border-blue-900/20">
                    <p class="text-[10px] uppercase font-bold text-blue-400 mb-2">Pemeriksaan & DICOM</p>
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">Jenis Pemeriksaan</p>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-primary-dark-100">
                                {{ $selectedOrder['procedure_desc'] ?? '-' }}
                            </p>
                        </div>
                        @if (isset($selectedOrder['study_instance_uid']))
                            <div class="grid grid-cols-2 gap-4 pt-3 border-t border-blue-100 dark:border-blue-900/20">
                                <div>
                                    <p class="text-[10px] text-zinc-500">Study Instance UID</p>
                                    <p class="text-[10px] font-mono break-all">
                                        {{ $selectedOrder['study_instance_uid'] }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-zinc-500">AE Title Target</p>
                                    <p class="text-xs font-mono font-bold">{{ $selectedOrder['ae_title'] ?? '-' }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal Response Cek Study --}}
    <x-organisms.modal wire:model="showCheckStudyModal" name="modal-check-study" title="Response Cek Study PACS"
        maxWidth="2xl">
        @if ($checkStudyResponse)
            <div class="space-y-4">
                @if (($checkStudyResponse['success'] ?? false) && ($checkStudyResponse['exists'] ?? false))
                    <div
                        class="p-3 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800 rounded-lg text-sm flex items-center gap-2">
                        <flux:icon name="check-circle" class="w-5 h-5" />
                        Study ditemukan di PACS.
                    </div>
                @else
                    <div
                        class="p-3 bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 rounded-lg text-sm flex items-center gap-2">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5" />
                        {{ $checkStudyResponse['message'] ?? 'Study belum tersedia atau terjadi kesalahan.' }}
                    </div>
                @endif

                <x-atoms.code-block language="json"
                    maxHeight="max-h-96">{{ json_encode($checkStudyResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</x-atoms.code-block>
            </div>
        @endif
        <x-slot:footer>
            <div class="flex justify-end">
                <x-atoms.button variant="primary"
                    wire:click="$set('showCheckStudyModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>

    {{-- Modal DICOM Viewer --}}
    <x-organisms.modal wire:model="showViewerModal" name="modal-dicom-viewer" :title="'Viewer DICOM — ' . $viewerLabel" maxWidth="full">
        @if ($showViewerModal && $viewerIframeUrl)
            <div class="relative w-full" style="height: 75dvh;" x-data="{ blocked: false }">
                <iframe src="{{ $viewerIframeUrl }}"
                    class="w-full h-full rounded-lg border border-zinc-200 dark:border-primary-dark-700"
                    allowfullscreen x-on:error="blocked = true" x-on:load="blocked = false">
                </iframe>

                {{-- Fallback jika iframe diblokir --}}
                <div x-show="blocked" x-cloak
                    class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-zinc-50 dark:bg-primary-dark-900 rounded-lg border border-zinc-200 dark:border-primary-dark-700">
                    <flux:icon name="exclamation-triangle" class="w-10 h-10 text-amber-400" />
                    <div class="text-center space-y-1">
                        <p class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-200">
                            Viewer tidak dapat ditampilkan di dalam modal
                        </p>
                        <p class="text-xs text-zinc-400 dark:text-primary-dark-500">
                            Server DICOM memblokir embedding. Buka di tab baru.
                        </p>
                    </div>
                    <x-atoms.button icon="arrow-top-right-on-square" href="{{ $viewerIframeUrl }}" target="_blank"
                        :navigate="false">
                        Buka di Tab Baru
                    </x-atoms.button>
                </div>
            </div>
        @endif

        <x-slot:footer>
            <div class="flex items-center justify-between">
                <x-atoms.button variant="ghost" icon="arrow-top-right-on-square" href="{{ $viewerIframeUrl }}"
                    target="_blank" :navigate="false">
                    Buka di Tab Baru
                </x-atoms.button>
                <x-atoms.button variant="ghost" wire:click="$set('showViewerModal', false)">Tutup</x-atoms.button>
            </div>
        </x-slot:footer>
    </x-organisms.modal>
</div>
