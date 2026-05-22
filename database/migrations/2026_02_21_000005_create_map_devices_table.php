<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel mapping alat kesehatan lokal ke KFA (Kamus Alkes) Satu Sehat.
 * Memisahkan dari map_obat_kfa lama.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('local_code')->unique()->index();
            $table->string('kfa_code', 50)->nullable();
            $table->string('kfa_name')->nullable();
            $table->string('system_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_devices');
    }
};
