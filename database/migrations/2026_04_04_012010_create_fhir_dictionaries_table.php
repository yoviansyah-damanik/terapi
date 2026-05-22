<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fhir_dictionaries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();
            $table->string('system_code', 100)->nullable();
            $table->string('system_term', 255)->nullable();
            $table->text('system_defenition')->nullable();
            $table->string('system_display', 255)->nullable();
            $table->string('source', 20)->default('internal')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fhir_dictionaries');
    }
};
