# Dokumentasi Integrasi BpjsErmLog di ErmService

## Perubahan

### 1. ErmService.php
- **Import**: Menambahkan `use App\Models\BpjsErmLog;`
- **Parameter**: Menambahkan parameter `$noRawat` di method `insertRekamMedis()`
- **Logging**: Menambahkan logging otomatis setelah pengiriman eRM ke BPJS
- **Headers**: Header HTTP otomatis ditambahkan oleh `BpjsBaseService::headers()`

#### HTTP Headers (Otomatis)
Setiap request ke BPJS otomatis menyertakan header berikut:
- `X-cons-id`: Consumer ID BPJS
- `X-timestamp`: Unix timestamp
- `X-signature`: HMAC-SHA256 signature (base64)
- `user_key`: User key BPJS
- `Content-Type`: application/json

#### Signature Generation
```php
$signature = base64_encode(
    hash_hmac('sha256', $consId . '&' . $timestamp, $secretKey, true)
);
```

#### Signature Method
```php
public function insertRekamMedis(
    string $noRawat,      // Nomor Rawat
    string $noSep,        // Nomor SEP
    int $jnsPelayanan,    // 1=Rawat Inap, 2=Rawat Jalan
    int $bulan,           // Bulan pelayanan (1-12)
    int $tahun,           // Tahun pelayanan
    array $bundle         // FHIR Bundle array
): array
```

#### Log Fields
- `no_rawat`: Nomor rawat pasien
- `no_sep`: Nomor SEP
- `status`: 'success' atau 'failed' (berdasarkan response BPJS)
- `request_payload`: FHIR Bundle yang dikirim (array)
- `response_payload`: Response dari BPJS
- `bundle`: FHIR Bundle dalam format JSON string
- `error_message`: Pesan error (null jika sukses)
- `sent_at`: Timestamp pengiriman (null jika gagal)

### 2. ⚡erm-detail.blade.php
- **Hapus**: Import `BpjsErmLog` (tidak diperlukan lagi)
- **Hapus**: Manual logging (sudah otomatis di service)
- **Update**: Panggilan `insertRekamMedis()` dengan parameter `$noRawat`

## Keuntungan
✅ Logging terpusat di service layer  
✅ Reusable - service bisa dipanggil dari mana saja dengan logging otomatis  
✅ Cleaner code di controller/component layer  
✅ Konsisten - semua pengiriman eRM pasti ter-log  
✅ Headers otomatis - autentikasi BPJS otomatis ditangani
