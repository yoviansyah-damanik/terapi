<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_simrs_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identitas pengirim
            $table->string('app_version', 50)->nullable()->comment('Versi aplikasi SIMRS');
            $table->string('host_name', 100)->nullable()->comment('Nama/hostname server SIMRS');
            $table->string('ip_address', 45)->nullable()->comment('IP address server SIMRS');

            // Klasifikasi log
            $table->enum('level', ['error', 'warning', 'info', 'debug'])->default('error')->index();
            $table->string('category', 100)->nullable()->index()->comment('Kategori: NullPointerException, SQLException, dll');
            $table->string('module', 100)->nullable()->index()->comment('Modul SIMRS: pendaftaran, rawat_jalan, farmasi, dll');

            // Identitas error dari sisi SIMRS
            $table->string('error_id', 100)->nullable()->unique()->comment('ID error dari aplikasi SIMRS (opsional, untuk dedup)');

            // Pesan & detail error
            $table->text('message')->comment('Pesan log/error utama');
            $table->string('exception_class', 255)->nullable()->comment('Nama class exception Java');
            $table->longText('stack_trace')->nullable()->comment('Stack trace lengkap dari Java');

            // Informasi sesi / user di SIMRS
            $table->string('simrs_user', 100)->nullable()->comment('Username yang sedang login di SIMRS');
            $table->string('simrs_user_role', 100)->nullable()->comment('Role pengguna SIMRS');

            // Informasi koneksi database SIMRS
            $table->string('db_host', 100)->nullable();
            $table->string('db_name', 100)->nullable();
            $table->boolean('db_connected')->nullable()->comment('Status koneksi DB saat error terjadi');
            $table->unsignedSmallInteger('db_response_time_ms')->nullable()->comment('Waktu respons DB dalam milidetik');

            // Context tambahan (JSON bebas)
            $table->json('context')->nullable()->comment('Data konteks tambahan dari aplikasi');

            $table->timestamps();

            $table->index('created_at');
            $table->index(['level', 'created_at']);
            $table->index(['category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_simrs_logs');
    }
};
