<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_bundle_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bundle_id')->index();
            $table->string('resource_type', 50)->index();
            $table->string('local_id', 150)->nullable()->index();
            $table->string('ihs_id', 100)->nullable()->index();
            $table->string('status', 20)->default('pending')->index();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('bundle_id')->references('id')->on('satu_sehat_bundles')->onDelete('cascade');
            $table->index(['bundle_id', 'resource_type']);
            $table->index(['bundle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_bundle_logs');
    }
};
