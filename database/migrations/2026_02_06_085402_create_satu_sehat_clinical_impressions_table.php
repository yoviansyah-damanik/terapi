<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_clinical_impressions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('identifier')->nullable()->index();
            $table->string('local_id')->nullable()->index();
            $table->string('patient_ihs')->index();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('assessor_ihs')->nullable()->comment('Practitioner yang melakukan assessment');
            $table->string('status')->default('completed')->comment('in-progress, completed, entered-in-error');
            $table->timestamp('effective_datetime')->nullable();
            $table->text('description')->nullable();
            $table->json('finding')->nullable();
            $table->json('problem')->nullable()->comment('Array of Condition references');
            $table->text('summary')->nullable();
            $table->json('prognosis_codeable_concept')->nullable();
            $table->json('raw_response')->nullable();
            $table->foreign('patient_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_patients')
                ->cascadeOnDelete();

            $table->foreign('encounter_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_encounters')
                ->nullOnDelete();

            $table->foreign('assessor_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_practitioners')
                ->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_clinical_impressions');
    }
};
