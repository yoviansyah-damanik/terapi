<?php

use App\Models\SimrsSlide;
use App\Models\SimrsVersion;
use App\Services\SimrsVersionService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new #[Layout('layouts::app')] #[Title('Manajemen Versi SIMRS')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'main';

    // ── Form modal (tambah / edit) ─────────────────────────────────────────
    public bool $showFormModal = false;
    public ?int $editVersionId = null;
    public string $newVersionType = 'main'; // di-set saat openForm berdasarkan tab aktif
    public string $version = '';
    public string $notes = '';
    public string $released_at = '';

    // Diisi oleh JS setelah chunked upload selesai
    public string $uploadedPath = '';
    public string $uploadedName = '';
    public int $uploadedSize = 0;

    // ── Confirm modal (activate / deactivate / delete) ────────────────────
    public bool $showConfirmModal = false;
    public string $confirmAction = ''; // 'activate' | 'deactivate' | 'delete'
    public int $confirmId = 0;
    public string $confirmVersion = '';

    // ── Detail modal ──────────────────────────────────────────────────────
    public bool $showDetailModal = false;
    public ?int $detailId = null;

    // ── Slide ────────────────────────────────────────────────────────────
    public bool $showSlideModal = false;
    public ?string $editSlideId = null;
    public string $slideTitle = '';
    public string $slideHref = '';
    public int $slideSortOrder = 0;
    public string $slideUploadedPath = '';
    public string $slideUploadedName = '';
    public int $slideUploadedSize = 0;
    public string $slideUploadedMime = '';

    public bool $showSlideConfirmModal = false;
    public ?string $slideConfirmId = null;
    public string $slideConfirmAction = ''; // 'delete' | 'deactivate'

    /** Buka modal dalam mode tambah */
    public function openForm(): void
    {
        $this->reset(['editVersionId', 'version', 'notes', 'released_at', 'uploadedPath', 'uploadedName', 'uploadedSize']);
        $this->newVersionType = $this->tab === 'launcher' ? 'launcher' : 'main';
        $this->resetValidation();
        $this->showFormModal = true;
        $this->dispatch('reset-uploader');
    }

    /** Buka modal dalam mode edit — hanya notes & released_at yang bisa diubah */
    public function openEdit(int $id): void
    {
        $record = SimrsVersion::findOrFail($id);

        $this->editVersionId = $id;
        $this->version = $record->version;
        $this->notes = $record->notes ?? '';
        $this->released_at = $record->released_at->format('Y-m-d');
        $this->resetValidation();
        $this->showFormModal = true;
    }

    /** Dipanggil oleh JS setelah semua chunk berhasil dirakit */
    public function setUploadedFile(string $path, string $name, int $size): void
    {
        $this->uploadedPath = $path;
        $this->uploadedName = $name;
        $this->uploadedSize = $size;
    }

    /** Dipanggil oleh JS saat user klik "Ganti file" */
    public function clearUploadedFile(): void
    {
        $this->uploadedPath = '';
        $this->uploadedName = '';
        $this->uploadedSize = 0;
    }

    public function save(SimrsVersionService $service): void
    {
        if ($this->editVersionId) {
            // ── Mode edit: hanya notes + released_at ──────────────────────
            $this->validate([
                'notes' => 'nullable|string|max:2000',
                'released_at' => 'required|date',
            ]);

            SimrsVersion::findOrFail($this->editVersionId)->update([
                'notes' => $this->notes,
                'released_at' => $this->released_at,
            ]);

            $ver = $this->version;
            $this->showFormModal = false;
            $this->dispatch('toast', type: 'success', message: "Versi {$ver} berhasil diperbarui.");
        } else {
            // ── Mode tambah: semua field wajib ────────────────────────────
            $uploadedPath     = $this->uploadedPath;
            $newVersionType   = $this->newVersionType;
            $this->validate([
                'version' => [
                    'required',
                    'regex:/^\d+\.\d+\.\d+$/',
                    \Illuminate\Validation\Rule::unique('simrs_versions', 'version')->where('type', $newVersionType),
                ],
                'notes' => 'nullable|string|max:2000',
                'released_at' => 'required|date',
                'uploadedPath' => [
                    'required',
                    function ($attr, $value, $fail) {
                        if (!$value || !file_exists($value)) {
                            $fail('File belum diunggah atau sudah kedaluwarsa.');
                        }
                    },
                ],
            ]);

            $service->create(
                [
                    'type'        => $this->newVersionType,
                    'version'     => $this->version,
                    'notes'       => $this->notes,
                    'released_at' => $this->released_at,
                ],
                $uploadedPath,
            );

            $ver = $this->version;
            $this->reset(['editVersionId', 'version', 'notes', 'released_at', 'uploadedPath', 'uploadedName', 'uploadedSize']);
            $this->showFormModal = false;
            $this->resetPage();
            $this->dispatch('toast', type: 'success', message: "Versi {$ver} berhasil diunggah.");
        }
    }

    public function openConfirm(string $action, int $id, string $version): void
    {
        $this->confirmAction = $action;
        $this->confirmId = $id;
        $this->confirmVersion = $version;
        $this->showConfirmModal = true;
    }

    public function executeConfirm(SimrsVersionService $service): void
    {
        $record = SimrsVersion::findOrFail($this->confirmId);
        $this->showConfirmModal = false;

        match ($this->confirmAction) {
            'activate' => $this->doActivate($record, $service),
            'deactivate' => $this->doDeactivate($record),
            'delete' => $this->doDelete($record, $service),
            default => null,
        };
    }

    private function doActivate(SimrsVersion $record, SimrsVersionService $service): void
    {
        $service->setActive($record);
        $this->dispatch('toast', type: 'success', message: "Versi {$record->version} sekarang aktif.");
    }

    private function doDeactivate(SimrsVersion $record): void
    {
        $record->update(['is_active' => false]);
        $this->dispatch('toast', type: 'success', message: "Versi {$record->version} dinonaktifkan.");
    }

    private function doDelete(SimrsVersion $record, SimrsVersionService $service): void
    {
        if ($record->is_active) {
            $this->dispatch('toast', type: 'error', message: 'Versi aktif tidak dapat dihapus.');
            return;
        }

        $service->delete($record);
        $this->dispatch('toast', type: 'success', message: "Versi {$record->version} berhasil dihapus.");
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->showDetailModal = true;
    }

    public function downloadFile(int $id): BinaryFileResponse
    {
        $record = SimrsVersion::findOrFail($id);
        $fullPath = Storage::disk('simrs_updates')->path($record->file_path);

        abort_unless($record->file_path && file_exists($fullPath), 404, 'File tidak tersedia di server.');

        return response()->download($fullPath, "simrs-{$record->type}-{$record->version}.zip", [
            'Content-Type'      => 'application/zip',
            'X-Checksum-SHA256' => $record->checksum ?? '',
        ]);
    }

    // ── Slide methods ────────────────────────────────────────────────────

    public function openSlideCreate(): void
    {
        $this->editSlideId = null;
        $this->slideTitle = '';
        $this->slideHref = '';
        $this->slideSortOrder = 0;
        $this->slideUploadedPath = '';
        $this->slideUploadedName = '';
        $this->slideUploadedSize = 0;
        $this->slideUploadedMime = '';
        $this->resetValidation();
        $this->showSlideModal = true;
        $this->dispatch('reset-slide-uploader');
    }

    public function openSlideEdit(string $id): void
    {
        $slide = SimrsSlide::findOrFail($id);
        $this->editSlideId = $id;
        $this->slideTitle = $slide->title;
        $this->slideHref = $slide->href ?? '';
        $this->slideSortOrder = $slide->sort_order;
        $this->slideUploadedPath = '';
        $this->resetValidation();
        $this->showSlideModal = true;
    }

    public function setSlideUploadedFile(string $path, string $name, int $size, string $mime): void
    {
        $this->slideUploadedPath = $path;
        $this->slideUploadedName = $name;
        $this->slideUploadedSize = $size;
        $this->slideUploadedMime = $mime;
    }

    public function clearSlideUploadedFile(): void
    {
        $this->slideUploadedPath = '';
        $this->slideUploadedName = '';
        $this->slideUploadedSize = 0;
        $this->slideUploadedMime = '';
    }

    public function saveSlide(): void
    {
        if ($this->editSlideId) {
            $this->validate([
                'slideTitle' => 'required|string|max:200',
                'slideSortOrder' => 'integer|min:0',
            ]);

            $slide = SimrsSlide::findOrFail($this->editSlideId);

            // Ganti gambar jika ada upload baru
            if ($this->slideUploadedPath && file_exists($this->slideUploadedPath)) {
                $ext = pathinfo($this->slideUploadedName, PATHINFO_EXTENSION);
                $dest = "{$slide->id}.{$ext}";
                $disk = Storage::disk('simrs_slides');
                rename($this->slideUploadedPath, $disk->path($dest));

                $slide->update([
                    'title' => $this->slideTitle,
                    'href' => $this->slideHref ?: null,
                    'sort_order' => $this->slideSortOrder,
                    'file_path' => $dest,
                    'mime_type' => $this->slideUploadedMime ?: "image/{$ext}",
                    'file_size' => $this->slideUploadedSize,
                ]);
            } else {
                $slide->update([
                    'title' => $this->slideTitle,
                    'href' => $this->slideHref ?: null,
                    'sort_order' => $this->slideSortOrder,
                ]);
            }

            $this->showSlideModal = false;
            $this->dispatch('toast', type: 'success', message: 'Slide berhasil diperbarui.');
        } else {
            $uploadedPath = $this->slideUploadedPath;
            $this->validate([
                'slideTitle' => 'required|string|max:200',
                'slideSortOrder' => 'integer|min:0',
                'slideUploadedPath' => [
                    'required',
                    function ($attr, $value, $fail) {
                        if (!$value || !file_exists($value)) {
                            $fail('File slide belum diunggah atau sudah kedaluwarsa.');
                        }
                    },
                ],
            ]);

            $slide = SimrsSlide::create([
                'title' => $this->slideTitle,
                'href' => $this->slideHref ?: null,
                'sort_order' => $this->slideSortOrder,
                'file_path' => '', // sementara, diisi setelah rename
                'mime_type' => $this->slideUploadedMime,
                'file_size' => $this->slideUploadedSize,
                'is_active' => true,
            ]);

            $ext = pathinfo($this->slideUploadedName, PATHINFO_EXTENSION);
            $dest = "{$slide->id}.{$ext}";
            $disk = Storage::disk('simrs_slides');

            if (!is_dir(dirname($disk->path($dest)))) {
                mkdir(dirname($disk->path($dest)), 0755, true);
            }

            rename($uploadedPath, $disk->path($dest));
            $slide->update(['file_path' => $dest]);

            $this->showSlideModal = false;
            $this->reset(['slideTitle', 'slideHref', 'slideSortOrder', 'slideUploadedPath', 'slideUploadedName', 'slideUploadedSize', 'slideUploadedMime']);
            $this->dispatch('toast', type: 'success', message: 'Slide berhasil diunggah.');
        }
    }

    public function openSlideConfirm(string $action, string $id): void
    {
        $this->slideConfirmAction = $action;
        $this->slideConfirmId = $id;
        $this->showSlideConfirmModal = true;
    }

    public function executeSlideConfirm(): void
    {
        $slide = SimrsSlide::findOrFail($this->slideConfirmId);
        $this->showSlideConfirmModal = false;

        if ($this->slideConfirmAction === 'delete') {
            Storage::disk('simrs_slides')->delete($slide->file_path);
            $slide->delete();
            $this->dispatch('toast', type: 'success', message: 'Slide berhasil dihapus.');
        } elseif ($this->slideConfirmAction === 'deactivate') {
            $slide->update(['is_active' => false]);
            $this->dispatch('toast', type: 'success', message: 'Slide dinonaktifkan.');
        }
    }

    public function toggleSlideActive(string $id): void
    {
        $slide = SimrsSlide::findOrFail($id);
        $slide->update(['is_active' => !$slide->is_active]);
        $status = $slide->is_active ? 'diaktifkan' : 'dinonaktifkan';
        $this->dispatch('toast', type: 'success', message: "Slide berhasil {$status}.");
    }

    // ─────────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $versionType = in_array($this->tab, ['main', 'launcher']) ? $this->tab : 'main';
        $versions    = SimrsVersion::ofType($versionType)->orderByDesc('released_at')->paginate(15);
        $detail      = $this->detailId ? SimrsVersion::find($this->detailId) : null;

        $reportBase = \App\Models\SimrsUpdateReport::where('created_at', '>=', now()->subDays(30));
        if (in_array($this->tab, ['main', 'launcher'])) {
            $reportBase->where(fn($q) => $q->where('type', $this->tab)->orWhereNull('type'));
        }
        $reportStats = [
            'total'   => (clone $reportBase)->count(),
            'success' => (clone $reportBase)->success()->count(),
            'failed'  => (clone $reportBase)->failed()->count(),
            'recent'  => \App\Models\SimrsUpdateReport::when(
                in_array($this->tab, ['main', 'launcher']),
                fn($q) => $q->where(fn($q2) => $q2->where('type', $this->tab)->orWhereNull('type'))
            )->orderByDesc('created_at')->limit(5)->get(),
        ];
        $slides = SimrsSlide::ordered()->get();

        return compact('versions', 'detail', 'reportStats', 'slides');
    }
};
?>

