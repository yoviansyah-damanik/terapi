# Dokumentasi CRUD Departemen SIMRS

Halaman Departemen SIMRS (`simrs.department`) menyediakan fungsi pengelolaan data master departemen (`departemen`) secara lengkap (CRUD).

## Berkas yang Diubah / Dibuat
1. **Rute Baru**: Ditambahkan `/department` ke dalam grup `simrs` di [web.php](file:///d:/WebApps/terapi/routes/web.php).
2. **Halaman CRUD**: Dibuat komponen [⚡department.blade.php](file:///d:/WebApps/terapi/resources/views/pages/simrs/%E2%9A%A1department.blade.php) menggunakan Livewire.
3. **Menu Sidebar**: Ditambahkan item menu baru untuk Departemen ke dalam grup "Data SIMRS" di [sidebar.php](file:///d:/WebApps/terapi/config/sidebar.php).

## Fitur & Logika Utama
- **Read**: Menampilkan tabel terpaginasi (default 25 baris) dengan pencarian *real-time* berbasis ID atau Nama.
- **Create**: Modal *form* input untuk ID dan Nama. Dilengkapi dengan validasi keunikan ID secara manual agar terhindar dari konflik koneksi database.
- **Update**: Memperbarui Nama departemen. Input ID diubah menjadi *readonly* untuk menjaga integritas relasi tabel (foreign key).
- **Delete**: Konfirmasi penghapusan dengan proteksi `try-catch` dari database bila departemen sedang aktif digunakan oleh entitas lain (misalnya tabel Pegawai).
