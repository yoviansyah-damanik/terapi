<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_procedures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('local_id')->nullable()->index();
            $table->json('identifier')->nullable()->index();
            $table->string('patient_ihs')->index();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('performer_ihs')->nullable();
            $table->string('status')->default('completed')->comment('preparation, in-progress, not-done, on-hold, stopped, completed, entered-in-error, unknown');
            $table->string('category')->nullable()->comment('SNOMED CT code');
            $table->string('code')->nullable()->index()->comment('ICD-9-CM code');
            $table->string('code_display')->nullable();
            $table->timestamp('performed_datetime')->nullable();
            $table->timestamp('performed_period_start')->nullable();
            $table->timestamp('performed_period_end')->nullable();
            $table->string('reason_code')->nullable()->comment('ICD-10 code');
            $table->string('body_site')->nullable()->comment('SNOMED CT code');
            $table->string('outcome')->nullable()->comment('SNOMED CT code');
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

            $table->foreign('performer_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_practitioners')
                ->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['patient_ihs', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_procedures');
    }
};
