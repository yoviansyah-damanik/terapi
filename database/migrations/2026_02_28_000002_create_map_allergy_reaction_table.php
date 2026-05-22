<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_allergy_reaction', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('reaction_id')->unique()->comment('ID dari tabel reaksi_alergi SIMRS');
            $table->string('system_code', 50)->nullable()->comment('Kode SNOMED CT');
            $table->string('system_term', 500)->nullable()->comment('Term/nama dari SNOMED CT');
            $table->string('system_display')->default('http://snomed.info/sct')->comment('URL sistem terminologi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_allergy_reaction');
    }
};
