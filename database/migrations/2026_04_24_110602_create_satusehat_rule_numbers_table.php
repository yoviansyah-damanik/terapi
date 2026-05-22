<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satusehat_rule_numbers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('(UUID())'));
            $table->string('rule_no')->unique()->index();
            $table->text('path')->nullable();
            $table->text('terminology_used')->nullable();
            $table->text('error_description')->nullable();
            $table->string('rule_last_update')->nullable();
            $table->string('version')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satusehat_rule_numbers');
    }
};
