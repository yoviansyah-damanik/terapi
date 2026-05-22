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
        Schema::create('map_surgery_note', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('procedure_code', 20)->unique();
            $table->string('loinc_code', 20)->index();
            $table->string('loinc_term', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_surgery_note');
    }
};
