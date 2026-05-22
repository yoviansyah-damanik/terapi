<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_observations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('local_id')->nullable()->index();
            $table->json('identifier')->nullable()->index();
            $table->string('patient_ihs')->index();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('status')->default('final')->comment('registered, preliminary, final, amended, corrected, cancelled, entered-in-error, unknown');
            $table->string('category')->nullable()->comment('vital-signs, laboratory, imaging, etc');
            $table->string('code')->nullable()->index()->comment('LOINC code');
            $table->string('code_display')->nullable();
            $table->string('value_type')->nullable()->comment('Quantity, String, CodeableConcept, etc');
            $table->decimal('value_quantity', 15, 4)->nullable();
            $table->string('value_unit')->nullable();
            $table->text('value_string')->nullable();
            $table->json('value_codeable_concept')->nullable();
            $table->timestamp('effective_datetime')->nullable();
            $table->string('performer_ihs')->nullable();
            $table->json('interpretation')->nullable();
            $table->json('reference_range')->nullable();
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
            $table->index(['encounter_ihs', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_observations');
    }
};
