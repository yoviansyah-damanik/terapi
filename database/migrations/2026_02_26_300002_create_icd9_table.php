<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icd9', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30);
            $table->string('display', 500)->nullable();
            $table->string('version', 50);
            $table->timestamps();

            $table->unique(['code', 'version']);
            $table->index('version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icd9');
    }
};
