# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Rules

- **JANGAN PERNAH** jalankan `php artisan migrate:fresh` atau `migrate:fresh --seed` pada aplikasi ini — akan menghapus seluruh data produksi secara permanen.
- Setiap halaman yang dibangun **wajib** menggunakan atomic design components yang tersedia
- Setiap menambah menu baru di `config/sidebar.php`, **wajib** tambahkan `'permission'` key yang sesuai di entry tersebut dan daftarkan permission key-nya di `config/permissions.php` (`x-organisms.*`, `x-molecules.*`, `x-atoms.*`, `x-ui.*`, `x-form.*`) — jangan pakai elemen HTML mentah atau Flux components secara langsung jika sudah ada wrapper atomic design-nya.

## Commands

```bash
# Start all development services concurrently (server, queue, log stream, vite)
composer run dev

# Production asset build
npm run build

# Run test suite
composer run test
# or
php artisan test
```

## Architecture Overview

**Terapi** adalah Laravel 12 + Livewire 4 + Volt platform integrasi layanan kesehatan. Berfungsi sebagai hub yang menghubungkan SIMRS, SatuSehat (FHIR Kemkes), BPJS Kesehatan, TTE, DICOM, dan WhatsApp Gateway.

### Stack

- **PHP 8.2+**, Laravel 12, Livewire 4.1, Volt 1.7
- **Flux UI** (component library via Livewire Flux)
- **Vite 6 + Tailwind CSS 4**
- Database: SQLite (dev) / MySQL (prod)

### Multiple Databases

```php
// Query SIMRS (read-only, external hospital system)
Model::on('simrs')->where(...)->get();
```

`config/database.php` mendefinisikan dua koneksi:
- `sqlite`/`mysql` → database aplikasi utama
- `simrs` → database SIMRS eksternal (`DB_SIMRS_*` env vars)

**Penting:** Jangan buat relasi Eloquent langsung antar dua koneksi berbeda. Lakukan join/merge di PHP.

### Volt Pages Pattern

Semua halaman adalah **Volt full-stack components**, bukan view biasa. File dengan prefix `⚡` di `resources/views/pages/` adalah Volt components:

```php
<?php
use Livewire\Attributes\{Layout, Title};

new #[Layout('layouts::app')] #[Title('Judul Halaman')] class extends Component {
    public function with(): array {
        return ['data' => Model::all()];
    }
    // public methods = callable dari UI (wire:click)
};
?>
{{-- Template Blade di bawah PHP block --}}
```

### Konfigurasi (3 Layer)

Priority: **Database > .env > config/*.php**

`ConfigurationHelper::get('key')` → caching in-memory + TTL 5 menit dari tabel `configurations`. `AppServiceProvider` meng-override `config()` Laravel dengan nilai dari DB saat boot.

Gunakan `ConfigurationHelper::get/set(key, value)` untuk semua konfigurasi yang bisa diubah via UI (credentials, URLs, toggles).

### Services Utama

| Service | Lokasi | Integrasi |
|---------|--------|-----------|
| SatuSehat FHIR | `app/Services/SatuSehat/` | Kemkes FHIR R4 API |
| BPJS | `app/Services/Bpjs/` | vClaim, Antrian, ERM, ApoTek |
| DICOM/PACS | `app/Services/Dicom/OrthancService` | Orthanc server |
| TTE | `app/Services/TteService` | Snowstorm digital signature |
| WhatsApp | `app/Services/WahaService`, `GowaService` | Waha & Gowa providers |
| RS Online | `app/Services/RsOnline/RsOnlineService` | RS Online API |
| AI | `app/Services/AiService` | LLM prompting |

### Models Namespace

```
app/Models/
├── Api/          # ApiUser, ApiToken, ApiLog, ApiSecurityLog
├── Bpjs/         # BpjsPatient, BpjsPractitioner, BpjsErm, dll.
├── Dicom/        # DicomModality, DicomStudy, DicomRouter
├── Mapping/      # EmployeeMap, DoctorMap, AllergyMap, dll.
├── SatuSehat/    # SatuSehatPatient, SatuSehatEncounter, dll.
├── Simrs/        # Model mirror data SIMRS (read via connection 'simrs')
├── Terminology/  # Hl7CodeSystem, FhirDictionary
└── WaGateway/    # WahaMessage, GowaMessage, dll.
```

`BaseModel` → extend untuk semua model dengan UUID PK (`HasUuids`). Migration wajib pakai `$table->uuid('id')->primary()`.

### API (`/api/v1`)

Auth: header `x-token`. Scope per endpoint (hospital, simrs, tte, dicom, whatsapp-gateway, qrcode, ai).

Middleware stack: `ApiTokenAuth → ApiScopeCheck → LimitRequestSize → ApiRequestLogger`

### Queue Jobs

Semua operasi sync berjalan async via job:
- `SyncBatch*Job` — sync patients/practitioners dari SIMRS ke SatuSehat/BPJS
- `SendSatuSehatBundleJob` — kirim FHIR bundle
- `Import*Job` — import terminologi (ICD, LOINC, SNOMED, HL7 CodeSystem)
- `Send*MessageJob` — kirim pesan WhatsApp

Queue driver: database (default).

### Routing

```
routes/web.php   → authenticated web routes (Volt pages)
routes/api.php   → /api/v1/* dengan token auth
routes/auth.php  → login/logout
```

Route sidebar dikonfigurasi via `config/sidebar.php`.

### Testing

PHPUnit 11 dengan SQLite in-memory. Konfigurasi di `phpunit.xml`.

```bash
php artisan test --filter=NamaTest   # jalankan satu test
php artisan test tests/Feature/      # jalankan satu suite
```

### Reusable Livewire Search Components

Komponen di `resources/views/livewire/components/` yang sering dipakai dalam modal pencarian:

| Komponen | Event Dispatch | Payload |
|----------|----------------|---------|
| `components.snomed-search` | `snomed-selected` | `system_code, system_term, system_display, category` |
| `components.fhir-codesystem-search` | `fhir-codesystem-selected` | `{ system_code, system_term, system_display, type }` |
| `components.fhir-dictionaries-search` | `fhir-dictionary-selected` | `{ id, source, type, system_code, system_term, system_display }` |
| `components.satusehat-resource-search` | `satusehat-resource-selected` | `resource` array FHIR |
| `components.loinc-search` | `loinc-search-selected` | `item` array |

Props umum: `:limitTypes="['my-type']"`, `:limitSources="['kemkes']"`, `:initialSearch="$var"`, `:key="'unique-key'"`.

Tangkap event dengan `#[On('event-name')]` di Volt component parent.

### Terminology: Dua Tabel Berbeda

- **`hl7_code_systems`** (`Hl7CodeSystem`) — diisi via import CSV; digunakan oleh `fhir-codesystem-search`. Types: `service-category`, `service-type`, `location-physical-type`, dll.
- **`fhir_dictionaries`** (`FhirDictionary`) — kolom tambahan `source` (`kemkes`, `hl7`, `internal`, dll.) dan `system_defenition`; digunakan oleh `fhir-dictionaries-search`. Types: `practitioner-speciality`, `diagnostic-category`, dll.

### Toast Notifications

```php
$this->toastSuccess('Pesan', 'Judul opsional');
$this->toastError('Pesan');
$this->toastWarning('Pesan');
```

### Volt Page Partials

Saat template terlalu panjang, pecah ke `partials/` di folder yang sama:

```php
@include('pages::local.healthcare-service.partials._mapping-section', ['var' => $val])
```

Penamaan file partial: prefix `_` (underscore). Variabel parent tersedia otomatis di `@include`.
