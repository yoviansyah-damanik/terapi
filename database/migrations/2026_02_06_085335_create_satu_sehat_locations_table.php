<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satu_sehat_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique()->comment('ID dari SatuSehat');
            $table->string('identifier')->nullable()->index()->comment('Identifier internal');
            $table->string('name');
            $table->enum('type', ['ralan', 'ranap', 'apotek', 'lab', 'rad']);
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active');
            $table->string('managing_organization')->nullable()->comment('Organization IHS number');
            $table->json('raw_response')->nullable();
            $table->foreign('managing_organization')
                ->references('ihs_number')
                ->on('satu_sehat_organizations')
                ->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satu_sehat_locations');
    }
};
