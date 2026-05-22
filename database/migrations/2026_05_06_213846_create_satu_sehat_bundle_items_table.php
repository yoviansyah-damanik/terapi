<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('satu_sehat_bundle_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bundle_log_id')->index();
            $table->string('resource_type', 50)->index();
            $table->string('local_id', 100)->nullable()->index();
            $table->string('ihs_id', 100)->nullable()->index();
            $table->string('status', 20)->default('pending')->index();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('bundle_log_id')->references('id')->on('satu_sehat_bundles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_bundle_items');
    }
};
