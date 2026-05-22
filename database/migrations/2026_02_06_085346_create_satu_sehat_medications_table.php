<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satu_sehat_medications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('identifier')->nullable()->index()->comment('Identifier internal');
            $table->string('kfa_code')->nullable()->index()->comment('Kode KFA');
            $table->string('kfa_display')->nullable();
            $table->string('status')->default('active')->comment('active, inactive, entered-in-error');
            $table->string('form_code')->nullable();
            $table->string('form_display')->nullable();
            $table->json('ingredient')->nullable();
            $table->string('medication_type')->nullable()->comment('NC (Non-compound), SD (Sediaan)');
            $table->json('extension')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_medications');
    }
};
