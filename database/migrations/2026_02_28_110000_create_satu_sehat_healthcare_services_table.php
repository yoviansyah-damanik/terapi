<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mariadb';

    public function up(): void
    {
        Schema::connection('mariadb')->create('satu_sehat_healthcare_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ihs_number')->unique();
            $table->string('identifier')->unique()->index();
            $table->string('name');
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->json('raw_response')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mariadb')->dropIfExists('satu_sehat_healthcare_services');
    }
};
