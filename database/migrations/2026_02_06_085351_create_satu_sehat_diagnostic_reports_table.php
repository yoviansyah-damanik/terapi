<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_diagnostic_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('identifier')->nullable()->index();
            $table->string('local_id')->nullable()->index();
            $table->string('patient_ihs')->index();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('service_request_ihs')->nullable()->index()->comment('Reference ke ServiceRequest');
            $table->string('performer_ihs')->nullable();
            $table->string('status')->default('final')->comment('registered, partial, preliminary, final, amended, corrected, appended, cancelled, entered-in-error, unknown');
            $table->string('category')->nullable()->comment('LAB, RAD, PAT, etc');
            $table->string('code')->nullable()->index()->comment('LOINC code');
            $table->string('code_display')->nullable();
            $table->timestamp('effective_datetime')->nullable();
            $table->timestamp('issued')->nullable();
            $table->json('result')->nullable()->comment('Array of Observation IHS');
            $table->json('specimen')->nullable()->comment('Array of Specimen IHS');
            $table->text('conclusion')->nullable();
            $table->json('conclusion_code')->nullable();
            $table->json('raw_response')->nullable();
            $table->foreign('patient_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_patients')
                ->cascadeOnDelete();

            $table->foreign('encounter_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_encounters')
                ->nullOnDelete();

            $table->foreign('service_request_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_service_requests')
                ->nullOnDelete();

            $table->foreign('performer_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_practitioners')
                ->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['patient_ihs', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_diagnostic_reports');
    }
};
