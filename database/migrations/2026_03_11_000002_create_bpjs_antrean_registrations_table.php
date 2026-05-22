<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bpjs_antrean_registrations', function (Blueprint $table) {
            $table->string('no_rawat', 20)->primary();
            $table->string('tanggal', 10)->index();
            $table->string('kd_poli', 20)->index();
            $table->string('nm_poli', 150)->nullable();
            $table->string('kd_dokter', 20);
            $table->string('nm_dokter', 150)->nullable();
            $table->string('status_lanjut', 10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_antrean_registrations');
    }
};
