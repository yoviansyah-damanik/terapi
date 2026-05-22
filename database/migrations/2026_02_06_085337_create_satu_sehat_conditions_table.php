<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_conditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('local_id')->nullable()->index();
            $table->json('identifier')->nullable()->index();
            $table->string('patient_ihs')->index();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('clinical_status')->default('active')->comment('active, recurrence, relapse, inactive, remission, resolved');
            $table->string('verification_status')->nullable()->comment('unconfirmed, provisional, differential, confirmed, refuted, entered-in-error');
            $table->string('category')->nullable()->comment('problem-list-item, encounter-diagnosis');
            $table->string('icd_code')->nullable()->index();
            $table->string('icd_display')->nullable();
            $table->timestamp('onset_datetime')->nullable();
            $table->timestamp('abatement_datetime')->nullable();
            $table->string('recorder_ihs')->nullable();
            $table->text('note')->nullable();
            $table->json('raw_response')->nullable();
            $table->foreign('patient_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_patients')
                ->cascadeOnDelete();

            $table->foreign('encounter_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_encounters')
                ->nullOnDelete();

            $table->foreign('recorder_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_practitioners')
                ->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['patient_ihs', 'icd_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_conditions');
    }
};
