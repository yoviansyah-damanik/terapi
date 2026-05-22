<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('api_tte_logs');
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tte_logs');
    }
};
