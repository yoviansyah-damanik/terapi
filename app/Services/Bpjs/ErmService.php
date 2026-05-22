<?php

namespace App\Services\Bpjs;

use App\Helpers\ConfigurationHelper;
use App\Models\Bpjs\BpjsErm;
use App\Models\Bpjs\BpjsLog;
use Illuminate\Support\Facades\Http;

class ErmService extends BpjsBaseService
{
    protected string $module = 'erm';

    /**
     * Kompres dan enkripsi data bundle untuk dikirim ke BPJS eRM.
     * Alur: JSON → gzencode → base64 → AES-256-CBC (RAW) → base64
     */
    public function encrypt(string $data): string
    {
        $compressed = base64_encode(gzencode($data));

        $keyStr = (ConfigurationHelper::get('bpjs.erm.cons_id')    ?? config('bpjs.erm.cons_id'))
                . (ConfigurationHelper::get('bpjs.erm.secret_key') ?? config('bpjs.erm.secret_key'))
                . (ConfigurationHelper::get('bpjs.kode_ppk')       ?? config('bpjs.kode_ppk'));
        $keyHash = hash('sha256', $keyStr, true);
        $iv = substr($keyHash, 0, 16);

        return base64_encode(openssl_encrypt($compressed, 'AES-256-CBC', $keyHash, OPENSSL_RAW_DATA, $iv));
    }

    /**
     * Dekripsi dan dekompresi data eRM dari BPJS.
     * Alur kebalikan encrypt: base64 → AES-256-CBC decrypt → base64_decode → gzdecode
     */
    public function decrypt(string $ciphertext): ?string
    {
        $keyStr = (ConfigurationHelper::get('bpjs.erm.cons_id')    ?? config('bpjs.erm.cons_id'))
                . (ConfigurationHelper::get('bpjs.erm.secret_key') ?? config('bpjs.erm.secret_key'))
                . (ConfigurationHelper::get('bpjs.kode_ppk')       ?? config('bpjs.kode_ppk'));
        $keyHash = hash('sha256', $keyStr, true);
        $iv = substr($keyHash, 0, 16);

        $decrypted = openssl_decrypt(base64_decode($ciphertext), 'AES-256-CBC', $keyHash, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            return null;
        }

        $decompressed = gzdecode(base64_decode($decrypted));

        return $decompressed ?: null;
    }

    /**
     * Insert data rekam medis elektronik ke BPJS.
     * POST /eclaim/rekammedis/insert — body dikirim sebagai raw JSON dengan Content-Type: text/plain
     *
     * @return array{code: string, message: string, response: mixed, _meta: array}
     */
    public function insertErm(array $data): array
    {
        $url = $this->baseUrl() . '/eclaim/rekammedis/insert';
        $start = microtime(true);

        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->withBody(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS), 'text/plain')
            ->post($url);

        $parsed = $this->parseResponse($response);
        $parsed['_meta'] = [
            'endpoint' => $url,
            'method' => 'POST',
            'response_status' => $response->status(),
            'response_time' => round(microtime(true) - $start, 2),
        ];

        return $parsed;
    }

    /**
     * Kirim rekam medis elektronik ke BPJS.
     *
     * @param string $noRawat      Nomor Rawat
     * @param string $noSep        Nomor SEP
     * @param int    $jnsPelayanan 1=Rawat Inap, 2=Rawat Jalan/IGD
     * @param int    $bulan        Bulan pelayanan (1-12)
     * @param int    $tahun        Tahun pelayanan
     * @param string $roomCode     Kode poli atau bangsal
     * @param string $doctorCode   Kode dokter (kd_dokter)
     * @param array  $bundle       FHIR Bundle array
     */
    public function insertRekamMedis(string $noRawat, string $noSep, int $jnsPelayanan, int $bulan, int $tahun, string $roomCode, string $doctorCode, array $bundle): array
    {
        $rmeBundle = json_encode($bundle, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
        $data = [
            'request' => [
                'noSep' => (string) $noSep,
                'jnsPelayanan' => (string) $jnsPelayanan,
                'bulan' => (string) $bulan,
                'tahun' => (string) $tahun,
                'dataMR' => $this->encrypt($rmeBundle),
            ],
        ];

        $response = $this->insertErm($data);
        $success = isset($response['code']) && $response['code'] == 200;
        $message = $response['message'] ?? 'Unknown error';
        $meta = $response['_meta'] ?? [];
        unset($response['_meta']);

        // Ambil encounter_type dari FHIR bundle
        $encounterType = null;
        foreach ($bundle['entry'] ?? [] as $entry) {
            $r = $entry['resource'] ?? null;
            if (is_array($r) && ($r['resourceType'] ?? '') === 'Encounter') {
                $encounterType = $r['class']['code'] ?? null;
                break;
            }
        }
        $encounterType ??= ($jnsPelayanan === 1 ? 'IMP' : 'AMB');

        BpjsLog::record(
            service: 'erm',
            status: $success ? 'success' : 'failed',
            noRawat: $noRawat,
            noSep: $noSep,
            method: $meta['method'] ?? 'POST',
            endpoint: $meta['endpoint'] ?? null,
            responseStatus: $meta['response_status'] ?? null,
            responseTime: $meta['response_time'] ?? null,
            requestPayload: $data,
            responsePayload: $response,
            bundle: $rmeBundle,
            errorMessage: $success ? null : $message,
            success: $success,
        );

        if ($success) {
            BpjsErm::create([
                'no_rawat' => $noRawat,
                'no_sep' => $noSep,
                'bundle_id' => $bundle['id'] ?? '',
                'jenis_pelayanan' => $jnsPelayanan,
                'bulan' => $bulan,
                'tahun' => $tahun,
                'room_code' => $roomCode,
                'doctor_code' => $doctorCode,
                'encounter_type' => $encounterType,
                'bundle' => $bundle,
                'sent_at' => now(),
            ]);
        }

        return $response;
    }


}
