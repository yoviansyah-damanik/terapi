# Plan: Halaman Detail Pengiriman FHIR Resource

**Status:** Menunggu konfirmasi  
**Referensi:** Halaman `satusehat.fhir-resource.episode-of-care` sebagai pola acuan

---

## Latar Belakang

Halaman `episode-of-care` memiliki dua tab:

- **Tab 1 "Deteksi"** — scan data SIMRS, tampilkan yang siap/belum/terblokir untuk dikirim
- **Tab 2 "Terkirim"** — list record yang sudah masuk ke tabel lokal (`satu_sehat_*`)

Pola ini akan diterapkan ke resource clinical FHIR lainnya. Seluruh pengiriman tetap dilakukan dari halaman eRM (`erm.detail`). Halaman FHIR resource berfungsi sebagai **monitoring dan navigasi**, bukan endpoint pengiriman baru.

---

## Halaman Baru (8 halaman)

| Route | URL | Model Utama | Sumber Deteksi (Tab 1) |
|---|---|---|---|
| `satusehat.fhir-resource.encounter` | `/satusehat/fhir-resource/encounter` | `SatuSehatEncounter` | `reg_periksa` belum punya encounter |
| `satusehat.fhir-resource.condition` | `/satusehat/fhir-resource/condition` | `SatuSehatCondition` | `diagnosa_pasien` belum terkirim |
| `satusehat.fhir-resource.observation` | `/satusehat/fhir-resource/observation` | `SatuSehatObservation` | Stat per kategori dari tabel lokal |
| `satusehat.fhir-resource.procedure` | `/satusehat/fhir-resource/procedure` | `SatuSehatProcedure` | `prosedur_pasien` / `jns_perawatan` |
| `satusehat.fhir-resource.allergy-intolerance` | `/satusehat/fhir-resource/allergy-intolerance` | `SatuSehatAllergyIntolerance` | `alergi_pasien` SIMRS |
| `satusehat.fhir-resource.medication-request` | `/satusehat/fhir-resource/medication-request` | `SatuSehatMedicationRequest` | `detail_pemberian_obat` belum terkirim |
| `satusehat.fhir-resource.diagnostic-report` | `/satusehat/fhir-resource/diagnostic-report` | `SatuSehatDiagnosticReport` | `periksa_lab` + `periksa_radiologi` |
| `satusehat.fhir-resource.composition` | `/satusehat/fhir-resource/composition` | `SatuSehatComposition` | `resume_pasien` + `catatan_adime_gizi` |

---

## Struktur Setiap Halaman

### Tab 1 — "Deteksi / Ringkasan"

- **Stat cards**: Total di SIMRS (default 30 hari), Terkirim, Belum Terkirim
- Filter range tanggal
- Tabel: data dari SIMRS dengan badge status (Terkirim / Belum / Tidak Eligible)
- Tombol aksi **navigasi ke halaman eRM** (`erm.detail`) — pengiriman tetap dari sana
- Pengecualian: **Observation** tidak punya deteksi SIMRS (terlalu granular). Tab 1-nya menampilkan stat per kategori (vital-signs / laboratory / imaging) dari tabel lokal.

### Tab 2 — "Terkirim"

- Search + filter (status, tanggal, filter khas per resource)
- Tabel: `local_id`, `ihs_number`, field kunci resource, `synced_at`
- Tombol mata → **detail modal** (metadata + raw JSON response)
- Link ke kunjungan asal: dari `encounter_ihs` → `SatuSehatEncounter.local_id` → route `erm.detail`

### Modal Detail (konsisten semua resource)

```
┌ Detail {Resource} ──────────────────────────────────────┐
│  Metadata: IHS Number, Local ID, Status, Synced At      │
│  Field kunci per resource                               │
│  [→ Lihat kunjungan eRM]         [Raw Response JSON]    │
└─────────────────────────────────────────────────────────┘
```

---

## Perbedaan Tab 1 per Resource

