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
        Schema::create('loinc', function (Blueprint $table) {
            $table->string('loinc_num')->primary();
            $table->text('component')->nullable();
            $table->string('property')->nullable();
            $table->string('time_aspct')->nullable();
            $table->string('system')->nullable();
            $table->string('scale_typ')->nullable();
            $table->string('method_typ')->nullable();
            $table->string('class')->nullable();
            $table->string('classtype')->nullable(); // CLASSTYPE
            $table->text('long_common_name')->nullable();
            $table->string('shortname')->nullable();
            $table->text('external_copyright_notice')->nullable();
            $table->string('status')->nullable();
            $table->string('version_first_released')->nullable();
            $table->string('version_last_changed')->nullable();
            $table->string('url')->default('http://loinc.org');
            $table->timestamps();

            $table->index('property');
            $table->index('system');
            $table->index('class');
            $table->fullText(['component', 'long_common_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loinc');
    }
};
