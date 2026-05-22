<?php

use App\Helpers\ConfigurationHelper;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::app')] #[Title('Informasi RS')] class extends Component {
    public string $hospitalName = '';
    public string $hospitalAlias = '';
    public string $hospitalPhone = '';
    public string $hospitalEmail = '';
    public string $hospitalWebsite = '';
    public string $hospitalAddress = '';
    public string $hospitalCity = '';
    public string $hospitalProvince = '';
    public string $hospitalPostalCode = '';
    public string $hospitalCountry = 'ID';
    public string $hospitalPropinsi = '';
    public string $hospitalKabupaten = '';
    public string $hospitalKecamatan = '';
    public string $hospitalKelurahan = '';

    public function mount(): void
    {
        $this->hospitalName = ConfigurationHelper::get('hospital.name', '');
        $this->hospitalAlias = ConfigurationHelper::get('hospital.alias', '');
        $this->hospitalPhone = ConfigurationHelper::get('hospital.phone', '');
        $this->hospitalEmail = ConfigurationHelper::get('hospital.email', '');
        $this->hospitalWebsite = ConfigurationHelper::get('hospital.website', '');
        $this->hospitalAddress = ConfigurationHelper::get('hospital.address', '');
        $this->hospitalCity = ConfigurationHelper::get('hospital.city', '');
        $this->hospitalProvince = ConfigurationHelper::get('hospital.province', '');
        $this->hospitalPostalCode = ConfigurationHelper::get('hospital.postal_code', '');
        $this->hospitalCountry = ConfigurationHelper::get('hospital.country', 'ID');
        $this->hospitalPropinsi = ConfigurationHelper::get('hospital.propinsi_code', '');
        $this->hospitalKabupaten = ConfigurationHelper::get('hospital.kabupaten_code', '');
        $this->hospitalKecamatan = ConfigurationHelper::get('hospital.kecamatan_code', '');
        $this->hospitalKelurahan = ConfigurationHelper::get('hospital.kelurahan_code', '');
    }

    public function saveHospital(): void
    {
        ConfigurationHelper::set('hospital.name', $this->hospitalName);
        ConfigurationHelper::set('hospital.alias', $this->hospitalAlias);
        ConfigurationHelper::set('hospital.phone', $this->hospitalPhone);
        ConfigurationHelper::set('hospital.email', $this->hospitalEmail);
        ConfigurationHelper::set('hospital.website', $this->hospitalWebsite);
        ConfigurationHelper::set('hospital.address', $this->hospitalAddress);
        ConfigurationHelper::set('hospital.city', $this->hospitalCity);
        ConfigurationHelper::set('hospital.province', $this->hospitalProvince);
        ConfigurationHelper::set('hospital.postal_code', $this->hospitalPostalCode);
        ConfigurationHelper::set('hospital.country', $this->hospitalCountry);
        ConfigurationHelper::set('hospital.propinsi_code', $this->hospitalPropinsi);
        ConfigurationHelper::set('hospital.kabupaten_code', $this->hospitalKabupaten);
        ConfigurationHelper::set('hospital.kecamatan_code', $this->hospitalKecamatan);
        ConfigurationHelper::set('hospital.kelurahan_code', $this->hospitalKelurahan);

        $this->dispatch('toast', type: 'success', message: 'Informasi RS berhasil disimpan.');
    }
}; ?>

<div>
    <x-ui.page-header title="Informasi RS" subtitle="Data identitas RS yang digunakan di seluruh modul" />

    <x-ui.section-card title="Informasi Rumah Sakit"
        subtitle="Data identitas RS yang digunakan di seluruh modul (eRM BPJS, Satu Sehat, laporan, dll.)">
        <form id="form-hospital" wire:submit="saveHospital" class="space-y-6">
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 mb-3">
                    Identitas</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <flux:label>Nama RS</flux:label>
                        <flux:input wire:model="hospitalName" placeholder="RS Contoh" />
                    </div>
                    <div>
                        <flux:label>Alias / Singkatan</flux:label>
                        <flux:input wire:model="hospitalAlias" placeholder="RSC" />
                    </div>
                    <div>
                        <flux:label>Telepon</flux:label>
                        <flux:input wire:model="hospitalPhone" placeholder="021-..." />
                    </div>
                    <div>
                        <flux:label>Email</flux:label>
                        <flux:input type="email" wire:model="hospitalEmail" placeholder="rs@example.com" />
                    </div>
                    <div>
                        <flux:label>Website</flux:label>
                        <flux:input wire:model="hospitalWebsite" placeholder="https://..." />
                    </div>
                </div>
            </div>
            <hr class="border-zinc-200 dark:border-primary-dark-700">
            <div>
                <h4
                    class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 mb-3">
                    Alamat</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <flux:label>Alamat</flux:label>
                        <flux:input wire:model="hospitalAddress" placeholder="Jl. ..." />
                    </div>
                    <div>
                        <flux:label>Kota / Kabupaten</flux:label>
                        <flux:input wire:model="hospitalCity" placeholder="Kota ..." />
                    </div>
                    <div>
                        <flux:label>Provinsi</flux:label>
                        <flux:input wire:model="hospitalProvince" placeholder="Provinsi ..." />
                    </div>
                    <div>
                        <flux:label>Kode Pos</flux:label>
                        <flux:input wire:model="hospitalPostalCode" placeholder="12345" />
                    </div>
                    <div>
                        <flux:label>Negara</flux:label>
                        <flux:input wire:model="hospitalCountry" placeholder="ID" />
                    </div>
                </div>
            </div>
            <hr class="border-zinc-200 dark:border-primary-dark-700">
            <div>
                <h4
                    class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-primary-dark-400 mb-1">
                    Kode Wilayah</h4>
                <p class="text-xs text-zinc-400 dark:text-primary-dark-500 mb-3">Standar Kemendagri — digunakan pada
                    FHIR
                    resource Satu Sehat.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <flux:label>Kode Provinsi</flux:label>
                        <flux:input wire:model="hospitalPropinsi" placeholder="32" class="font-mono" />
                    </div>
                    <div>
                        <flux:label>Kode Kabupaten/Kota</flux:label>
                        <flux:input wire:model="hospitalKabupaten" placeholder="32.73" class="font-mono" />
                    </div>
                    <div>
                        <flux:label>Kode Kecamatan</flux:label>
                        <flux:input wire:model="hospitalKecamatan" placeholder="32.73.07" class="font-mono" />
                    </div>
                    <div>
                        <flux:label>Kode Kelurahan</flux:label>
                        <flux:input wire:model="hospitalKelurahan" placeholder="32.73.07.1003" class="font-mono" />
                    </div>
                </div>
            </div>
        </form>
        <x-slot:footer>
            <div class="flex items-center gap-3">
                <x-atoms.button form="form-hospital" type="submit" variant="primary" icon="check">Simpan</x-atoms.button>
                <span wire:loading wire:target="saveHospital"
                    class="text-sm text-zinc-500 dark:text-primary-dark-400">Menyimpan...</span>
            </div>
        </x-slot:footer>
    </x-ui.section-card>
</div>
