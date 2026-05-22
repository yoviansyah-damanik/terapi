<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_questionnaire_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('local_id')->nullable()->index()->comment('no_resep atau identifier lokal SIMRS');
            $table->json('identifier')->nullable()->index();
            $table->string('type')->default('telaah_farmasi')->index()->comment('Tipe QR: telaah_farmasi, dll.');
            $table->string('questionnaire')->comment('URL Questionnaire FHIR');
            $table->string('status')->default('completed');
            $table->string('patient_ihs')->index();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('author_ihs')->nullable();
            $table->timestamp('authored')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('patient_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_patients')
                ->cascadeOnDelete();

            $table->foreign('encounter_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_encounters')
                ->nullOnDelete();

            $table->foreign('author_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_practitioners')
                ->nullOnDelete();

            $table->index(['patient_ihs', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_questionnaire_responses');
    }
};
