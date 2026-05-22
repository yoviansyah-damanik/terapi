<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] #[Title('Panduan Aplikasi')] class extends Component {
    #[Url]
    public string $tab = 'umum';
}; ?>

<div class="space-y-6">
    <x-ui.page-header title="Panduan Aplikasi" subtitle="Dokumentasi lengkap penggunaan aplikasi Terapi, fitur integrasi, dan arsitektur sistem cerdas." />

    <x-organisms.data-panel>
        <div class="px-6 pt-4 border-b border-zinc-200 dark:border-primary-dark-800 hidden md:block">
            <x-molecules.tabs class="border-none mb-0">
                <x-atoms.tab-item wire:click="$set('tab', 'umum')" :active="$tab === 'umum'">Tinjauan Sistem</x-atoms.tab-item>
                <x-atoms.tab-item wire:click="$set('tab', 'quickstart')" :active="$tab === 'quickstart'">Mulai Cepat</x-atoms.tab-item>
                <x-atoms.tab-item wire:click="$set('tab', 'integrasi')" :active="$tab === 'integrasi'">Modul Integrasi</x-atoms.tab-item>
                <x-atoms.tab-item wire:click="$set('tab', 'ai')" :active="$tab === 'ai'">Smart AI & Terminologi</x-atoms.tab-item>
                <x-atoms.tab-item wire:click="$set('tab', 'utilitas')" :active="$tab === 'utilitas'">Utilitas & Backup</x-atoms.tab-item>
            </x-molecules.tabs>
        </div>
        
        {{-- Mobile dropdown tabs --}}
        <div class="p-4 md:hidden border-b border-zinc-200 dark:border-primary-dark-800">
            <flux:select wire:model.live="tab">
                <flux:select.option value="umum">Tinjauan Sistem</flux:select.option>
                <flux:select.option value="quickstart">Mulai Cepat</flux:select.option>
                <flux:select.option value="integrasi">Modul Integrasi</flux:select.option>
                <flux:select.option value="ai">Smart AI & Terminologi</flux:select.option>
                <flux:select.option value="utilitas">Utilitas & Backup</flux:select.option>
            </flux:select>
        </div>

        <div class="p-6">
            @if($tab === 'umum')
                <article class="prose prose-sm md:prose-base prose-zinc dark:prose-invert max-w-none">
                    <h3 class="text-zinc-800 dark:text-primary-dark-200 font-semibold mb-3">Selamat Datang di Terapi</h3>
                    <p class="text-zinc-600 dark:text-primary-dark-400">
                        <strong>Terapi</strong> adalah sistem terpadu (Enterprise Service Bus & API Gateway) yang didesain secara khusus untuk menjembatani operasional Sistem Informasi Manajemen Rumah Sakit (SIMRS) 
                        dengan ekosistem kesehatan eksternal, seperti <strong>BPJS Kesehatan</strong>, <strong>Satu Sehat Kemenkes</strong>, serta layanan digital lainnya.
                    </p>
                    
                    <h4 class="text-zinc-800 dark:text-primary-dark-200 font-semibold mt-6 mb-2">Pilar Utama Aplikasi</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4 not-prose">
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-800/30">
                            <flux:icon name="arrows-right-left" class="w-6 h-6 text-blue-500 mb-2" />
                            <h5 class="font-medium text-zinc-900 dark:text-zinc-100">Konektivitas Fleksibel</h5>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Mengelola integrasi BPJS, Satu Sehat, dan layanan lainnya tanpa perlu merombak SIMRS utama.</p>
                        </div>
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-800/30">
                            <flux:icon name="language" class="w-6 h-6 text-emerald-500 mb-2" />
                            <h5 class="font-medium text-zinc-900 dark:text-zinc-100">Translasi Data Medis</h5>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Menerjemahkan data klinis lokal ke format standar nasional (FHIR, HL7, SNOMED, LOINC).</p>
                        </div>
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-primary-dark-700 bg-zinc-50 dark:bg-primary-dark-800/30">
                            <flux:icon name="shield-check" class="w-6 h-6 text-purple-500 mb-2" />
                            <h5 class="font-medium text-zinc-900 dark:text-zinc-100">Kepatuhan & Keamanan</h5>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Seluruh audit log tersimpan dengan aman, serta mendukung pendelegasian otorisasi (API Gateway).</p>
                        </div>
                    </div>
                </article>

            @elseif($tab === 'quickstart')
                <article class="prose prose-sm md:prose-base prose-zinc dark:prose-invert max-w-none">
                    <h3 class="text-zinc-800 dark:text-primary-dark-200 font-semibold mb-3">Langkah - Langkah Memulai Aplikasi</h3>
                    <p class="text-zinc-600 dark:text-primary-dark-400">
                        Untuk memastikan seluruh fungsi integrasi aplikasi Terapi berjalan maksimal, pastikan Anda telah menyelesaikan hal-hal berikut:
                    </p>

                    <ol class="list-decimal list-outside ml-4 space-y-4 font-medium text-zinc-800 dark:text-zinc-200 mt-6">
                        <li>
                            Konfigurasi Rumah Sakit
                            <p class="font-normal text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                Masuk ke menu <strong>Pengaturan &gt; Rumah Sakit</strong>. Lengkapi profil Rumah Sakit termasuk kode faskes, alamat wilayah administratif standar Kemendagri, dan zona waktu.
                            </p>
                        </li>
                        <li>
                            Konfigurasi Konektivitas & Kredensial
                            <p class="font-normal text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                Pada <strong>Pengaturan &gt; Konektivitas</strong>, pastikan seluruh *Client ID*, *Secret*, URL environment (Development/Production), 
                                dan *Passphrase* BPJS maupun SatuSehat telah terisi lengkap. Uji koneksi pada opsi <span class="bg-zinc-100 dark:bg-zinc-800 px-1 rounded">Utility &gt; Status Koneksi</span>.
                            </p>
                        </li>
                        <li>
                            Sinkronisasi Terminologi Master
                            <p class="font-normal text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                Buka bagian <strong>Terminologi</strong>. Pastikan Anda telah mengimpor tabel referensi ICD-10, ICD-9, serta meloloskan sinkronisasi HFIS Pegawai / Dokter. 
                                Sistem AI membutuhkan basis data referensi ini sebagai pedoman.
                            </p>
                        </li>
                    </ol>
                </article>

            @elseif($tab === 'integrasi')
                <article class="prose prose-sm md:prose-base prose-zinc dark:prose-invert max-w-none">
                    <h3 class="text-zinc-800 dark:text-primary-dark-200 font-semibold flex items-center gap-2 mb-3">
                        <flux:icon name="puzzle-piece" class="w-5 h-5 text-indigo-500" />
                        Modul & Fitur Integrasi Terapi
                    </h3>
                    
                    <p class="text-zinc-600 dark:text-primary-dark-400 mb-6">
                        Berikut adalah ragam layanan integrasi dua arah yang dikelola secara independen oleh aplikasi ini:
                    </p>

                    <div class="space-y-4 not-prose">
                        <div class="flex flex-col md:flex-row gap-4 p-4 border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-zinc-900 rounded-xl">
                            <div class="w-10 h-10 flex-shrink-0 flex items-center justify-center bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 rounded-lg">
                                <flux:icon name="clipboard-document-check" class="w-5 h-5" />
                            </div>
                            <div>
                                <h4 class="font-semibold text-zinc-800 dark:text-zinc-100">BPJS Kesehatan (VClaim, Antrean, Aplicare)</h4>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                    Mendukung Bridging lengkap sistem SEP, pengiriman antrean *realtime* (Task ID), data *bed* RS, dan modul farmasi BPJS. 
                                    Anda dapat melacak log lengkapnya di menu Log BPJS.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col md:flex-row gap-4 p-4 border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-zinc-900 rounded-xl">
                            <div class="w-10 h-10 flex-shrink-0 flex items-center justify-center bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 rounded-lg">
                                <flux:icon name="globe-alt" class="w-5 h-5" />
                            </div>
                            <div>
                                <h4 class="font-semibold text-zinc-800 dark:text-zinc-100">Kemenkes (Satu Sehat & SIRS)</h4>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                    Modul untuk mengirimkan rekam medis pasien menjadi format sumber daya (Resource) FHIR. Selain itu, mendukung pengumpulan laporan standar SIRS (RL) secara otomatis tanpa olah data Excel.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col md:flex-row gap-4 p-4 border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-zinc-900 rounded-xl">
                            <div class="w-10 h-10 flex-shrink-0 flex items-center justify-center bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400 rounded-lg">
                                <flux:icon name="cpu-chip" class="w-5 h-5" />
                            </div>
                            <div>
                                <h4 class="font-semibold text-zinc-800 dark:text-zinc-100">API Portal Terpusat</h4>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                    Ingin diakses balik oleh *Mobile App* rumah sakit atau rekanan pihak ke-3? Buatkan kredensial di <strong>API Portal > Manajemen API</strong> 
                                    untuk memberikan akses aman membaca data kunjungan, token, dengan kapabilitas kontrol `scopes`.
                                </p>
                            </div>
                        </div>
                    </div>
                </article>

            @elseif($tab === 'ai')
                <article class="prose prose-sm md:prose-base prose-zinc dark:prose-invert max-w-none">
                    <h3 class="text-zinc-800 dark:text-primary-dark-200 font-semibold mb-4 flex items-center gap-2">
                        <flux:icon name="sparkles" class="w-5 h-5 text-amber-500" />
                        Teknologi & Algoritma Smart AI
                    </h3>
                    <p class="text-zinc-600 dark:text-primary-dark-400 mb-6">
                        Proses pengiriman rekam medis (terutama Satu Sehat/FHIR) membutuhkan data yang bersih dengan taksonomi baku. 
                        Terapi menggunakan model <strong>Smart AI Search</strong> untuk memecahkan inkonsistensi teks klinisi (typo, gaya bahasa non-standar, atau singkatan rawat jalan).
                    </p>
                    
                    <div class="space-y-6 not-prose">
                        <div class="relative pl-6 border-l-2 border-zinc-200 dark:border-primary-dark-700">
                            <div class="absolute w-3 h-3 bg-zinc-400 dark:bg-primary-dark-500 rounded-full -left-[7px] top-1.5 ring-4 ring-white dark:ring-zinc-900"></div>
                            <h4 class="text-base font-semibold text-zinc-800 dark:text-zinc-200">1. Levenshtein Distance & Jaro-Winkler</h4>
                            <p class="text-sm text-zinc-500 dark:text-primary-dark-400 mt-1">
                                Digunakan pada algoritma <em>Fuzzy String Matching</em> untuk menghitung kemiripan kata. 
                                Memastikan input mentah medis dari SIMRS seperti <strong>"Typus Abdominalis"</strong> tetap dapat di-match ke master <strong>"Tifus" / "ICD-10: A01"</strong>.
                            </p>
                        </div>

                        <div class="relative pl-6 border-l-2 border-zinc-200 dark:border-primary-dark-700">
                            <div class="absolute w-3 h-3 bg-zinc-400 dark:bg-primary-dark-500 rounded-full -left-[7px] top-1.5 ring-4 ring-white dark:ring-zinc-900"></div>
                            <h4 class="text-base font-semibold text-zinc-800 dark:text-zinc-200">2. TF-IDF & Semantic Vectorization</h4>
                            <p class="text-sm text-zinc-500 dark:text-primary-dark-400 mt-1">
                                Saat Anda memanfaatkan fitur <em>Smart Search Terminologi</em>, teks panjang diekstraksi ke dalam vektor.
                                <em>Term Frequency-Inverse Document Frequency</em> mengisolasi istilah penting dari kata penghubung sehingga pencocokan kode SNOMED-CT jauh lebih akurat.
                            </p>
                        </div>

                        <div class="relative pl-6 border-l-2 border-transparent">
                            <div class="absolute w-3 h-3 bg-amber-400 dark:bg-amber-500 rounded-full -left-[7px] top-1.5 ring-4 ring-white dark:ring-zinc-900 shadow-sm shadow-amber-500/50"></div>
                            <h4 class="text-base font-semibold text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                                3. Generative Language Models (LLM) Framework
                            </h4>
                            <p class="text-sm text-zinc-500 dark:text-primary-dark-400 mt-1">
                                Bila opsi <em>AI Provider</em> aktif (via OpenAI / Anthropic API), Terapi dapat mengirim <em>free-text</em> narasi seperti Asuhan Keperawatan 
                                lalu model menyajikan pemetaan terstruktur berupa JSON berisi ekstraksi <em>Symptom, Diagnosis, Medication, dan Procedure</em>.
                            </p>
                        </div>
                    </div>

                    <div class="mt-8 p-4 bg-zinc-50 dark:bg-primary-dark-800/20 border border-zinc-200 dark:border-primary-dark-700 rounded-xl">
                        <h4 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                            <flux:icon name="bolt" class="w-4 h-4 text-emerald-500" />
                            Redis Caching Layer
                        </h4>
                        <p class="text-sm text-zinc-500 dark:text-primary-dark-400 mt-1">
                            Setiap pemetaan istilah yang rumit akan dihafal <em>(cached)</em> oleh sistem. Pada transaksi pencarian berikutnya untuk masalah serupa, skor pencarian *Smart AI* akan dibajak oleh Redis agar merespon seketika (sub-10ms latency).
                        </p>
                    </div>
                </article>
            @elseif($tab === 'utilitas')
                <article class="prose prose-sm md:prose-base prose-zinc dark:prose-invert max-w-none">
                    <h3 class="text-zinc-800 dark:text-primary-dark-200 font-semibold flex items-center gap-2 mb-3">
                        <flux:icon name="circle-stack" class="w-5 h-5 text-teal-500" />
                        Backup Database
                    </h3>

                    <p class="text-zinc-600 dark:text-primary-dark-400">
                        Fitur <strong>Backup Database</strong> tersedia di menu <strong>Utilitas Sistem → Backup Database</strong>.
                        Sistem mendukung backup untuk dua database:
                    </p>

                    <div class="space-y-4 not-prose mt-4">
                        <div class="flex gap-4 p-4 border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-zinc-900 rounded-xl">
                            <div class="w-10 h-10 flex-shrink-0 flex items-center justify-center bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400 rounded-lg">
                                <flux:icon name="circle-stack" class="w-5 h-5" />
                            </div>
                            <div>
                                <h4 class="font-semibold text-zinc-800 dark:text-zinc-100">Terapi (Database Utama)</h4>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                    Backup seluruh data konfigurasi, mapping terminologi, log integrasi, dan data operasional Terapi.
                                    Mendukung MySQL/MariaDB (menggunakan <code>mysqldump</code>) maupun SQLite.
                                    File hasil backup disimpan dalam format <code>.sql.gz</code> / <code>.sqlite.gz</code>.
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-4 p-4 border border-zinc-200 dark:border-primary-dark-700 bg-white dark:bg-zinc-900 rounded-xl">
                            <div class="w-10 h-10 flex-shrink-0 flex items-center justify-center bg-sky-100 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400 rounded-lg">
                                <flux:icon name="circle-stack" class="w-5 h-5" />
                            </div>
                            <div>
                                <h4 class="font-semibold text-zinc-800 dark:text-zinc-100">SIMRS (Database Eksternal)</h4>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                    Backup database SIMRS menggunakan koneksi <code>DB_SIMRS_*</code> yang telah dikonfigurasi.
                                    Berguna sebagai cadangan data rekam medis dari sisi integrasi.
                                </p>
                            </div>
                        </div>
                    </div>

                    <h4 class="text-zinc-800 dark:text-primary-dark-200 font-semibold mt-6 mb-3">Cara Penggunaan</h4>

                    <div class="space-y-3 not-prose">
                        @foreach([
                            ['icon' => 'hand-raised', 'color' => 'blue', 'title' => 'Backup Manual', 'desc' => 'Klik tombol "Backup Sekarang" di sudut kanan atas halaman. Proses berjalan di background (queue) dan status diperbarui otomatis setiap 5 detik.'],
                            ['icon' => 'clock', 'color' => 'violet', 'title' => 'Backup Terjadwal (Scheduler)', 'desc' => 'Aktifkan toggle "Aktifkan Scheduler" lalu pilih frekuensi: Setiap Jam, Harian, Mingguan, atau Custom Cron Expression. Jadwal aktif saat `php artisan schedule:run` berjalan (disarankan via cron OS setiap menit).'],
                            ['icon' => 'trash', 'color' => 'amber', 'title' => 'Pengaturan Retensi', 'desc' => 'Atur jumlah backup yang dipertahankan (N terakhir) dan/atau batas usia backup dalam hari. Backup kadaluarsa dihapus otomatis setiap kali backup baru dibuat.'],
                            ['icon' => 'arrow-down-tray', 'color' => 'emerald', 'title' => 'Download & Log', 'desc' => 'Setiap entry riwayat memiliki tombol Download (untuk backup berhasil) dan Log (untuk melihat detail: waktu, durasi, ukuran file, pesan error).'],
                        ] as $item)
                            <div class="flex gap-3 p-3.5 rounded-xl border border-zinc-100 dark:border-primary-dark-700 bg-zinc-50/50 dark:bg-primary-dark-800/30">
                                <div class="w-8 h-8 flex-shrink-0 flex items-center justify-center bg-{{ $item['color'] }}-100 text-{{ $item['color'] }}-600 dark:bg-{{ $item['color'] }}-900/30 dark:text-{{ $item['color'] }}-400 rounded-lg mt-0.5">
                                    <flux:icon name="{{ $item['icon'] }}" class="w-4 h-4" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $item['title'] }}</p>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $item['desc'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 p-4 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800/40 rounded-xl not-prose">
                        <h4 class="text-sm font-semibold text-amber-800 dark:text-amber-300 flex items-center gap-2">
                            <flux:icon name="exclamation-triangle" class="w-4 h-4" />
                            Prasyarat Scheduler
                        </h4>
                        <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                            Agar backup terjadwal berjalan, pastikan perintah <code>php artisan schedule:run</code> terdaftar di cron OS server:
                        </p>
                        <pre class="mt-2 text-xs bg-amber-100 dark:bg-amber-900/20 rounded-lg p-3 overflow-x-auto font-mono text-amber-900 dark:text-amber-300">* * * * * cd /path/to/terapi && php artisan schedule:run >> /dev/null 2>&1</pre>
                    </div>
                </article>
            @endif
        </div>
    </x-organisms.data-panel>
</div>
