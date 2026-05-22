<?php

use App\Jobs\ImportTerminologyJob;
use App\Models\Terminology\Icd9;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app', ['title' => 'Source Terminology'])] class extends Component {
    use WithPagination;

    protected string $type = 'icd9';
    protected string $typeLabel = 'ICD-9CM';

    public string $search = '';
    public string $filterVersion = '';

    public bool $showImportModal = false;
    public string $importPath = '';
    public array $importVersions = [];
    public array $conflictVersions = [];
    public array $conflictCounts = [];
    public int $importTotalRows = 0;
    public bool $importing = false;
    public bool $showConflictWarning = false;
    public bool $previewDone = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
    public function updatingFilterVersion(): void
    {
        $this->resetPage();
    }

    public function openImportModal(): void
    {
        $this->resetImportState();
        $this->showImportModal = true;
    }

    public function resetImportState(): void
    {
        $this->importPath = $this->filterVersion = '';
        $this->importVersions = $this->conflictVersions = $this->conflictCounts = [];
        $this->importTotalRows = 0;
        $this->importing = $this->showConflictWarning = $this->previewDone = false;
        $this->resetErrorBag();
    }

    public function setImportPath(string $path): void
    {
        $tempBase = realpath(storage_path('app/temp'));
        abort_if(!$tempBase || !str_starts_with(realpath($path) ?: '', $tempBase), 403, 'Path tidak valid.');
        $this->importPath = $path;
    }

    public function previewImport(): void
    {
        $this->resetErrorBag();
        $this->importVersions = $this->conflictVersions = $this->conflictCounts = [];
        $this->showConflictWarning = $this->previewDone = false;

        if (empty($this->importPath) || !file_exists($this->importPath)) {
            $this->addError('importPath', 'File tidak ditemukan di server.');
            return;
        }

        $handle = fopen($this->importPath, 'r');
        if (!$handle) {
            $this->addError('importPath', 'Gagal membuka file CSV.');
            return;
        }

        $headers = array_map(fn($h) => strtolower(trim($h)), fgetcsv($handle, 0, ',') ?: []);

        if (!in_array('code', $headers) || !in_array('version', $headers)) {
            fclose($handle);
            $this->addError('importPath', 'Format CSV tidak valid. Kolom yang dibutuhkan: code, display, version.');
            return;
        }

        $versions = [];
        $rowCount = 0;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($headers) !== count($row)) {
                continue;
            }
            $data = array_combine($headers, $row);
            $v = trim($data['version'] ?? '');
            if ($v) {
                $versions[$v] = true;
            }
            $rowCount++;
        }

        fclose($handle);
        $this->importTotalRows = $rowCount;
        $this->importVersions = array_keys($versions);

        foreach ($this->importVersions as $v) {
            $count = Icd9::countByVersion($v);
            if ($count > 0) {
                $this->conflictVersions[] = $v;
                $this->conflictCounts[$v] = $count;
            }
        }

        $this->showConflictWarning = !empty($this->conflictVersions);
        $this->previewDone = true;
    }

    public function import(): void
    {
        if (!empty($this->conflictVersions)) {
            return;
        }
        $this->doDispatch([]);
    }

    public function forceImport(): void
    {
        $this->doDispatch($this->conflictVersions);
    }

    private function doDispatch(array $forceVersions): void
    {
        if (empty($this->importPath) || !file_exists($this->importPath)) {
            $this->addError('importPath', 'File tidak ditemukan.');
            return;
        }
        $this->importing = true;
        try {
            ImportTerminologyJob::dispatch($this->importPath, $this->type, $forceVersions);
            $this->resetImportState();
            $this->showImportModal = false;
            $this->dispatch('toast', type: 'success', message: 'Import berjalan di background. Data akan segera tersedia.');
        } catch (\Exception $e) {
            $this->importing = false;
            $this->dispatch('toast', type: 'error', message: 'Gagal memulai import: ' . $e->getMessage());
        }
    }

    public function deleteVersion(string $version): void
    {
        Icd9::where('version', $version)->delete();
        if ($this->filterVersion === $version) {
            $this->filterVersion = '';
        }
        $this->dispatch('toast', type: 'success', message: "Versi {$version} berhasil dihapus.");
    }

    public function with(): array
    {
        $data = Icd9::query()->when($this->search, fn($q) => $q->where(fn($s) => $s->where('code', 'like', $this->search . '%')->orWhere('display', 'like', '%' . $this->search . '%')))->when($this->filterVersion, fn($q) => $q->where('version', $this->filterVersion))->orderBy('version')->orderBy('code')->paginate(25);

        return [
            'data' => $data,
            'versions' => Icd9::getVersions(),
            'typeLabel' => $this->typeLabel,
        ];
    }
};
?>

<div>
    @include('pages.terminology._source-codes-view')
</div>
