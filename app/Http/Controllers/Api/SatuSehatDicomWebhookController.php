<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dicom\DicomRouterResponse;
use App\Models\Dicom\Worklist;
use App\Models\SatuSehat\SatuSehatBundle;
use App\Models\SatuSehat\SatuSehatBundleItem;
use App\Models\SatuSehat\SatuSehatBundleLog;
use App\Models\SatuSehat\SatuSehatImagingStudy;
use App\Models\SatuSehat\SatuSehatEncounter;
use App\Models\Simrs\RegPeriksa;
use App\Services\SatuSehat\ErmFhirService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SatuSehatDicomWebhookController extends Controller
{
    /**
     * Menerima payload webhook dari DICOM Router Kemenkes / Satu Sehat.
     *
     * Contoh payload:
     * {
     *   "status": true,
     *   "message": "DICOM berhasil dikirim",
     *   "data": {
     *     "imagingStudyId": "02d36312-8a0f-45ef-bd88-eb3c954a6a3f",
     *     "accessionNumber": "PR202602220002",
     *     "studyInstanceUID": "2.25.697744798.2508253374"
     *   },
     *   "error": [],
     *   "stage": "dicom_sent"
     * }
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        Log::channel('satusehat')->info('Webhook DICOM Router Received:', [
            'ip' => $request->ip(),
            'payload' => $payload,
        ]);

        try {
            $data = $payload['data'] ?? [];
            $accessionNumber = $data['accessionNumber'] ?? $payload['accession_number'] ?? null;
            $studyUid = $data['studyInstanceUID'] ?? $payload['study_instance_uid'] ?? null;
            $ihsId = $data['imagingStudyId'] ?? $payload['resource_id'] ?? null;
            $status = $payload['status'] ?? false;
            $stage = $payload['stage'] ?? null;
            $message = $payload['message'] ?? null;
            $errors = $payload['error'] ?? $payload['errors'] ?? [];

            // Cari Worklist terkait
            $dicomStudy = $accessionNumber
                ? Worklist::firstWhere('accession_number', $accessionNumber)
                : ($studyUid ? Worklist::firstWhere('study_instance_uid', $studyUid) : null);

            // Simpan response ke tabel log
            DicomRouterResponse::create([
                'dicom_study_id' => $dicomStudy?->id,
                'accession_number' => $accessionNumber,
                'imaging_study_ihs' => $ihsId,
                'study_instance_uid' => $studyUid,
                'stage' => $stage,
                'status' => (bool) $status,
                'message' => $message,
                'errors' => empty($errors) ? null : $errors,
                'raw_payload' => $payload,
                'ip_address' => $request->ip(),
            ]);

            // Update status Worklist
            if ($dicomStudy) {
                $dicomStudy->status = $status ? 'sent' : 'error';
                if ($studyUid && !$dicomStudy->study_instance_uid) {
                    $dicomStudy->study_instance_uid = $studyUid;
                }

                if ($ihsId && $status) {
                    $dicomStudy->imaging_study_ihs = $ihsId;

                    // Update cache SatuSehatImagingStudy dengan data lengkap
                    $reg = RegPeriksa::where('no_rawat', $dicomStudy->no_rawat)->first();
                    $encounter = SatuSehatEncounter::where('local_id', $dicomStudy->no_rawat)->first();

                    if ($reg && $encounter) {
                        // Konstruksi local_id yang sama dengan ErmFhirService
                        $isRadiologi = str_starts_with($dicomStudy->noorder, 'PR');
                        $prefix = $isRadiologi ? 'IMG_RAD_' : 'IMG_USG_';

                        $tglIS = $dicomStudy->created_at;
                        $jamIS = $dicomStudy->created_at?->format('H:i:s');

                        if ($isRadiologi) {
                            $rad = \App\Models\Simrs\PermintaanRadiologi::where('noorder', $dicomStudy->noorder)->first();
                            if ($rad) {
                                $periksaRad = \App\Models\Simrs\PeriksaRadiologi::where('no_rawat', $dicomStudy->no_rawat)
                                    ->where('tgl_periksa', $rad->tgl_hasil)
                                    ->where('jam', $rad->jam_hasil)
                                    ->first();

                                $tglIS = $periksaRad?->tgl_periksa ?? $rad->tgl_permintaan;
                                $jamIS = $periksaRad?->jam ?? $rad->jam_permintaan;
                            }
                        } else {
                            $usg = \App\Models\Simrs\Usg\PermintaanUsg::where('noorder', $dicomStudy->noorder)->first();
                            if ($usg) {
                                $tglIS = $usg->waktu_hasil ?? $usg->waktu_permintaan;
                                $jamIS = $usg->waktu_hasil ? $usg->waktu_hasil->format('H:i:s') : $usg->waktu_permintaan->format('H:i:s');
                            }
                        }

                        $tglFormat = $tglIS instanceof \DateTime ? $tglIS->format('Ymd') : date('Ymd', strtotime($tglIS));
                        $idStr = $dicomStudy->no_rawat . '-' . $prefix . $dicomStudy->noorder . '-' . $tglFormat . '-' . str_replace(':', '', $jamIS ?? '000000');

                        $imagingStudyRecord = SatuSehatImagingStudy::updateOrCreate(
                            ['ihs_number' => $ihsId],
                            [
                                'local_id' => $idStr,
                                'patient_ihs' => $encounter->patient_ihs,
                                'encounter_ihs' => $encounter->ihs_number,
                                'status' => 'available',
                                'modality_code' => $dicomStudy->modality,
                                'description' => $dicomStudy->procedure_desc,
                                'started_at' => $dicomStudy->scheduled_date ?? $tglIS,
                                'synced_at' => now(),
                            ]
                        );

                        $imagingStudyResult = [
                            'status' => 'success',
                            'ihs_number' => $ihsId,
                            'local_id' => $imagingStudyRecord->getAttribute('local_id'),
                        ];

                        // Catat ke BundleItem & BundleLog bundle terbaru untuk no_rawat ini
                        $bundle = SatuSehatBundle::where('no_rawat', $dicomStudy->no_rawat)->latest()->first();
                        if ($bundle) {
                            SatuSehatBundleItem::updateOrCreate(
                                ['bundle_log_id' => $bundle->id, 'resource_type' => 'ImagingStudy', 'local_id' => $idStr],
                                ['ihs_id' => $ihsId, 'status' => 'success', 'response' => $data, 'error_message' => null]
                            );
                            SatuSehatBundleLog::create([
                                'bundle_id'     => $bundle->id,
                                'resource_type' => 'ImagingStudy',
                                'local_id'      => $idStr,
                                'ihs_id'        => $ihsId,
                                'status'        => 'success',
                                'response'      => $data,
                            ]);
                        }
                    }

                    $triggerResult = $this->triggerRadiologyResources($dicomStudy, $bundle ?? null);
                }

                $dicomStudy->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook received and processed successfully',
                'items' => [
                    'imaging_study' => $imagingStudyResult ?? null,
                    ...($triggerResult['items'] ?? []),
                ],
            ]);
        } catch (\Exception $e) {
            Log::channel('satusehat')->error('Error processing DICOM Webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error processing webhook',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Memicu pengiriman resource lanjutan (DiagnosticReport & Observation)
     * setelah ImagingStudy IHS ID diterima dari webhook DICOM Router.
     * Menangani RAD dan USG.
     */
    private function triggerRadiologyResources(Worklist $dicomStudy, ?SatuSehatBundle $bundle = null): array
    {
        try {
            $reg = RegPeriksa::find($dicomStudy->no_rawat);
            $encounter = SatuSehatEncounter::where('local_id', $dicomStudy->no_rawat)->first();

            if (!$reg || !$encounter) {
                return ['status' => 'skipped', 'message' => 'Data pendaftaran atau encounter tidak ditemukan.', 'payload' => $dicomStudy];
            }

            $fhirService = app(ErmFhirService::class);
            $noOrder = $dicomStudy->noorder;
            $isUsg = str_starts_with($noOrder, 'US');
            $responseItems = [];

            if (!$isUsg) {
                // --- RAD ---
                $periksaRad = \App\Models\Simrs\PeriksaRadiologi::where('no_rawat', $dicomStudy->no_rawat)
                    ->where('noorder', $noOrder)
                    ->first();

                if (!$periksaRad) {
                    return ['status' => 'skipped', 'message' => "PeriksaRadiologi untuk order {$noOrder} tidak ditemukan.", 'items' => []];
                }

                $tgl = $periksaRad->tgl_periksa ? \Carbon\Carbon::parse($periksaRad->tgl_periksa)->format('Ymd') : '';
                $jam = str_replace(':', '', $periksaRad->jam ?? '000000');
                $drIdStr = "{$dicomStudy->no_rawat}-DR_RAD_{$noOrder}-{$tgl}-{$jam}";

                Log::channel('satusehat')->info("Webhook Trigger RAD: Obs+DR untuk Order {$noOrder}");

                $obsIdStr = "{$dicomStudy->no_rawat}-OBS_RAD_{$noOrder}-{$tgl}-{$jam}";
                $obsRes = $fhirService->sendRadObservations($reg, $encounter, [$obsIdStr], $bundle);
                $drRes = $fhirService->sendRadDiagnosticReports($reg, $encounter, [$drIdStr], $bundle);

                $responseItems['observation'] = ['status' => $obsRes['success'] ? 'success' : 'failed', 'count' => $obsRes['count'] ?? 0, 'message' => $obsRes['message'] ?? ''];
                $responseItems['diagnostic_report'] = ['status' => $drRes['success'] ? 'success' : 'failed', 'count' => $drRes['count'] ?? 0, 'message' => $drRes['message'] ?? ''];
            } else {
                // --- USG ---
                $usgConfigs = \App\Services\UsgService::getUsgTypeConfigs();
                $drIdStr = null;
                $obsIdStr = null;
                $usgKey = null;

                foreach ($usgConfigs as $key => $cfg) {
                    $row = $cfg['model']::where('no_rawat', $dicomStudy->no_rawat)
                        ->where('noorder', $noOrder)
                        ->first();

                    if ($row) {
                        $tglUSG = \Carbon\Carbon::parse($row->tanggal);
                        $jamUSG = $row->jam ?? $tglUSG->format('H:i:s');
                        $suffix = '_' . $noOrder . '-' . $tglUSG->format('Ymd') . '-' . str_replace(':', '', $jamUSG);
                        $drIdStr = "{$dicomStudy->no_rawat}-DR_USG{$suffix}";
                        $obsIdStr = "{$dicomStudy->no_rawat}-OBS_USG{$suffix}";
                        $usgKey = $key;
                        break;
                    }
                }

                if (!$drIdStr) {
                    return ['status' => 'skipped', 'message' => "Data USG untuk order {$noOrder} tidak ditemukan di tabel hasil.", 'items' => []];
                }

                Log::channel('satusehat')->info("Webhook Trigger USG ({$usgKey}): Obs+DR untuk Order {$noOrder}");

                $obsRes = $fhirService->sendUsgObservations($reg, $encounter, [$obsIdStr], $bundle);
                $drRes = $fhirService->sendUsgDiagnosticReports($reg, $encounter, [$drIdStr], $bundle);

                $responseItems['observation'] = ['status' => $obsRes['success'] ? 'success' : 'failed', 'count' => $obsRes['count'] ?? 0, 'message' => $obsRes['message'] ?? ''];
                $responseItems['diagnostic_report'] = ['status' => $drRes['success'] ? 'success' : 'failed', 'count' => $drRes['count'] ?? 0, 'message' => $drRes['message'] ?? ''];
            }

            $overallSuccess = ($responseItems['observation']['status'] ?? '') === 'success'
                && ($responseItems['diagnostic_report']['status'] ?? '') === 'success';

            return [
                'status' => $overallSuccess ? 'triggered' : 'partial',
                'items' => $responseItems,
            ];
        } catch (\Exception $e) {
            Log::channel('satusehat')->error('Gagal kirim resource lanjutan via Webhook:', [
                'no_rawat' => $dicomStudy->no_rawat,
                'noorder' => $dicomStudy->noorder,
                'error' => $e->getMessage(),
            ]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
