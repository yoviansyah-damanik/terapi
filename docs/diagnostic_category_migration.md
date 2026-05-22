# Pemisahan Tabel Mapping Diagnostic Category

Memisahkan kolom `diagnostic_category` dan `diagnostic_category_term` dari tabel `map_lab` dan `map_rad` ke dalam tabel tersendiri yaitu `map_diagnostic_category`.

Rencana Perubahan:
1. Membuat tabel `map_diagnostic_category` menggunakan migrasi.
2. Memindahkan data kategori yang sudah ada di tabel `map_lab` dan `map_rad` ke tabel `map_diagnostic_category` secara otomatis saat migrasi.
3. Menghapus kolom `diagnostic_category` dan `diagnostic_category_term` dari `map_lab` dan `map_rad`.
4. Membuat model `DiagnosticCategoryMap`.
5. Mengubah model `LabMap` dan `RadMap`.
6. Menyesuaikan service `ErmFhirService.php` dan komponen blade untuk menggunakan tabel yang baru.
