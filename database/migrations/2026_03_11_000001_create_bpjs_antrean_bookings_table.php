<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bpjs_antrean_bookings', function (Blueprint $table) {
            $table->string('kode_booking', 50)->primary();
            $table->string('tanggal', 10)->index();
            $table->string('kd_poli', 20)->nullable();
            $table->string('kd_dokter', 20)->nullable();
            $table->string('jam_praktek', 20)->nullable();
            $table->string('nik', 20)->nullable()->index();
            $table->string('no_kartu', 25)->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->string('no_rm', 20)->nullable();
            $table->string('jenis_kunjungan', 5)->nullable();
            $table->string('no_referensi', 50)->nullable();
            $table->string('sumber_data', 60)->nullable();
            $table->boolean('is_peserta')->default(false);
            $table->string('no_antrean', 20)->nullable();
            $table->bigInteger('estimasi_timestamp')->nullable();
            $table->string('status', 50)->nullable();
            $table->bigInteger('created_time_timestamp')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_antrean_bookings');
    }
};
