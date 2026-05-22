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
        Schema::create('map_diagnostic_category', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('local_code', 20)->unique()->index()
                  ->comment('Kode jenis perawatan dari Lab/Rad SIMRS (kd_jenis_prw)');
            $table->string('diagnostic_category', 10)
                  ->comment('Kode diagnostic-category HL7 v2-0074: LAB, CH, HM, RAD, dll');
            $table->string('diagnostic_category_term', 100);
            $table->enum('source', ['lab', 'rad'])->comment('Tanda asal kategori: lab atau rad');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_diagnostic_category');
    }
};