<div>
    <x-ui.page-header title="Manajemen Versi SIMRS"
        subtitle="Kelola file update SIMRS — unggah, aktifkan, dan distribusikan ke klien melalui API">
        <x-slot:actions>
            @if ($tab === 'main')
                <x-atoms.button wire:click="openForm" variant="primary" icon="arrow-up-tray">
                    Unggah Versi Main
                </x-atoms.button>
            @elseif ($tab === 'launcher')
                <x-atoms.button wire:click="openForm" variant="primary" icon="arrow-up-tray">
                    Unggah Versi Launcher
                </x-atoms.button>
            @elseif ($tab === 'slides')
                <x-atoms.button wire:click="openSlideCreate" variant="primary" icon="plus">
                    Unggah Slide
                </x-atoms.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Tabs ──────────────────────────────────────────────────────────────── --}}
    <x-molecules.tabs>

        <x-atoms.tab-item wire:click="$set('tab', 'main')" :active="$tab === 'main'">Main SIMRS</x-atoms.tab-item>
        <x-atoms.tab-item wire:click="$set('tab', 'launcher')" :active="$tab === 'launcher'">Launcher</x-atoms.tab-item>
        <x-atoms.tab-item wire:click="$set('tab', 'slides')" :active="$tab === 'slides'">Slide
            @if ($slides->count())
                <span
                    class="ml-1.5 inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium
                        {{ $tab === 'slides' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300' : 'bg-zinc-100 text-zinc-500 dark:bg-primary-dark-700 dark:text-primary-dark-400' }}">
                    {{ $slides->where('is_active', true)->count() }}
                </span>
            @endif
        </x-atoms.tab-item>

    </x-molecules.tabs>

    @if (in_array($tab, ['main', 'launcher']))
        {{-- Tabel Versi ───────────────────────────────────────────────────────── --}}
        <div
            class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm dark:border-primary-dark-700/60 dark:bg-primary-dark-800">
            <div
                class="flex items-center justify-between border-b border-zinc-100 px-5 py-3.5 dark:border-primary-dark-700/60">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">
                        Daftar Versi {{ $tab === 'launcher' ? 'Launcher' : 'Main SIMRS' }}
                    </h3>
                    @if ($versions->total() > 0)
                        <flux:badge color="zinc" size="sm">{{ $versions->total() }}</flux:badge>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-100 dark:border-primary-dark-700">
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-primary-dark-400">
                                Versi
                            </th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-primary-dark-400">
                                Status
                            </th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-primary-dark-400">
                                Ukuran
                            </th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-primary-dark-400">
                                Checksum
                                (SHA-256)</th>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold text-zinc-500 dark:text-primary-dark-400">
                                Tanggal
                                Rilis</th>
                            <th
                                class="px-5 py-3 text-right text-xs font-semibold text-zinc-500 dark:text-primary-dark-400">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-50 dark:divide-primary-dark-700/50">
                        @forelse ($versions as $v)
                            <tr @class([
                                'transition-colors hover:bg-zinc-50 dark:hover:bg-primary-dark-700/30',
                                'bg-emerald-50/40 dark:bg-emerald-900/10' => $v->is_active,
                            ])>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="font-mono text-sm font-bold text-zinc-800 dark:text-primary-dark-100">
                                            v{{ $v->version }}
                                        </span>
                                        @if ($v->notes)
                                            <button wire:click="openDetail({{ $v->id }})"
                                                class="text-zinc-300 hover:text-zinc-500 dark:text-primary-dark-600 dark:hover:text-primary-dark-400 transition-colors">
                                                <flux:icon name="information-circle" class="size-3.5" />
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($v->is_active)
                                        <flux:badge color="emerald" size="sm" icon="check-circle">AKTIF
                                        </flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">Tidak Aktif</flux:badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-zinc-600 dark:text-primary-dark-300">
                                    {{ $v->file_size_label }}
                                </td>
                                <td class="px-5 py-3">
                                    @if ($v->checksum)
                                        <span class="font-mono text-xs text-zinc-500 dark:text-primary-dark-400"
                                            title="{{ $v->checksum }}">
                                            {{ $v->checksum }}
                                        </span>
                                    @else
                                        <span class="text-xs text-zinc-300 dark:text-primary-dark-600">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-zinc-600 dark:text-primary-dark-300">
                                    {{ $v->released_at->format('d M Y') }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($v->is_active)
                                            <x-atoms.button
                                                wire:click="openConfirm('deactivate', {{ $v->id }}, '{{ $v->version }}')"
                                                size="xs" variant="ghost" icon="pause-circle">
                                                Nonaktifkan
                                            </x-atoms.button>
                                        @else
                                            <x-atoms.button
                                                wire:click="openConfirm('activate', {{ $v->id }}, '{{ $v->version }}')"
                                                size="xs" variant="ghost" icon="bolt">
                                                Aktifkan
                                            </x-atoms.button>
                                        @endif
                                        @if ($v->file_path)
                                            <x-atoms.button wire:click="downloadFile({{ $v->id }})"
                                                size="xs" variant="ghost" icon="arrow-down-tray">
                                                Unduh
                                            </x-atoms.button>
                                        @endif
                                        <x-atoms.button wire:click="openEdit({{ $v->id }})" size="xs"
                                            variant="ghost" icon="pencil-square">
                                            Edit
                                        </x-atoms.button>
                                        <x-atoms.button
                                            wire:click="openConfirm('delete', {{ $v->id }}, '{{ $v->version }}')"
                                            size="xs" variant="ghost" icon="trash" :disabled="$v->is_active">
                                            Hapus
                                        </x-atoms.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <flux:icon name="archive-box"
                                            class="size-10 text-zinc-200 dark:text-primary-dark-700" />
                                        <p class="text-sm text-zinc-400">Belum ada versi yang diunggah.</p>
                                        <x-atoms.button wire:click="openForm" size="sm" variant="ghost"
                                            icon="plus">
                                            Unggah Versi Pertama
                                        </x-atoms.button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($versions->hasPages())
                <div class="border-t border-zinc-100 dark:border-primary-dark-700 px-5 py-3">
                    {{ $versions->links() }}
                </div>
            @endif
        </div>

        {{-- Update Report Summary ─────────────────────────────────────────────── --}}
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            @foreach ([['label' => 'Total Request', 'value' => $reportStats['total'], 'color' => 'zinc', 'icon' => 'arrow-path'], ['label' => 'Berhasil', 'value' => $reportStats['success'], 'color' => 'emerald', 'icon' => 'check-circle'], ['label' => 'Gagal', 'value' => $reportStats['failed'], 'color' => 'red', 'icon' => 'x-circle']] as $stat)
                <div
                    class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm dark:border-primary-dark-700/60 dark:bg-primary-dark-800 px-5 py-4">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-zinc-500 dark:text-primary-dark-400">{{ $stat['label'] }} <span
                                class="text-zinc-400">(30 hari)</span></p>
                        <flux:icon name="{{ $stat['icon'] }}" class="size-4 text-{{ $stat['color'] }}-400" />
                    </div>
                    <p class="mt-1.5 text-2xl font-bold text-zinc-800 dark:text-primary-dark-100">
                        {{ number_format($stat['value']) }}</p>
                </div>
            @endforeach
        </div>

        {{-- Recent Update Reports ─────────────────────────────────────────────── --}}
        @if ($reportStats['recent']->isNotEmpty())
            <div
                class="mt-4 overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm dark:border-primary-dark-700/60 dark:bg-primary-dark-800">
                <div
                    class="flex items-center justify-between border-b border-zinc-100 px-5 py-3.5 dark:border-primary-dark-700/60">
                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-primary-dark-300">Laporan Update Terbaru
                    </h3>
                    <a wire:navigate
                        href="{{ route('api-portal.documentation', ['activeSection' => 'simrs-update-overview', 'tab' => 'simrs-update']) }}#simrs-update"
                        class="flex items-center gap-1.5 text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors">
                        <flux:icon name="book-open" class="size-3.5" />
                        Dokumentasi API
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-50 dark:border-primary-dark-700/50">
                                <th class="px-5 py-2.5 text-left text-xs font-semibold text-zinc-500">Status</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold text-zinc-500">Dari → Ke</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold text-zinc-500">Host / Klien</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold text-zinc-500">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-50 dark:divide-primary-dark-700/50">
                            @foreach ($reportStats['recent'] as $r)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-primary-dark-700/30 transition-colors">
                                    <td class="px-5 py-2.5">
                                        @if ($r->status === 'success')
                                            <flux:badge color="emerald" size="sm">Berhasil</flux:badge>
                                        @elseif ($r->status === 'failed')
                                            <flux:badge color="red" size="sm">Gagal</flux:badge>
                                        @else
                                            <flux:badge color="amber" size="sm">Rollback</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5 font-mono text-xs text-zinc-600 dark:text-primary-dark-300">
                                        {{ $r->from_version ?? '—' }} → {{ $r->to_version ?? '—' }}
                                    </td>
                                    <td class="px-5 py-2.5 text-xs text-zinc-500">
                                        {{ $r->host_name ?? $r->ip_address }}
                                        @if ($r->app_name)
                                            <span class="text-zinc-400">({{ $r->app_name }})</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5 text-xs text-zinc-400">
                                        {{ $r->created_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            {{-- Kosong: tampilkan shortcut ke dokumentasi API --}}
            <div
                class="mt-4 flex items-center justify-between rounded-2xl border border-zinc-200/80 bg-white shadow-sm dark:border-primary-dark-700/60 dark:bg-primary-dark-800 px-5 py-4">
                <div>
                    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Belum ada laporan update
                        masuk</p>
                    <p class="text-xs text-zinc-400 mt-0.5">Integrasikan SIMRS menggunakan panduan di halaman
                        dokumentasi
                        API</p>
                </div>
                <a href="{{ route('api-portal.documentation', ['activeSection' => 'simrs-update-overview', 'tab' => 'simrs-update']) }}#simrs-update"
                    wire:navigate>
                    <x-atoms.button variant="ghost" icon="book-open" size="sm">Dokumentasi API</x-atoms.button>
                </a>
            </div>
        @endif

    @endif {{-- end in_array($tab, ['main', 'launcher']) --}}

    {{-- Tab Slide ────────────────────────────────────────────────────────── --}}
    @if ($tab === 'slides')
        <p class="mb-5 text-sm text-zinc-500 dark:text-primary-dark-400">
            Slide ditampilkan di SIMRS dengan rasio <span
                class="font-semibold text-zinc-700 dark:text-primary-dark-200">3:4</span> (portrait).
            Format: JPG, PNG, WebP.
        </p>

        @if ($slides->isEmpty())
            <div
                class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-zinc-200 dark:border-primary-dark-700 py-16">
                <flux:icon name="photo" class="size-10 text-zinc-300 dark:text-primary-dark-600" />
                <p class="mt-2 text-sm text-zinc-400">Belum ada slide yang diunggah.</p>
                <x-atoms.button wire:click="openSlideCreate" size="sm" variant="ghost" icon="plus"
                    class="mt-3">
                    Unggah Slide Pertama
                </x-atoms.button>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                @foreach ($slides as $slide)
                    <div wire:key="slide-{{ $slide->id }}" class="group relative">
                        {{-- Card dengan aspect-ratio 3:4 --}}
                        <div class="relative overflow-hidden rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-zinc-100 dark:bg-primary-dark-800 shadow-sm"
                            style="aspect-ratio: 3/4;">
                            {{-- Gambar preview langsung dari storage (inline base64) --}}
                            @php
                                $disk = \Illuminate\Support\Facades\Storage::disk('simrs_slides');
                                $imgBase64 = $disk->exists($slide->file_path)
                                    ? 'data:' .
                                        ($slide->mime_type ?: 'image/jpeg') .
                                        ';base64,' .
                                        base64_encode($disk->get($slide->file_path))
                                    : null;
                            @endphp
                            @if ($imgBase64)
                                <img src="{{ $imgBase64 }}" alt="{{ $slide->title }}"
                                    class="absolute inset-0 w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
                            @else
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <flux:icon name="photo" class="size-8 text-zinc-400" />
                                </div>
                            @endif

                            {{-- Overlay saat inactive --}}
                            @if (!$slide->is_active)
                                <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                                    <flux:badge color="zinc" size="sm">Nonaktif</flux:badge>
                                </div>
                            @endif

                            {{-- Active badge --}}
                            @if ($slide->is_active)
                                <div class="absolute top-2 left-2 flex items-center gap-1">
                                    <flux:badge color="emerald" size="sm">Aktif</flux:badge>
                                    @if ($slide->href)
                                        <span title="{{ $slide->href }}"
                                            class="inline-flex items-center justify-center rounded-full bg-black/50 p-0.5">
                                            <flux:icon name="link" class="size-3 text-white" />
                                        </span>
                                    @endif
                                </div>
                            @endif

                            {{-- Sort order badge --}}
                            <div class="absolute top-2 right-2">
                                <span
                                    class="inline-flex items-center rounded-full bg-black/50 px-1.5 py-0.5 text-[10px] font-medium text-white">
                                    #{{ $slide->sort_order }}
                                </span>
                            </div>

                            {{-- Action overlay --}}
                            <div
                                class="absolute inset-x-0 bottom-0 translate-y-full group-hover:translate-y-0 transition-transform duration-200 bg-gradient-to-t from-black/80 to-transparent p-2 flex items-end justify-end gap-1">
                                <x-atoms.button size="xs" variant="ghost"
                                    wire:click="openSlideEdit('{{ $slide->id }}')"
                                    class="!text-white !border-white/30 hover:!bg-white/20" icon="pencil-square"
                                    title="Edit" />
                                <x-atoms.button size="xs" variant="ghost"
                                    wire:click="toggleSlideActive('{{ $slide->id }}')"
                                    class="!text-white !border-white/30 hover:!bg-white/20"
                                    icon="{{ $slide->is_active ? 'eye-slash' : 'eye' }}"
                                    title="{{ $slide->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" />
                                <x-atoms.button size="xs" variant="ghost"
                                    wire:click="openSlideConfirm('delete', '{{ $slide->id }}')"
                                    class="!text-red-300 !border-white/30 hover:!bg-white/20" icon="trash"
                                    title="Hapus" />
                            </div>
                        </div>

                        {{-- Info bawah card --}}
                        <div class="mt-1.5 px-0.5">
                            <p class="text-xs font-medium text-zinc-700 dark:text-primary-dark-300 truncate"
                                title="{{ $slide->title }}">
                                {{ $slide->title }}
                            </p>
                            <p class="text-[10px] text-zinc-400 dark:text-primary-dark-500">
                                {{ $slide->file_size_label }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Info API --}}
        <div
            class="mt-6 rounded-2xl border border-zinc-200/80 bg-white shadow-sm dark:border-primary-dark-700/60 dark:bg-primary-dark-800 px-5 py-4">
            <div class="flex items-start gap-3">
                <flux:icon name="information-circle" class="size-4 text-zinc-400 mt-0.5 shrink-0" />
                <div>
                    <p class="text-sm font-medium text-zinc-700 dark:text-primary-dark-300">Akses Slide via API</p>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-primary-dark-400">
                        SIMRS dapat mengambil daftar slide aktif melalui
                        <code class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-[11px]">GET
                            {{ url('/api/simrs/launcher/slides') }}</code>
                        menggunakan scope <code
                            class="px-1 py-0.5 rounded bg-zinc-100 dark:bg-primary-dark-700 text-[11px]">update-simrs</code>.
                    </p>
                </div>
                <a href="{{ route('api-portal.documentation', ['tab' => 'simrs-update', 'activeSection' => 'simrs-slide-list']) }}"
                    wire:navigate class="ml-auto">
                    <x-atoms.button size="xs" variant="ghost" icon="book-open">Dokumentasi</x-atoms.button>
                </a>
            </div>
        </div>
    @endif

    {{-- Modal Tambah / Edit Slide ───────────────────────────────────────── --}}
    <x-organisms.modal wire:model="showSlideModal" maxWidth="lg" title="">
        <div class="space-y-5">
            <div class="flex items-center gap-3">
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="photo" class="size-4 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <flux:heading size="sm">{{ $editSlideId ? 'Edit Slide' : 'Unggah Slide' }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-400">Rasio 3:4 (portrait) — JPG, PNG, WebP</flux:text>
                </div>
            </div>

            <div>
                <flux:label>Judul Slide <span class="text-red-400">*</span></flux:label>
                <flux:input wire:model="slideTitle" placeholder="Promo Kesehatan Maret 2026" />
                <flux:error name="slideTitle" />
            </div>

            <div>
                <flux:label>Link Tujuan (href)</flux:label>
                <flux:input wire:model="slideHref" placeholder="https://example.com/promo" type="url" />
                <flux:description>Opsional — jika diisi, SIMRS akan membuka URL ini saat slide diklik.
                </flux:description>
                <flux:error name="slideHref" />
            </div>

            <div>
                <flux:label>Urutan Tampil</flux:label>
                <flux:input type="number" wire:model="slideSortOrder" min="0" placeholder="0" />
                <p class="mt-1 text-[10px] text-zinc-400">Angka lebih kecil ditampilkan lebih dahulu</p>
            </div>

            {{-- Chunked image uploader --}}
            <div x-data="{
                dragging: false,
                state: '{{ $editSlideId && !$slideUploadedPath ? 'existing' : 'idle' }}',
                progress: 0,
                fileName: '',
                previewUrl: '',
                errorMsg: '',
                mimeMap: { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp' },
                handleDrop(e) {
                    this.dragging = false;
                    const f = e.dataTransfer.files[0];
                    if (f) this.startUpload(f);
                },
                handleInput(e) {
                    const f = e.target.files[0];
                    if (f) this.startUpload(f);
                },
                async startUpload(file) {
                    const ext = file.name.split('.').pop().toLowerCase();
                    if (!['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                        this.errorMsg = 'Hanya JPG, PNG, dan WebP yang diperbolehkan.';
                        this.state = 'error';
                        return;
                    }
                    const CHUNK = 2 * 1024 * 1024;
                    const total = Math.ceil(file.size / CHUNK);
                    const uid = crypto.randomUUID().replace(/-/g, '');
                    const token = '{{ csrf_token() }}';
                    this.state = 'uploading';
                    this.progress = 0;
                    this.fileName = file.name;
                    this.errorMsg = '';
                    // Preview lokal
                    this.previewUrl = URL.createObjectURL(file);
                    try {
                        for (let i = 0; i < total; i++) {
                            const form = new FormData();
                            form.append('upload_id', uid);
                            form.append('chunk_index', i);
                            form.append('total_chunks', total);
                            form.append('filename', file.name);
                            form.append('chunk', file.slice(i * CHUNK, (i + 1) * CHUNK));
                            form.append('_token', token);
                            const res = await fetch('/upload/chunk', { method: 'POST', body: form });
                            if (!res.ok) {
                                const d = await res.json().catch(() => ({}));
                                throw new Error(d.message || 'Gagal mengirim bagian ' + (i + 1));
                            }
                            this.progress = Math.round((i + 1) / total * 100);
                            if (i === total - 1) {
                                const data = await res.json();
                                if (data.done) {
                                    const mime = this.mimeMap[ext] || 'image/jpeg';
                                    $wire.setSlideUploadedFile(data.path, file.name, file.size, mime);
                                    this.state = 'done';
                                }
                            }
                        }
                    } catch (err) {
                        this.errorMsg = err.message || 'Upload gagal, coba lagi.';
                        this.state = 'error';
                        this.previewUrl = '';
                    }
                },
                resetUpload() {
                    this.state = 'idle';
                    this.progress = 0;
                    this.fileName = '';
                    this.previewUrl = '';
                    this.errorMsg = '';
                    $wire.call('clearSlideUploadedFile');
                }
            }" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                @drop.prevent="handleDrop($event)" @reset-slide-uploader.window="resetUpload()">
                <flux:label>
                    File Gambar
                    @if (!$editSlideId)
                        <span class="text-red-400">*</span>
                    @endif
                </flux:label>

                {{-- Existing (edit tanpa ganti file) --}}
                <div x-show="state === 'existing'" x-cloak
                    class="mt-1 flex items-center justify-between rounded-xl border border-zinc-200 dark:border-primary-dark-600 bg-zinc-50 dark:bg-primary-dark-700/40 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <flux:icon name="photo" class="size-4 text-zinc-400" />
                        <span class="text-sm text-zinc-600 dark:text-primary-dark-300">Gambar sudah ada</span>
                    </div>
                    <button type="button" @click="state = 'idle'"
                        class="text-xs text-primary-500 hover:text-primary-700 transition-colors">
                        Ganti gambar
                    </button>
                </div>

                {{-- Drop zone --}}
                <div x-show="state === 'idle' || state === 'error'"
                    class="mt-1 rounded-xl border-2 border-dashed transition-all overflow-hidden"
                    :class="dragging
                        ?
                        'border-primary-400 bg-primary-50/60 dark:border-primary-500 dark:bg-primary-900/20' :
                        'border-zinc-300 bg-zinc-50 hover:border-primary-400 hover:bg-primary-50/30 dark:border-primary-dark-600 dark:bg-primary-dark-700/30 dark:hover:border-primary-500'">
                    <label class="flex cursor-pointer flex-col items-center gap-2 px-6 py-6">
                        <span :class="dragging ? 'text-primary-400' : 'text-zinc-300 dark:text-primary-dark-500'">
                            <flux:icon name="photo" class="size-7 transition-colors" />
                        </span>
                        <div class="text-center">
                            <p x-show="!dragging"
                                class="text-sm font-medium text-zinc-600 dark:text-primary-dark-300">
                                Seret gambar ke sini atau <span class="text-primary-500">klik untuk memilih</span>
                            </p>
                            <p x-show="dragging" x-cloak class="text-sm font-semibold text-primary-600">Lepaskan untuk
                                mengunggah</p>
                            <p x-show="!dragging" class="text-xs text-zinc-400">JPG, PNG, WebP — Rasio 3:4 (portrait)
                            </p>
                        </div>
                        <input @change="handleInput($event)" type="file" accept=".jpg,.jpeg,.png,.webp"
                            class="hidden" />
                    </label>
                    <div x-show="state === 'error'" x-cloak
                        class="border-t border-red-100 dark:border-red-900/30 px-4 py-2.5 bg-red-50 dark:bg-red-900/10">
                        <p class="text-xs text-red-600 dark:text-red-400 flex items-center gap-1.5">
                            <flux:icon name="exclamation-circle" class="size-3.5 shrink-0" />
                            <span x-text="errorMsg"></span>
                        </p>
                    </div>
                </div>

                {{-- Uploading --}}
                <div x-show="state === 'uploading'" x-cloak
                    class="mt-1 rounded-xl border border-primary-200 bg-primary-50/40 dark:border-primary-800/40 dark:bg-primary-900/10 px-4 py-4 space-y-2.5">
                    {{-- Preview + progress --}}
                    <div class="flex items-center gap-3">
                        <img :src="previewUrl" class="size-10 rounded object-cover shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-primary-700 dark:text-primary-300 truncate"
                                x-text="fileName"></p>
                            <div
                                class="mt-1.5 relative h-1.5 rounded-full bg-primary-100 dark:bg-primary-900/40 overflow-hidden">
                                <div class="absolute inset-y-0 left-0 rounded-full bg-primary-500 transition-all duration-300"
                                    :style="'width: ' + progress + '%'"></div>
                            </div>
                        </div>
                        <span class="text-xs font-semibold text-primary-600 shrink-0" x-text="progress + '%'"></span>
                    </div>
                </div>

                {{-- Done --}}
                <div x-show="state === 'done'" x-cloak
                    class="mt-1 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-800/40 dark:bg-emerald-900/10 px-4 py-3">
                    <img :src="previewUrl"
                        class="size-10 rounded object-cover shrink-0 ring-1 ring-emerald-300" />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300 truncate"
                            x-text="fileName"></p>
                        <p class="text-xs text-emerald-500">Siap diunggah</p>
                    </div>
                    <button type="button" @click="resetUpload()"
                        class="text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-primary-dark-300 shrink-0 transition-colors">
                        Ganti
                    </button>
                </div>

                @error('slideUploadedPath')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            
        <x-slot:footer>
            <div class="flex justify-end gap-2 pt-1">
                <x-atoms.button wire:click="$set('showSlideModal', false)" variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="saveSlide" variant="primary"
                    icon="{{ $editSlideId ? 'check' : 'arrow-up-tray' }}" wire:loading.attr="disabled"
                    wire:target="saveSlide">
                    <span wire:loading.remove
                        wire:target="saveSlide">{{ $editSlideId ? 'Simpan' : 'Unggah Slide' }}</span>
                    <span wire:loading wire:target="saveSlide">Menyimpan...</span>
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Modal Konfirmasi Hapus Slide ────────────────────────────────────── --}}
    <x-organisms.modal wire:model="showSlideConfirmModal" maxWidth="sm" title="">
        <div class="space-y-4">
            <div class="flex items-start gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="trash" class="size-4 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <flux:heading size="sm">Hapus Slide</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-500 dark:text-primary-dark-400">
                        Slide akan dihapus permanen beserta filenya. Tindakan ini tidak dapat dibatalkan.
                    </flux:text>
                </div>
            </div>
            
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showSlideConfirmModal', false)"
                    variant="ghost">Batal</x-atoms.button>
                <x-atoms.button wire:click="executeSlideConfirm" variant="danger" wire:loading.attr="disabled"
                    wire:target="executeSlideConfirm">
                    Ya, Hapus
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Modal Tambah / Edit Versi ────────────────────────────────────────── --}}
    <x-organisms.modal wire:model="showFormModal" maxWidth="3xl" title="">
        <div class="space-y-5">
            {{-- Heading --}}
            <div class="flex items-center gap-3">
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary-100 dark:bg-primary-900/30">
                    <flux:icon name="{{ $editVersionId ? 'pencil-square' : 'arrow-up-tray' }}"
                        class="size-4 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <flux:heading size="sm">
                        @if ($editVersionId)
                            Edit Versi {{ $version }}
                        @else
                            Unggah Versi {{ $newVersionType === 'launcher' ? 'Launcher' : 'Main SIMRS' }} Baru
                        @endif
                    </flux:heading>
                    <flux:text size="sm" class="text-zinc-400">
                        {{ $editVersionId ? 'Perbarui catatan rilis atau tanggal rilis' : 'Upload file ZIP dan isi informasi versi' }}
                    </flux:text>
                </div>
            </div>

            {{-- Nomor versi --}}
            <div>
                <flux:label>Nomor Versi <span class="text-red-400">*</span></flux:label>
                @if ($editVersionId)
                    <div
                        class="mt-1 flex items-center gap-2 rounded-lg border border-zinc-200 dark:border-primary-dark-600 bg-zinc-50 dark:bg-primary-dark-700/50 px-3 py-2">
                        <flux:icon name="lock-closed" class="size-3.5 text-zinc-400 shrink-0" />
                        <span
                            class="font-mono text-sm font-bold text-zinc-700 dark:text-primary-dark-200">v{{ $version }}</span>
                    </div>
                @else
                    <flux:input wire:model="version" placeholder="2.3.0" class="font-mono" />
                    <flux:error name="version" />
                    <p class="mt-1 text-[10px] text-zinc-400">Format: major.minor.patch</p>
                @endif
            </div>

            {{-- Tanggal rilis --}}
            <div>
                <flux:label>Tanggal Rilis <span class="text-red-400">*</span></flux:label>
                <flux:input wire:model="released_at" type="date" />
                <flux:error name="released_at" />
            </div>

            {{-- Catatan rilis --}}
            <div>
                <flux:label>Catatan Rilis</flux:label>
                <flux:textarea wire:model="notes" rows="4"
                    placeholder="Daftar perubahan, perbaikan bug, fitur baru..." />
                <flux:error name="notes" />
            </div>

            {{-- File upload — hanya mode tambah (chunked upload via JS) --}}
            @if (!$editVersionId)
                <div x-data="{
                    dragging: false,
                    state: 'idle',
                    progress: 0,
                    fileName: '',
                    fileSize: 0,
                    errorMsg: '',
                    get fileSizeLabel() {
                        if (!this.fileSize) return '';
                        const units = ['B', 'KB', 'MB', 'GB'];
                        let size = this.fileSize,
                            i = 0;
                        while (size >= 1024 && i < units.length - 1) {
                            size /= 1024;
                            i++;
                        }
                        return (Math.round(size * 100) / 100) + ' ' + units[i];
                    },
                    handleDrop(e) {
                        this.dragging = false;
                        const f = e.dataTransfer.files[0];
                        if (f) this.startUpload(f);
                    },
                    handleInput(e) {
                        const f = e.target.files[0];
                        if (f) this.startUpload(f);
                    },
                    async startUpload(file) {
                        if (!file.name.toLowerCase().endsWith('.zip')) {
                            this.errorMsg = 'Hanya file .zip yang diperbolehkan.';
                            this.state = 'error';
                            return;
                        }
                        const CHUNK = 5 * 1024 * 1024;
                        const total = Math.ceil(file.size / CHUNK);
                        const uid = crypto.randomUUID().replace(/-/g, '');
                        const token = '{{ csrf_token() }}';
                        this.state = 'uploading';
                        this.progress = 0;
                        this.fileName = file.name;
                        this.fileSize = file.size;
                        this.errorMsg = '';
                        try {
                            for (let i = 0; i < total; i++) {
                                const form = new FormData();
                                form.append('upload_id', uid);
                                form.append('chunk_index', i);
                                form.append('total_chunks', total);
                                form.append('filename', file.name);
                                form.append('chunk', file.slice(i * CHUNK, (i + 1) * CHUNK));
                                form.append('_token', token);
                                const res = await fetch('/upload/chunk', { method: 'POST', body: form });
                                if (!res.ok) {
                                    const d = await res.json().catch(() => ({}));
                                    throw new Error(d.message || 'Gagal mengirim bagian ' + (i + 1) + ' dari ' + total);
                                }
                                this.progress = Math.round((i + 1) / total * 100);
                                if (i === total - 1) {
                                    const data = await res.json();
                                    if (data.done) {
                                        $wire.setUploadedFile(data.path, file.name, file.size);
                                        this.state = 'done';
                                    }
                                }
                            }
                        } catch (err) {
                            this.errorMsg = err.message || 'Upload gagal, coba lagi.';
                            this.state = 'error';
                        }
                    },
                    resetUpload() {
                        this.state = 'idle';
                        this.progress = 0;
                        this.fileName = '';
                        this.fileSize = 0;
                        this.errorMsg = '';
                        $wire.call('clearUploadedFile');
                    }
                }" @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false" @drop.prevent="handleDrop($event)"
                    @reset-uploader.window="resetUpload()">
                    <flux:label>File Update (ZIP) <span class="text-red-400">*</span></flux:label>

                    {{-- Idle & Error: drop zone --}}
                    <div x-show="state === 'idle' || state === 'error'"
                        class="mt-1 rounded-xl border-2 border-dashed transition-all overflow-hidden"
                        :class="dragging
                            ?
                            'border-primary-400 bg-primary-50/60 dark:border-primary-500 dark:bg-primary-900/20' :
                            'border-zinc-300 bg-zinc-50 hover:border-primary-400 hover:bg-primary-50/30 dark:border-primary-dark-600 dark:bg-primary-dark-700/30 dark:hover:border-primary-500'">
                        <label class="flex cursor-pointer flex-col items-center gap-2 px-6 py-5">
                            <span
                                :class="dragging ? 'text-primary-400 dark:text-primary-500' :
                                    'text-zinc-300 dark:text-primary-dark-500'">
                                <flux:icon name="archive-box-arrow-down" class="size-7 transition-colors" />
                            </span>
                            <div class="text-center">
                                <p x-show="!dragging"
                                    class="text-sm font-medium text-zinc-600 dark:text-primary-dark-300">
                                    Seret file ke sini atau <span class="text-primary-500">klik untuk memilih</span>
                                </p>
                                <p x-show="dragging" x-cloak
                                    class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                                    Lepaskan untuk mengunggah
                                </p>
                                <p x-show="!dragging" class="text-xs text-zinc-400">Format: .zip — Tanpa batas ukuran
                                </p>
                            </div>
                            <input @change="handleInput($event)" type="file" accept=".zip" class="hidden" />
                        </label>
                        <div x-show="state === 'error'" x-cloak
                            class="border-t border-red-100 dark:border-red-900/30 px-4 py-2.5 bg-red-50 dark:bg-red-900/10">
                            <p class="text-xs text-red-600 dark:text-red-400 flex items-center gap-1.5">
                                <flux:icon name="exclamation-circle" class="size-3.5 shrink-0" />
                                <span x-text="errorMsg"></span>
                            </p>
                        </div>
                    </div>

                    {{-- Uploading: progress bar --}}
                    <div x-show="state === 'uploading'" x-cloak
                        class="mt-1 rounded-xl border border-primary-200 bg-primary-50/40 dark:border-primary-800/40 dark:bg-primary-900/10 px-4 py-4 space-y-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <flux:icon name="arrow-path" class="size-4 text-primary-500 animate-spin shrink-0" />
                                <p class="text-sm font-medium text-primary-700 dark:text-primary-300 truncate"
                                    x-text="fileName"></p>
                            </div>
                            <span class="text-xs font-semibold text-primary-600 dark:text-primary-400 shrink-0"
                                x-text="progress + '%'"></span>
                        </div>
                        <div class="relative h-1.5 rounded-full bg-primary-100 dark:bg-primary-900/40 overflow-hidden">
                            <div class="absolute inset-y-0 left-0 rounded-full bg-primary-500 transition-all duration-300"
                                :style="'width: ' + progress + '%'"></div>
                        </div>
                        <p class="text-xs text-primary-400" x-text="fileSizeLabel + ' · mengirim chunk...'"></p>
                    </div>

                    {{-- Done: success state --}}
                    <div x-show="state === 'done'" x-cloak
                        class="mt-1 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-800/40 dark:bg-emerald-900/10 px-4 py-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <flux:icon name="check-circle" class="size-4 text-emerald-500 shrink-0" />
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300 truncate"
                                    x-text="fileName"></p>
                                <p class="text-xs text-emerald-500" x-text="fileSizeLabel"></p>
                            </div>
                        </div>
                        <button type="button" @click="resetUpload()"
                            class="ml-3 text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-primary-dark-300 shrink-0 transition-colors">
                            Ganti
                        </button>
                    </div>

                    @error('uploadedPath')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <div
                    class="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-800/40 dark:bg-amber-900/10 px-4 py-3">
                    <p class="text-xs text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                        <flux:icon name="information-circle" class="size-4 shrink-0" />
                        File ZIP tidak dapat diganti melalui edit. Unggah versi baru jika ingin mengganti file.
                    </p>
                </div>
            @endif

            {{-- Footer --}}
            
        <x-slot:footer>
            <div class="flex justify-end gap-2 pt-1">
                <x-atoms.button wire:click="$set('showFormModal', false)" variant="ghost">
                    Batal
                </x-atoms.button>
                <x-atoms.button wire:click="save" variant="primary"
                    icon="{{ $editVersionId ? 'check' : 'arrow-up-tray' }}" wire:loading.attr="disabled"
                    wire:target="save">
                    <span wire:loading.remove wire:target="save">
                        {{ $editVersionId ? 'Simpan Perubahan' : 'Unggah Versi' }}
                    </span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>

    {{-- Modal Detail Catatan Rilis ───────────────────────────────────────── --}}
    <x-organisms.modal wire:model="showDetailModal" maxWidth="lg" title="">
        @if ($detail)
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary-100 dark:bg-primary-900/30">
                        <flux:icon name="document-text" class="size-4 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <flux:heading size="sm">Versi {{ $detail->version }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-400">
                            Dirilis {{ $detail->released_at->format('d M Y') }}
                            @if ($detail->is_active)
                                · <span class="text-emerald-500 font-medium">Aktif</span>
                            @endif
                        </flux:text>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    @foreach ([['label' => 'Ukuran File', 'value' => $detail->file_size_label], ['label' => 'Checksum SHA-256', 'value' => $detail->checksum ? substr($detail->checksum, 0, 16) . '…' : '—']] as $row)
                        <div
                            class="rounded-xl border border-zinc-100 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-700/30 px-3 py-2.5">
                            <p class="text-[10px] text-zinc-400 dark:text-primary-dark-500">{{ $row['label'] }}</p>
                            <p class="mt-0.5 font-mono text-xs font-semibold text-zinc-700 dark:text-primary-dark-200">
                                {{ $row['value'] }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($detail->notes)
                    <div>
                        <p
                            class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                            Catatan Rilis</p>
                        <div
                            class="rounded-xl border border-zinc-100 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-700/30 px-4 py-3 text-sm text-zinc-700 dark:text-primary-dark-200 whitespace-pre-wrap leading-relaxed">
                            {{ $detail->notes }}</div>
                    </div>
                @endif

                @if ($detail->checksum)
                    <div>
                        <p
                            class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-primary-dark-500">
                            Checksum Lengkap</p>
                        <div
                            class="rounded-xl border border-zinc-100 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-700/30 px-3 py-2.5">
                            <p
                                class="font-mono text-[11px] text-zinc-600 dark:text-primary-dark-300 break-all leading-relaxed">
                                {{ $detail->checksum }}</p>
                        </div>
                    </div>
                @endif

                <div class="flex justify-end gap-2 pt-1">
                    <x-atoms.button wire:click="openEdit({{ $detail->id }})" variant="ghost" icon="pencil-square"
                        x-on:click="$wire.showDetailModal = false">
                        Edit
                    </x-atoms.button>
                    <x-atoms.button wire:click="$set('showDetailModal', false)" variant="ghost">Tutup</x-atoms.button>
                </div>
            </div>
        @endif
    
    </x-organisms.modal>

    {{-- Modal Konfirmasi Aksi ────────────────────────────────────────────── --}}
    <x-organisms.modal wire:model="showConfirmModal" maxWidth="sm" title="">
        <div class="space-y-4">
            <div class="flex items-start gap-3">
                <div @class([
                    'flex size-9 shrink-0 items-center justify-center rounded-xl',
                    'bg-emerald-100 dark:bg-emerald-900/30' => $confirmAction === 'activate',
                    'bg-amber-100 dark:bg-amber-900/30' => $confirmAction === 'deactivate',
                    'bg-red-100 dark:bg-red-900/30' => $confirmAction === 'delete',
                ])>
                    @if ($confirmAction === 'activate')
                        <flux:icon name="bolt" class="size-4 text-emerald-600 dark:text-emerald-400" />
                    @elseif ($confirmAction === 'deactivate')
                        <flux:icon name="pause-circle" class="size-4 text-amber-600 dark:text-amber-400" />
                    @else
                        <flux:icon name="trash" class="size-4 text-red-600 dark:text-red-400" />
                    @endif
                </div>
                <div>
                    <flux:heading size="sm">
                        @if ($confirmAction === 'activate')
                            Aktifkan Versi
                        @elseif ($confirmAction === 'deactivate')
                            Nonaktifkan Versi
                        @else
                            Hapus Versi
                        @endif
                    </flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-500 dark:text-primary-dark-400">
                        @if ($confirmAction === 'activate')
                            Versi <span
                                class="font-mono font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $confirmVersion }}</span>
                            akan dijadikan versi aktif. Versi yang sedang aktif akan dinonaktifkan secara otomatis.
                        @elseif ($confirmAction === 'deactivate')
                            Versi <span
                                class="font-mono font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $confirmVersion }}</span>
                            akan dinonaktifkan. Tidak akan ada versi aktif hingga salah satu diaktifkan kembali.
                        @else
                            Versi <span
                                class="font-mono font-semibold text-zinc-700 dark:text-primary-dark-200">{{ $confirmVersion }}</span>
                            akan dihapus beserta file-nya dari server. Tindakan ini tidak dapat dibatalkan.
                        @endif
                    </flux:text>
                </div>
            </div>

            
        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-atoms.button wire:click="$set('showConfirmModal', false)" variant="ghost">
                    Batal
                </x-atoms.button>
                <x-atoms.button wire:click="executeConfirm"
                    variant="{{ $confirmAction === 'activate' ? 'primary' : ($confirmAction === 'deactivate' ? 'filled' : 'danger') }}"
                    wire:loading.attr="disabled" wire:target="executeConfirm">
                    @if ($confirmAction === 'activate')
                        Ya, Aktifkan
                    @elseif ($confirmAction === 'deactivate')
                        Ya, Nonaktifkan
                    @else
                        Ya, Hapus
                    @endif
                </x-atoms.button>
            </div>
        </x-slot:footer>
    </div>
    </x-organisms.modal>
</div>
