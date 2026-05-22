<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel mapping obat lokal ke KFA (Kamus Farmasi) Satu Sehat.
 * Memisahkan dari map_obat_kfa lama.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_medication', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('local_code')->unique()->index();
            $table->string('kfa_code', 50)->nullable();
            $table->string('kfa_name')->nullable();
            $table->string('system_url')->nullable();
            $table->string('form_code')->nullable();
            $table->string('form_name')->nullable();
            $table->string('form_display', 255)->nullable();
            $table->string('route_code')->nullable();
            $table->string('route_name')->nullable();
            $table->string('route_display', 255)->nullable();
            $table->string('numerator_code')->nullable();
            $table->string('numerator_name')->nullable();
            $table->string('numerator_display', 255)->nullable();
            $table->string('denominator_code')->nullable();
            $table->string('denominator_name')->nullable();
            $table->string('denominator_display', 255)->nullable();
            $table->string('controlled_drug_code', 50)->nullable();
            $table->string('controlled_drug_name', 255)->nullable();
            $table->string('controlled_drug_display', 255)->nullable();
            $table->string('medication_type_code', 50)->nullable();
            $table->string('medication_type_name', 100)->nullable();
            $table->string('medication_type_display', 255)->nullable();
            $table->string('immunization_reason_code', 50)->nullable();
            $table->string('immunization_reason_name', 255)->nullable();
            $table->string('immunization_routine_timing_code', 50)->nullable();
            $table->string('immunization_routine_timing_name', 255)->nullable();
            $table->json('kfa_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_medication');
    }
};
