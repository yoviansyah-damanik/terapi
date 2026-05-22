<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_care_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('identifier')->nullable()->index();
            $table->string('local_id')->nullable()->index();
            $table->string('patient_ihs')->index();
            $table->string('encounter_ihs')->nullable()->index();
            $table->string('author_ihs')->nullable();
            $table->string('status')->default('active')->comment('draft, active, on-hold, revoked, completed, entered-in-error, unknown');
            $table->string('intent')->default('plan')->comment('proposal, plan, order, option');
            $table->string('title')->nullable();
            $table->string('category_code')->nullable()->comment('SNOMED CT code');
            $table->string('category_display')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('created')->nullable();
            $table->json('activity')->nullable();
            $table->json('goal')->nullable()->comment('Array of Goal references');
            $table->json('addresses')->nullable()->comment('Array of Condition references');
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

            $table->foreign('author_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_practitioners')
                ->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['patient_ihs', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_care_plans');
    }
};
