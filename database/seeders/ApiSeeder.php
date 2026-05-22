<?php

namespace Database\Seeders;

use App\Models\Api\ApiUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ApiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ApiUser::create([
            'name' => 'SIMRS',
            'username' => 'simrs',
            'password' => bcrypt('simrs2026'),
            'scopes' => '["tte","log-simrs","whatsapp-gateway"]',
            'is_active' => true,
        ]);
    }
}
