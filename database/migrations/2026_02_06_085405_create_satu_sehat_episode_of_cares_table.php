<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_episode_of_cares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('identifier')->nullable()->index();
            $table->string('local_id')->nullable()->index();
            $table->string('patient_ihs')->index();
            $table->string('managing_organization_ihs')->nullable();
            $table->string('care_manager_ihs')->nullable();
            $table->string('status')->default('active')->comment('planned, waitlist, active, onhold, finished, cancelled, entered-in-error');
            $table->string('type_code')->nullable();
            $table->string('type_display')->nullable();
            $table->json('diagnosis')->nullable()->comment('Array of Condition references with rank');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->json('raw_response')->nullable();
            $table->foreign('patient_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_patients')
                ->cascadeOnDelete();

            $table->foreign('managing_organization_ihs')
                ->references('ihs_number')
                ->on('satu_sehat_organizations')
                ->nullOnDelete();

            $table->foreign('care_manager_ihs')
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
        Schema::dropIfExists('satu_sehat_episode_of_cares');
    }
};
