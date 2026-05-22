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
        Schema::create('medical_lexicons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('layman_term')->unique()->comment('Istilah awam (Bahasa Indonesia)');
            $table->string('clinical_term')->comment('Target terminologi klinis (English)');
            $table->string('snomed_concept_id')->nullable()->comment('Pre-mapped SNOMED ID');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_lexicons');
    }
};
