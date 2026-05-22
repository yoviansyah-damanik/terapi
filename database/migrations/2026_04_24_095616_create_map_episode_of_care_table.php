<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('map_episode_of_care', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('(UUID())'));
            $table->string('eoc_code');           // FK logis ke fhir_dictionaries.system_code (episode-of-care-type)
            $table->string('icd10_code');         // FK logis ke icd10.code
            $table->text('notes')->nullable();     // Catatan opsional
            $table->timestamps();

            $table->unique(['eoc_code', 'icd10_code']);
            $table->index('eoc_code');
            $table->index('icd10_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_episode_of_care');
    }
};
