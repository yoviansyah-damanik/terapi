<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_bundles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_rawat', 20)->index();
            $table->uuid('triggered_by')->nullable()->index();
            $table->string('status', 20)->default('queued')->index();
            $table->json('results')->nullable();
            $table->json('warnings')->nullable();
            $table->json('errors')->nullable();
            $table->integer('total_sent')->default(0);
            $table->integer('total_errors')->default(0);
            $table->boolean('encounter_finished')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('triggered_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['no_rawat', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_bundles');
    }
};