| Resource | Sumber SIMRS | Query Strategy | Filter Khas |
|---|---|---|---|
| Encounter | `reg_periksa` | LEFT JOIN `satu_sehat_encounters` ON `local_id = no_rawat` | Kelas (AMB/IMP/EMER), Status |
| Condition | `diagnosa_pasien` | JOIN pasien, LEFT JOIN `satu_sehat_conditions` ON `local_id` | Kode ICD-10, Prioritas |
| Observation | — | Stat dari `satu_sehat_observations` | Category (vital-signs / laboratory / imaging) |
| Procedure | `prosedur_pasien` | JOIN icd9, LEFT JOIN `satu_sehat_procedures` | Kode ICD-9 |
| AllergyIntolerance | `alergi_pasien` (SIMRS) | JOIN pasien, LEFT JOIN `satu_sehat_allergy_intolerances` | Kategori (medication/food/environment) |
| MedicationRequest | `detail_pemberian_obat` | GROUP BY kd_obat+no_rawat, LEFT JOIN `satu_sehat_medication_requests` | Status resep |
| DiagnosticReport | `periksa_lab` + `periksa_radiologi` | UNION, LEFT JOIN `satu_sehat_diagnostic_reports` | Kategori (LAB / RAD) |
| Composition | `resume_pasien` + `catatan_adime_gizi` | LEFT JOIN `satu_sehat_compositions` ON `local_id` | Type code (resume/gizi/ADIME) |

---

## File yang Dibuat / Dimodifikasi

### View files (8 baru)
```
resources/views/pages/satusehat/fhir-resource/
├── ⚡encounter.blade.php
├── ⚡condition.blade.php
├── ⚡observation.blade.php
├── ⚡procedure.blade.php
├── ⚡allergy-intolerance.blade.php
├── ⚡medication-request.blade.php
├── ⚡diagnostic-report.blade.php
└── ⚡composition.blade.php
```

### `routes/web.php`
Tambah 8 route di dalam grup `satusehat.fhir-resource.*`:
```php
Route::livewire('/encounter', 'pages::satusehat.fhir-resource.encounter')->name('encounter');
Route::livewire('/condition', 'pages::satusehat.fhir-resource.condition')->name('condition');
Route::livewire('/observation', 'pages::satusehat.fhir-resource.observation')->name('observation');
Route::livewire('/procedure', 'pages::satusehat.fhir-resource.procedure')->name('procedure');
Route::livewire('/allergy-intolerance', 'pages::satusehat.fhir-resource.allergy-intolerance')->name('allergy-intolerance');
Route::livewire('/medication-request', 'pages::satusehat.fhir-resource.medication-request')->name('medication-request');
Route::livewire('/diagnostic-report', 'pages::satusehat.fhir-resource.diagnostic-report')->name('diagnostic-report');
Route::livewire('/composition', 'pages::satusehat.fhir-resource.composition')->name('composition');
```

### `config/sidebar.php`
Tambah 8 nav-item di bawah grup FHIR Resource (setelah `episode-of-care`):
```php
['title' => 'Encounter',           'route' => 'satusehat.fhir-resource.encounter'],
['title' => 'Condition',           'route' => 'satusehat.fhir-resource.condition'],
['title' => 'Observation',         'route' => 'satusehat.fhir-resource.observation'],
['title' => 'Procedure',           'route' => 'satusehat.fhir-resource.procedure'],
['title' => 'Allergy Intolerance', 'route' => 'satusehat.fhir-resource.allergy-intolerance'],
['title' => 'Medication Request',  'route' => 'satusehat.fhir-resource.medication-request'],
['title' => 'Diagnostic Report',   'route' => 'satusehat.fhir-resource.diagnostic-report'],
['title' => 'Composition',         'route' => 'satusehat.fhir-resource.composition'],
```

---

## Cakupan & Pengecualian

### Resource yang masuk plan ini (8)
Diprioritaskan karena memiliki nilai monitoring tersendiri di luar konteks kunjungan.

### Resource yang tidak dibuatkan halaman tersendiri
Resources berikut lebih relevan dilihat dalam konteks kunjungan via eRM dan tidak dibuatkan halaman terpisah:

| Resource | Alasan |
|---|---|
| MedicationDispense | Turunan dari MedicationRequest, konteks kunjungan |
| MedicationStatement | Riwayat obat per pasien, konteks kunjungan |
| MedicationAdministration | Pemberian obat per kunjungan |
| ServiceRequest | Order lab/rad per kunjungan |
| Specimen | Spesimen per lab/rad order |
| ImagingStudy | Sudah ada halaman DICOM Worklist |
| ClinicalImpression | Catatan klinis per kunjungan |
| CarePlan | Instruksi perawatan per kunjungan |
| QuestionnaireResponse | Telaah farmasi per kunjungan |

---

## Catatan Teknis

- Query Tab 1 yang menyentuh SIMRS dibungkus `try/catch` karena koneksi DB berbeda
- Pagination Tab 1 menggunakan `LengthAwarePaginator` manual (collection-based) atau query langsung tergantung volume data
- Link ke eRM: `route('erm.detail', ['noRawat' => $encounter_local_id])`
- Semua halaman inherit pola `#[Url]` untuk `activeTab`, `search`, `perPage`
