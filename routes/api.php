<?php

use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\ApiHospitalController;
use App\Http\Controllers\Api\ApiSimrsSlideController;
use App\Http\Controllers\Api\ApiSimrsVersionController;
use App\Http\Controllers\Api\ApiAiController;
use App\Http\Controllers\Api\DicomWorklistController;
use App\Http\Controllers\Api\GowaWebhookController;
use App\Http\Controllers\Api\OrthancWebhookController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\SatuSehatDicomWebhookController;
use App\Http\Controllers\Api\SimrsLogController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\TteController;
use App\Http\Controllers\Api\WahaWebhookController;
use App\Http\Controllers\Api\WhatsappApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function () {

        // ── Autentikasi ───────────────────────────────────────────────────────
        Route::prefix('auth')->group(function () {
            Route::post('/token', [ApiAuthController::class, 'createToken'])
                ->middleware(['throttle:api.auth', 'api.size:256']);
            Route::delete('/token', [ApiAuthController::class, 'revokeToken'])
                ->middleware(['api.token', 'throttle:api.general']);
        });

        // ── Informasi Rumah Sakit ─────────────────────────────────────────────
        Route::middleware(['api.token', 'api.scope:hospital', 'throttle:api.general'])
            ->prefix('hospital')->group(function () {
                Route::get('/', [ApiHospitalController::class, 'info']);
                Route::get('/service', [ApiHospitalController::class, 'service']);
            });

        // ── SIMRS ─────────────────────────────────────────────────────────────
        Route::middleware(['api.token', 'api.scope:simrs', 'throttle:api.general'])
            ->prefix('simrs')->group(function () {
                Route::get('/version', [ApiSimrsVersionController::class, 'version']);
                Route::get('/download/{version}', [ApiSimrsVersionController::class, 'download'])->middleware('throttle:5,1');
                Route::post('/update/report', [ApiSimrsVersionController::class, 'reportUpdate']);
                Route::get('/launcher/slides', [ApiSimrsSlideController::class, 'index']);
                Route::get('/launcher/slides/{id}/image', [ApiSimrsSlideController::class, 'image'])->name('simrs.slides.image');

                Route::middleware('api.size:2048')->group(function () {
                    Route::post('/log', [SimrsLogController::class, 'store']);
                    Route::post('/log/batch', [SimrsLogController::class, 'storeBatch']);
                    Route::get('/logs', [SimrsLogController::class, 'index']);
                    Route::get('/logs/{id}', [SimrsLogController::class, 'show']);
                });
            });

        // ── WhatsApp ──────────────────────────────────────────────────────────
        Route::middleware(['api.token', 'api.scope:whatsapp-gateway', 'throttle:api.general', 'api.size:5120'])
            ->prefix('whatsapp')->group(function () {
                Route::post('/send/text', [WhatsappApiController::class, 'sendText']);
                Route::post('/send/image', [WhatsappApiController::class, 'sendImage']);
                Route::post('/send/file', [WhatsappApiController::class, 'sendFile']);
                Route::post('/send/video', [WhatsappApiController::class, 'sendVideo']);
                Route::post('/send/audio', [WhatsappApiController::class, 'sendAudio']);
                Route::post('/send/location', [WhatsappApiController::class, 'sendLocation']);
                Route::post('/send/contact', [WhatsappApiController::class, 'sendContact']);
                Route::post('/send/link', [WhatsappApiController::class, 'sendLink']);
                Route::post('/send/poll', [WhatsappApiController::class, 'sendPoll']);
                Route::get('/status', [WhatsappApiController::class, 'getStatus']);
                Route::get('/user/check', [WhatsappApiController::class, 'checkUser']);
                Route::get('/message/{id}', [WhatsappApiController::class, 'getMessageStatus']);
                Route::post('/message/{id}/revoke', [WhatsappApiController::class, 'revokeMessage']);
                Route::post('/message/{id}/react', [WhatsappApiController::class, 'reactMessage']);
            });

        // ── TTE ───────────────────────────────────────────────────────────────
        Route::middleware(['api.token', 'api.scope:tte', 'throttle:api.general', 'api.size:20480'])
            ->prefix('tte')->group(function () {
                Route::get('/status', [TteController::class, 'connectionStatus']);
                Route::get('/hits', [TteController::class, 'hitStats']);
                Route::post('/sign/pdf', [TteController::class, 'signPdf']);
                Route::post('/sign/totp', [TteController::class, 'requestSignTotp']);
                Route::post('/verify/pdf', [TteController::class, 'verifyPdf']);
                Route::post('/user/status', [TteController::class, 'checkUserStatus']);
                Route::post('/user/register', [TteController::class, 'registerUser']);
                Route::post('/seal/activation', [TteController::class, 'sealGetActivation']);
                Route::post('/seal/refresh', [TteController::class, 'sealRefreshActivation']);
                Route::post('/seal/revoke', [TteController::class, 'sealRevokeActivation']);
                Route::post('/seal/totp', [TteController::class, 'sealGetTotp']);
                Route::post('/seal/pdf', [TteController::class, 'sealPdf']);
            });

        // ── QR Code ───────────────────────────────────────────────────────────
        Route::middleware(['api.token', 'api.scope:qrcode', 'throttle:api.general', 'api.size:5120'])
            ->prefix('qrcode')->group(function () {
                Route::post('/generate', [QrCodeController::class, 'generate']);
            });

        // ── AI Provider ───────────────────────────────────────────────────────
        Route::middleware(['api.token', 'api.scope:ai', 'throttle:api.general', 'api.size:2048'])
            ->prefix('ai')->group(function () {
                Route::post('/prompt', [ApiAiController::class, 'prompt']);
            });

        // ── DICOM Worklist ────────────────────────────────────────────────────
        Route::middleware(['api.token', 'api.scope:dicom', 'throttle:api.general'])
            ->prefix('worklists')->group(function () {
                Route::post('/batch', [DicomWorklistController::class, 'batch']);
                Route::post('/', [DicomWorklistController::class, 'store']);
                Route::get('/{noorder}', [DicomWorklistController::class, 'show']);
                Route::delete('/{noorder}', [DicomWorklistController::class, 'destroy']);
            });
    });

// ── Webhook (tanpa auth — dipanggil service eksternal) ────────────────
// Route::middleware('throttle:api.webhook')->prefix('webhooks')->group(function () {
Route::middleware('throttle:api.webhook')->prefix('webhooks')->group(function () {
    Route::match(['get', 'post'], '/whatsapp/waha', [WahaWebhookController::class, 'handle']);
    Route::match(['get', 'post'], '/whatsapp/gowa', [GowaWebhookController::class, 'handle']);
    Route::post('/satusehat/dicom', [SatuSehatDicomWebhookController::class, 'handle']);
    Route::post('/orthanc/worklist', [OrthancWebhookController::class, 'handle'])
        ->name('orthanc-sync');
});

// ── Status layanan eksternal ──────────────────────────────────────────
Route::prefix('status')->group(function () {
    Route::get('/reverb', [StatusController::class, 'reverb'])->name('status.reverb');
    Route::get('/redis', [StatusController::class, 'redis'])->name('status.redis');
});
