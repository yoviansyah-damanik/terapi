<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icd_pm', function (Blueprint $table) {
            $table->id();
            $table->string('category', 5)->nullable();           // A, I, N, M
            $table->string('category_display', 200)->nullable(); // ANTEPARTUM DEATH, dst.
            $table->string('subcategory', 10)->nullable();       // A1, A2, I1, N10, dst.
            $table->string('subcategory_display', 500)->nullable();
            $table->string('code', 30);
            $table->string('display', 1000)->nullable();
            $table->string('version', 50);
            $table->timestamps();

            $table->unique(['code', 'version']);
            $table->index(['version', 'category', 'subcategory']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icd_pm');
    }
};
