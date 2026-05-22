<?php

namespace Database\Seeders;

use App\Models\Configuration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class ConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------------------ //
        //  Aplikasi
        // ------------------------------------------------------------------ //
        $this->plain('app.alias_name', 'TERAPI');
        $this->plain('app.version', 'alpha-1.0.0');

        // ------------------------------------------------------------------ //
        //  QR Code
        // ------------------------------------------------------------------ //
        $this->plain('qrcode.error_correction', 'M');
        $this->plain('qrcode.size', '300');
        $this->plain('qrcode.margin', '10');
        $this->plain('qrcode.foreground_color', '#000000');
        $this->plain('qrcode.background_color', '#FFFFFF');
        $this->plain('qrcode.logo_enabled', '0');
        $this->plain('qrcode.logo_path', null);
        $this->plain('qrcode.logo_size', '60');

        // ------------------------------------------------------------------ //
        //  WhatsApp Gateway
        // ------------------------------------------------------------------ //
        $this->plain('whatsapp.active_gateway', 'waha');
        $this->plain('whatsapp.api_url', 'http://localhost:3000');
        $this->plain('whatsapp.api_key', '');
        $this->plain('whatsapp.session', 'default');
        $this->plain('whatsapp.delay', '3');
        $this->plain('whatsapp.tries', '3');
        $this->plain('whatsapp.backoff', '10');
        $this->plain('whatsapp.webhook_url', '');

        $this->plain('gowa.api_url', 'http://172.1.0.4:9321/');
        $this->plain('gowa.username', '');
        $this->plain('gowa.password', '');
        $this->plain('gowa.device_id', '');
        $this->plain('gowa.delay', '3');
        $this->plain('gowa.tries', '3');
        $this->plain('gowa.backoff', '10');
        $this->plain('gowa.webhook_url', '');

        // ------------------------------------------------------------------ //
        //  Queue Worker
        // ------------------------------------------------------------------ //
        $this->plain('queue.timeout', '60');
        $this->plain('queue.memory', '128');
        $this->plain('queue.sleep', '3');
        $this->plain('queue.tries', '3');
        $this->plain('queue.backoff', '10');
        $this->plain('queue.max_jobs', '1000');
        $this->plain('queue.max_time', '3600');
        $this->plain('queue.queue_names', 'default');

        // ------------------------------------------------------------------ //
        //  Informasi Rumah Sakit
        // ------------------------------------------------------------------ //
        $this->plain('hospital.name', 'RS Tk. IV 01.07.03 Padangsidimpuan');
        $this->plain('hospital.alias', 'RST PSP');
        $this->plain('hospital.phone', '021-1234567');
        $this->plain('hospital.email', 'rumkittnipsp@gmail.com');
        $this->plain('hospital.website', 'www.rumkittnipsp.com');
        $this->plain('hospital.address', 'Jalan Sudirman No. 1');
        $this->plain('hospital.city', 'Kota Padangsidimpuan');
        $this->plain('hospital.province', 'Sumatera Utara');
        $this->plain('hospital.postal_code', '22713');
        $this->plain('hospital.country', 'Indonesia');

        $this->plain('hospital.propinsi_code', '12');
        $this->plain('hospital.kabupaten_code', '1277');
        $this->plain('hospital.kecamatan_code', '127701');
        $this->plain('hospital.kelurahan_code', '1277011012');

        // ------------------------------------------------------------------ //
        //  Satu Sehat FHIR
        // ------------------------------------------------------------------ //
        $this->plain('satusehat.auth_url', 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1');
        $this->plain('satusehat.base_url', 'https://api-satusehat-stg.dto.kemkes.go.id');
        $this->plain('satusehat.fhir_url', 'https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1');
        $this->plain('satusehat.consent_url', 'https://api-satusehat-stg.dto.kemkes.go.id/consent/v1');
        $this->plain('satusehat.organization_id', 'cce12de1-082e-4789-a0f0-ef3620f5a2c2');
        $this->plain('satusehat.kode_ppk_kemenkes', '1277015');
        $this->plain('satusehat.nama_ppk_kemenkes', 'RS Tk. IV 01.07.03 Padangsidimpuan');

        $this->secret('satusehat.client_id', 'FTLaPVzgBHZLpvTxk0C6AlxbI9KwGrgD6EpNCDw44TAyztWj');
        $this->secret('satusehat.client_secret', '6y7FQPhzaDlWQaVf1ihWYn0aWQ63dCHxKaGjlytfld4isY0IDzCOnh2koxy7pqgT');

        // ------------------------------------------------------------------ //
        //  BPJS Kesehatan — Kode PPK Global
        // ------------------------------------------------------------------ //
        $this->plain('bpjs.kode_ppk', '0220R002');
        $this->plain('bpjs.nama_ppk', 'RS Tk. IV 01.07.03 Padangsidimpuan');
        $this->plain('bpjs.kode_ppk_apotek', '22419');
        $this->plain('bpjs.nama_ppk_apotek', 'IF RS Tk. IV 01.07.03 Padangsidimpuan');
        $this->plain('bpjs.default_codes', 'BPJ,BPN');
        $this->plain('bpjs.antrol_ex_polyclinics', 'GIG,IGDK,ADM,OK,FAR');
        $this->plain('bpjs.antrol_ex_doctors', '');

        // ------------------------------------------------------------------ //
        //  BPJS — VClaim
        // ------------------------------------------------------------------ //
        $this->plain('bpjs.vclaim.base_url', 'https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-rest-dev');
        $this->plain('bpjs.vclaim.cons_id', '1165');
        $this->secret('bpjs.vclaim.user_key', 'd420eb9c61505f200e09aa9c4496f4d4');
        $this->secret('bpjs.vclaim.secret_key', '2aF4F7A805');

        // ------------------------------------------------------------------ //
        //  BPJS — Antrian Online
        // ------------------------------------------------------------------ //
        $this->plain('bpjs.antrian_online.base_url', 'https://apijkn-dev.bpjs-kesehatan.go.id/antreanrs_dev');
        $this->plain('bpjs.antrian_online.cons_id', '1165');
        $this->secret('bpjs.antrian_online.user_key', 'c80800e11b1531be3defa922a7aa5f38');
        $this->secret('bpjs.antrian_online.secret_key', '2aF4F7A805');

        // ------------------------------------------------------------------ //
        //  BPJS — Apotek Online
        // ------------------------------------------------------------------ //
        $this->plain('bpjs.apotek_online.base_url', 'https://apijkn-dev.bpjs-kesehatan.go.id/apotek-rest-dev');
        $this->plain('bpjs.apotek_online.cons_id', '22419');
        $this->secret('bpjs.apotek_online.user_key', '0229f224cc5b36c0e3df5035d971aa84');
        $this->secret('bpjs.apotek_online.secret_key', '9rGB0687DF');

        // ------------------------------------------------------------------ //
        //  BPJS — eRM
        // ------------------------------------------------------------------ //
        $this->plain('bpjs.erm.base_url', 'https://apijkn-dev.bpjs-kesehatan.go.id/erekammedis_dev');
        $this->plain('bpjs.erm.cons_id', '1165');
        $this->secret('bpjs.erm.user_key', 'd420eb9c61505f200e09aa9c4496f4d4');
        $this->secret('bpjs.erm.secret_key', '2aF4F7A805');

        // ------------------------------------------------------------------ //
        //  BPJS — ICare
        // ------------------------------------------------------------------ //
        $this->plain('bpjs.icare.base_url', 'https://apijkn-dev.bpjs-kesehatan.go.id/ihs_dev');
        $this->plain('bpjs.icare.cons_id', '1165');
        $this->secret('bpjs.icare.user_key', 'c80800e11b1531be3defa922a7aa5f38');
        $this->secret('bpjs.icare.secret_key', '2aF4F7A805');

        // ------------------------------------------------------------------ //
        //  BPJS — Aplicare
        // ------------------------------------------------------------------ //
        $this->plain('bpjs.aplicare.base_url', 'https://dvlp.bpjs-kesehatan.go.id:9080/aplicaresws');
        $this->plain('bpjs.aplicare.cons_id', '1165');
        $this->secret('bpjs.aplicare.user_key', 'c80800e11b1531be3defa922a7aa5f38');
        $this->secret('bpjs.aplicare.secret_key', '2aF4F7A805');

        // ------------------------------------------------------------------ //
        //  BPJS — Antrian RS (JKN Mobile)
        // ------------------------------------------------------------------ //
        $this->plain('bpjs.antrian_rs.base_url', 'https://simrs.rumkittnipsp.com:8888');
        $this->secret('bpjs.antrian_rs.username', 'bpjs');
        $this->secret('bpjs.antrian_rs.password', 'RumkitTniPsp2025');

        // ------------------------------------------------------------------ //
        //  TTE (Tanda Tangan Elektronik)
        // ------------------------------------------------------------------ //
        $this->plain('tte.base_url', 'http://10.0.8.200');
        $this->secret('tte.username', 'esign');
        $this->secret('tte.password', 'qwerty');

        // ------------------------------------------------------------------ //
        //  Snowstorm (SNOMED CT)
        // ------------------------------------------------------------------ //
        $this->plain('snowstorm.url', 'http://simrs.rumkittnipsp.com:9876');
        $this->plain('snowstorm.branch', 'MAIN');
        $this->plain('snowstorm.system_display', 'http://snomed.info/sct');

        // ------------------------------------------------------------------ //
        //  AI Provider
        // ------------------------------------------------------------------ //
        $this->plain('ai.provider', 'ollama');
        $this->plain('ai.ollama_url', 'http://localhost:11434');
        $this->plain('ai.ollama_model', 'llama3');
        $this->plain('ai.claude_model', 'claude-sonnet-4-6');
        $this->plain('ai.openai_model', 'gpt-4o');
        $this->plain('ai.claude_key', '');
        $this->plain('ai.openai_key', '');
    }

    // ------------------------------------------------------------------ //
    //  Helpers
    // ------------------------------------------------------------------ //

    /** Seed nilai plain — tidak terenkripsi. Hanya mengisi jika belum ada atau masih kosong. */
    private function plain(string $key, ?string $value): void
    {
        $existing = Configuration::where('key', $key)->first();

        if ($existing && $existing->value !== null && $existing->value !== '') {
            return;
        }

        Configuration::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'is_encrypted' => false]
        );
    }

    /** Seed nilai sensitif — disimpan terenkripsi. Hanya mengisi jika belum ada atau masih kosong. */
    private function secret(string $key, string $value): void
    {
        $existing = Configuration::where('key', $key)->first();

        if ($existing && $existing->value !== null && $existing->value !== '' && $existing->is_encrypted) {
            return;
        }

        Configuration::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value !== '' ? Crypt::encryptString($value) : null,
                'is_encrypted' => $value !== '',
            ]
        );
    }
}
