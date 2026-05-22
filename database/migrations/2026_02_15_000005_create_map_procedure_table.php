<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('map_procedure', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('procedure_code', 20)->index();
            $table->enum('source_table', ['jalan', 'inap', 'lab', 'radiologi', 'operasi'])->index();
            $table->string('system_code', 20)->index();
            $table->string('system_term', 255)->nullable();
            $table->string('system_display')->default('http://snomed.info/sct');
            $table->string('category_code', 20)->nullable()->index();
            $table->string('category_term', 255)->nullable();
            $table->string('category_display')->default('http://snomed.info/sct');
            $table->timestamps();

            $table->unique(['procedure_code', 'source_table', 'system_code'], 'map_proc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_procedure');
    }
};
