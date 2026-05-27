<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiHospitalController extends Controller
{
    /**
     * Kembalikan seluruh informasi identitas rumah sakit
     * dari konfigurasi sistem (config/hospital.php dan .env).
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'identity' => [
                    'name'        => config('hospital.name'),
                    'alias'       => config('hospital.alias'),
                    'phone'       => config('hospital.phone'),
                    'email'       => config('hospital.email'),
                    'website'     => config('hospital.website'),
                ],
                'location' => [
                    'address'     => config('hospital.address'),
                    'city'        => config('hospital.city'),
                    'province'    => config('hospital.province'),
                    'postal_code' => config('hospital.postal_code'),
                    'country'     => config('hospital.country'),
                ],
                'administrative_codes' => [
                    'province' => config('hospital.propinsi'),
                    'city'     => config('hospital.kabupaten'),
                    'district' => config('hospital.kecamatan'),
                    'village'  => config('hospital.kelurahan'),
                ],
            ],
        ]);
    }

    /**
     * Kembalikan informasi layanan/sistem integrasi yang sedang berjalan.
     */
    public function service(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'name'        => config('app.name'),
                'alias'       => config('app.alias_name'),
                'version'     => config('app.version'),
                'timezone'    => config('app.timezone'),
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
