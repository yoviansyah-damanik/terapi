<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_compositions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('identifier')->nullable()->index();
            $table->string('local_id')->nullable()->index();
            $table->string('patient_ihs')->index();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('author_ihs')->nullable()->comment('Practitioner yang membuat');
            $table->string('composition_type')->nullable()->index()
                ->comment('resume_ralan, resume_ranap, catatan_gizi, resume_keperawatan, resume_farmasi');
            $table->string('status')->default('final')->comment('preliminary, final, amended, entered-in-error');
            $table->string('type_code')->nullable()->comment('LOINC code');
            $table->string('type_display')->nullable();
            $table->string('title')->nullable();
            $table->timestamp('date')->nullable();
            $table->string('custodian_ihs')->nullable()->comment('Organization IHS');
            $table->json('raw_response')->nullable();
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

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['patient_ihs', 'type_code']);
            $table->index(['encounter_ihs', 'composition_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_compositions');
    }
};
