<?php


use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->group(function () {
        // Download dokumen dari resources/docs
        Route::get('/docs/download/{filename}', function (string $filename) {
            $path = resource_path("docs/{$filename}");
            abort_unless(file_exists($path), 404);
            return response()->download($path);
        })->where('filename', '.*')->name('docs.download');

        // Proxy gambar SIMRS (HTTP → HTTPS, menghindari Mixed Content)
        Route::get('/proxy/simrs-image', function (Illuminate\Http\Request $request) {
            $path = ltrim($request->query('path', ''), '/');
            abort_if(empty($path), 400);

            $url = rtrim(env('SIMRS_WEBAPPS_URL'), '/') . '/' . $path;

            $response = Illuminate\Support\Facades\Http::timeout(10)->get($url);

            abort_unless($response->successful(), 404);

            return response($response->body(), 200, [
                'Content-Type' => $response->header('Content-Type') ?? 'image/jpeg',
                'Cache-Control' => 'private, max-age=86400',
            ]);
        })->name('proxy.simrs-image');

        // Single Page
        Route::livewire('/', 'pages::dashboard')->name('home');
        Route::livewire('/guide', 'pages::guide')->name('guide');

        // Utility
        Route::prefix('utility')->name('utility.')->group(function () {
            Route::livewire('/connection-status', 'pages::utility.connection-status')->middleware('module:utility.connection_status')->name('connection-status');
            Route::livewire('/qrcode', 'pages::utility.qrcode-generator')->middleware('module:utility.qrcode')->name('qrcode-generator');
            Route::livewire('/simrs-version', 'pages::utility.simrs-version')->middleware('module:utility.simrs_version')->name('simrs-version');
            Route::livewire('/database-backup', 'pages::utility.database-backup')->middleware('module:utility.database_backup')->name('database-backup');
        });

        // API Portal
        Route::prefix('api-portal')->name('api-portal.')->group(function () {
            Route::livewire('/summary', 'pages::api-portal.summary')->middleware('module:api_portal.summary')->name('summary');
            Route::livewire('/management', 'pages::api-portal.management')->middleware('module:api_portal.management')->name('management');
            Route::livewire('/api-version', 'pages::api-portal.api-version')->middleware('module:api_portal.api_version')->name('api-version');
            Route::livewire('/logs', 'pages::api-portal.logs')->middleware('module:api_portal.logs')->name('logs');
            Route::livewire('/documentation', 'pages::api-portal.documentation')->middleware('module:api_portal.documentation')->name('documentation');
            Route::livewire('/integration', 'pages::api-portal.integration')->middleware('module:api_portal.integration')->name('integration');
            Route::livewire('/security', 'pages::api-portal.security')->middleware('module:api_portal.security')->name('security');
        });

        // Profil
        Route::livewire('/profile', 'pages::profile')->name('profile');

        // Configuration
        Route::prefix('configuration')->name('configuration.')->group(function () {
            Route::redirect('/general', '/configuration/hospital')->name('general');
            Route::livewire('/hospital', 'pages::configuration.hospital')->middleware('module:configuration.hospital')->name('hospital');
            Route::redirect('/satusehat', '/configuration/connectivity?tab=bridging')->name('satusehat');
            Route::redirect('/bpjs', '/configuration/connectivity?tab=bridging')->name('bpjs');
            Route::redirect('/rsonline', '/configuration/connectivity?tab=bridging')->name('rsonline');

            // Konektivitas Master Page
            Route::livewire('/connectivity', 'pages::configuration.connectivity')->middleware('module:configuration.connectivity')->name('connectivity');

            // Redirect rute lama ke tab Connectivity
            Route::redirect('/bridging', '/configuration/connectivity?tab=bridging')->name('bridging');
            Route::redirect('/snowstorm', '/configuration/connectivity?tab=snowstorm')->name('snowstorm');
            Route::redirect('/ai', '/configuration/connectivity?tab=ai')->name('ai');
            Route::redirect('/tte', '/configuration/connectivity?tab=tte')->name('tte');
            Route::redirect('/wa-gateway', '/configuration/connectivity?tab=wa')->name('wa-gateway');
            Route::redirect('/dicom', '/configuration/connectivity?tab=dicom')->name('dicom');

            Route::livewire('/users', 'pages::configuration.users')->middleware('module:configuration.users')->name('users');
            Route::livewire('/jobs', 'pages::configuration.jobs')->middleware('module:configuration.jobs')->name('jobs');
            Route::livewire('/security', 'pages::configuration.security')->middleware('module:configuration.security')->name('security');
            Route::livewire('/monitoring', 'pages::configuration.monitoring')->middleware('module:configuration')->name('monitoring');
            Route::redirect('/api-access', '/api-portal/management')->name('api-access');

            // QRCode tetap berdiri sendiri
            Route::livewire('/qrcode', 'pages::configuration.qrcode')->middleware('module:configuration.qrcode')->name('qrcode');
        });

        // Data SIMRS
        Route::prefix('simrs')->name('simrs.')->group(function () {
            Route::livewire('/patients', 'pages::simrs.patients')->name('patients');
            Route::livewire('/employees', 'pages::simrs.employees')->name('employees');
            Route::livewire('/room', 'pages::simrs.room')->middleware('module:simrs.room')->name('room');
            Route::livewire('/allergy', 'pages::simrs.allergy')->middleware('module:simrs.allergy')->name('allergy');
            Route::livewire('/polyclinic', 'pages::simrs.polyclinic')->middleware('module:simrs.polyclinic')->name('polyclinic');
            Route::livewire('/employee', 'pages::simrs.employee')->middleware('module:simrs.employee')->name('employee');
            Route::livewire('/procedure', 'pages::simrs.procedure')->middleware('module:simrs.procedure')->name('procedure');
            Route::livewire('/icd9', 'pages::simrs.icd9')->middleware('module:simrs.icd9')->name('icd9');
            Route::livewire('/icd10', 'pages::simrs.icd10')->middleware('module:simrs.icd10')->name('icd10');
            Route::livewire('/patient', 'pages::simrs.patient')->middleware('module:simrs.patient')->name('patient');
            Route::livewire('/department', 'pages::simrs.department')->middleware('module:simrs.department')->name('department');
        });

        // TTE
        Route::prefix('tte')->name('tte.')->group(function () {
            Route::redirect('/configuration', '/configuration/tte')->name('configuration');
            Route::livewire('/simulation', 'pages::tte.simulation')->middleware('module:tte.simulation')->name('simulation');
            Route::livewire('/verification', 'pages::tte.verification')->middleware('module:tte.verification')->name('verification');
            Route::livewire('/history', 'pages::tte.history')->middleware('module:tte.history')->name('history');
            Route::get('/download-signed/{id}', function (string $id) {
                $doc = \App\Models\TteDocument::findOrFail($id);
                $files = $doc->signed_files ?? [];
                abort_if(empty($files), 404);
                $path = $files[0];
                abort_unless(\Illuminate\Support\Facades\Storage::disk('tte_signed')->exists($path), 404);
                $binary = \Illuminate\Support\Facades\Storage::disk('tte_signed')->get($path);
                return response($binary, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="tte-signed.pdf"',
                ]);
            })->name('download-signed');

            Route::get('/signed-file/{id}/{index}', function (string $id, int $index, \Illuminate\Http\Request $request) {
                $doc = \App\Models\TteDocument::findOrFail($id);
                $files = $doc->signed_files ?? [];
                abort_unless(isset($files[$index]), 404);
                $path = $files[$index];
                abort_unless(\Illuminate\Support\Facades\Storage::disk('tte_signed')->exists($path), 404);
                $binary = \Illuminate\Support\Facades\Storage::disk('tte_signed')->get($path);
                $filename = 'tte-signed-' . ($index + 1) . '.pdf';
                $disposition = $request->boolean('dl') ? 'attachment' : 'inline';
                return response($binary, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
                ]);
            })->name('signed-file');
            Route::get('/simulation-pdf/{file?}', function (string $file = 'tte-simulation.pdf') {
                $allowed = ['tte-simulation.pdf', 'tte-simulation-2.pdf'];
                abort_unless(in_array($file, $allowed), 403);
                $path = resource_path('files/' . $file);
                abort_unless(file_exists($path), 404);
                return response()->file($path, ['Content-Type' => 'application/pdf']);
            })->name('simulation-pdf');
        });

        // DICOM / PACS
        Route::prefix('dicom')->name('dicom.')->group(function () {
            Route::livewire('/summary', 'pages::dicom.summary')->middleware('module:dicom.summary')->name('summary');
            Route::livewire('/worklist', 'pages::dicom.worklist')->middleware('module:dicom.worklist')->name('worklist');
            Route::livewire('/viewer', 'pages::dicom.viewer')->middleware('module:dicom')->name('viewer');
            Route::livewire('/modality', 'pages::dicom.modality')->middleware('module:dicom.modality')->name('modality');
            Route::redirect('/router', '/dicom/infrastructure?tab=router')->name('router');
            Route::livewire('/convert', 'pages::dicom.convert')->middleware('module:dicom.convert')->name('convert');

            // Proxy download file DICOM binary dari Orthanc (hindari CORS + auth)
            Route::get('/instance-file/{id}', function (string $id) {
                $binary = app(\App\Services\Dicom\OrthancService::class)->getInstanceFile($id);
                abort_if(empty($binary), 404);
                return response($binary, 200, [
                    'Content-Type' => 'application/dicom',
                    'Content-Disposition' => "attachment; filename=\"{$id}.dcm\"",
                ]);
            })->name('instance-file');
        });

        // Satu Sehat
        Route::prefix('satusehat')->name('satusehat.')->group(function () {
            Route::livewire('/summary', 'pages::satusehat.summary')->middleware('module:satusehat.summary')->name('summary');
            Route::livewire('/scheduler', 'pages::satusehat.scheduler')->middleware('module:satusehat.scheduler')->name('scheduler');
            Route::livewire('/rule-number', 'pages::satusehat.rule-number')->middleware('module:satusehat.rule_number')->name('rule-number');

            // FHIR Resources
            Route::prefix('fhir-resource')->name('fhir-resource.')->middleware('module:satusehat.fhir_resource')->group(function () {
                Route::livewire('/episode-of-care', 'pages::satusehat.fhir-resource.episode-of-care')->name('episode-of-care');
                Route::livewire('/healthcare-services', 'pages::satusehat.fhir-resource.healthcare-services')->name('healthcare-services');
                Route::livewire('/locations', 'pages::satusehat.fhir-resource.locations')->name('locations');
                Route::livewire('/medication', 'pages::satusehat.fhir-resource.medication')->name('medication');
                Route::livewire('/organizations', 'pages::satusehat.fhir-resource.organizations')->name('organizations');
                Route::livewire('/patients', 'pages::satusehat.fhir-resource.patients')->name('patients');
                Route::livewire('/practitioners', 'pages::satusehat.fhir-resource.practitioners')->name('practitioners');
            });

            // KYC — Verifikasi Identitas Pasien
            Route::prefix('kyc')->name('kyc.')->middleware('module:satusehat.kyc')->group(function () {
                Route::livewire('/', 'pages::satusehat.kyc.generate-url')->name('generate-url');
                Route::livewire('/logs', 'pages::satusehat.kyc.logs')->name('logs');
            });
        });

        // WhatsApp Gateway (Unified)
        Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
            Route::livewire('/messages', 'pages::whatsapp.messages')->middleware('module:whatsapp.messages')->name('messages');
            Route::livewire('/broadcast', 'pages::whatsapp.broadcast')->middleware('module:whatsapp.broadcast')->name('broadcast');
            Route::livewire('/contacts', 'pages::whatsapp.contacts')->middleware('module:whatsapp.contacts')->name('contacts');
            Route::redirect('/configuration', '/configuration/wa-gateway')->name('configuration');
        });

        // eRM (Rekam Medis Elektronik — BPJS + Satu Sehat terintegrasi)
        Route::prefix('erm')->name('erm.')->group(function () {
            Route::livewire('/rawat-jalan', 'pages::erm.rawat-jalan')->middleware('module:erm.rawat_jalan')->name('rawat-jalan');
            Route::livewire('/igd', 'pages::erm.igd')->middleware('module:erm.igd')->name('igd');
            Route::livewire('/rawat-inap', 'pages::erm.rawat-inap')->middleware('module:erm.rawat_inap')->name('rawat-inap');
            Route::livewire('/detail/{noRawat}', 'pages::erm.detail')->middleware('module:erm')->name('detail');
        });

        // BPJS Kesehatan
        Route::prefix('bpjs')->name('bpjs.')->group(function () {
            Route::livewire('/summary', 'pages::bpjs.summary')->middleware('module:bpjs.summary')->name('summary');
            Route::livewire('/antrean-online', 'pages::bpjs.antrean-online')->middleware('module:bpjs.antrean_online')->name('antrean-online');
            Route::livewire('/aplicare', 'pages::bpjs.aplicare')->middleware('module:bpjs.aplicare')->name('aplicare');
            Route::livewire('/vclaim', 'pages::bpjs.vclaim')->middleware('module:bpjs.vclaim')->name('vclaim');
            Route::livewire('/erm', 'pages::bpjs.erm')->middleware('module:bpjs.erm')->name('erm');
            Route::livewire('/erm/{id}', 'pages::bpjs.erm-detail')->middleware('module:bpjs.erm')->name('erm-detail');
            Route::get('/erm-scheduler', fn() => redirect()->route('bpjs.erm', ['tab' => 'scheduler']))->name('erm-scheduler');
            Route::prefix('fhir-resource')->name('fhir-resource.')->group(function () {
                Route::get('/patient', fn() => redirect()->route('local.patient'))->name('patient');
                Route::get('/practitioner', fn() => redirect()->route('local.practitioner'))->name('practitioner');
                Route::get('/organization', fn() => redirect()->route('local.organization'))->name('organization');
                Route::get('/healthcare-service', fn() => redirect()->route('local.healthcare-service.polyclinic'))->name('healthcare-service');
                Route::get('/procedure', fn() => redirect()->route('local.clinical.procedure'))->name('procedure');
                Route::livewire('/icd10', 'pages::local.clinical.icd10')->name('icd10');
                Route::get('/icd9', fn() => redirect()->route('local.clinical.icd9'))->name('icd9');
                Route::get('/medication', fn() => redirect()->route('local.medication.medicine'))->name('medication');
                Route::prefix('observation')->name('observation.')->group(function () {
                    Route::get('/lab', fn() => redirect()->route('local.observation.laboratory'))->name('lab');
                    Route::get('/radiology', fn() => redirect()->route('local.observation.radiology'))->name('radiology');
                });
                Route::prefix('medication-group')->name('medication.')->group(function () {
                    Route::get('/vaccine', fn() => redirect()->route('local.medication.vaccine'))->name('vaccine');
                });
                Route::prefix('device')->name('device.')->group(function () {
                    Route::get('/equipment', fn() => redirect()->route('local.device.equipment'))->name('equipment');
                });
                Route::prefix('allergy')->name('allergy.')->group(function () {
                    Route::get('/allergy', fn() => redirect()->route('local.allergy.allergy'))->name('allergy');
                    Route::get('/reaction', fn() => redirect()->route('local.allergy.reaction'))->name('reaction');
                });
            });
        });

        // Terminologi Sumber
        Route::prefix('terminology')->name('terminology.')->group(function () {
            Route::livewire('/smart-search', 'pages::terminology.smart-search')->middleware('module:terminology.smart_search')->name('smart-search');
            Route::livewire('/snomed', 'pages::terminology.snomed')->middleware('module:terminology.snomed')->name('snomed');
            Route::livewire('/loinc', 'pages::terminology.loinc')->middleware('module:terminology.loinc')->name('loinc');
            Route::get('/hl7-codesystem', fn() => redirect()->route('terminology.fhir-dictionary', ['filterSource' => 'hl7']))->name('hl7-codesystem');
            Route::get('/satu-sehat', fn() => redirect()->route('terminology.fhir-dictionary', ['filterSource' => 'kemkes']))->name('satu-sehat');
            Route::livewire('/kfa', 'pages::terminology.kfa')->middleware('module:terminology.kfa')->name('kfa');
            Route::livewire('/icd-o-morphology', 'pages::terminology.icd-o-morphology')->middleware('module:terminology.icd_o_morphology')->name('icd-o-morphology');
            Route::livewire('/icd-o-topography', 'pages::terminology.icd-o-topography')->middleware('module:terminology.icd_o_topography')->name('icd-o-topography');
            Route::livewire('/icd9cm', 'pages::terminology.icd9cm')->middleware('module:terminology.icd9cm')->name('icd9cm');
            Route::livewire('/icd10', 'pages::terminology.icd10')->middleware('module:terminology.icd10')->name('icd10');
            Route::livewire('/icd-mm', 'pages::terminology.icd-mm')->middleware('module:terminology.icd_mm')->name('icd-mm');
            Route::livewire('/icd-pm', 'pages::terminology.icd-pm')->middleware('module:terminology.icd_pm')->name('icd-pm');
            Route::livewire('/fhir-dictionary', 'pages::terminology.fhir-dictionary')->middleware('module:terminology.fhir_dictionary')->name('fhir-dictionary');
        });

        // Local Terminology — Observation & Medication
        Route::prefix('local')->name('local.')->group(function () {
            Route::livewire('/summary', 'pages::local.summary')->middleware('module:local')->name('summary');
            Route::livewire('/patient', 'pages::local.patient')->middleware('module:local.patient')->name('patient');
            Route::livewire('/organization', 'pages::local.organization')->middleware('module:local.organization')->name('organization');
            Route::prefix('source')->name('source.')->middleware('module:local.source')->group(function () {
                Route::livewire('/icd10', 'pages::local.clinical.icd10')->name('icd10');
                Route::livewire('/icd9', 'pages::local.clinical.icd9')->name('icd9');
                Route::livewire('/icd-o-topography', 'pages::local.clinical.icd-o-topography')->name('icd-o-topography');
                Route::livewire('/icd-o-morphology', 'pages::local.clinical.icd-o-morphology')->name('icd-o-morphology');
                Route::livewire('/icd-pm', 'pages::local.clinical.icd-pm')->name('icd-pm');
                Route::livewire('/icd-mm', 'pages::local.clinical.icd-mm')->name('icd-mm');
            });
            Route::prefix('clinical')->name('clinical.')->middleware('module:local.clinical')->group(function () {
                Route::livewire('/procedure', 'pages::local.clinical.procedure')->name('procedure');
                Route::livewire('/surgery', 'pages::local.clinical.surgery')->name('surgery');
                Route::livewire('/surgery-report', 'pages::local.clinical.surgery-report')->name('surgery-report');
            });
            Route::livewire('/practitioner', 'pages::local.practitioner')->middleware('module:local.practitioner')->name('practitioner');
            Route::prefix('observation')->name('observation.')->middleware('module:local.observation')->group(function () {
                Route::livewire('/lab', 'pages::local.observation.laboratory')->name('laboratory');
                Route::livewire('/radiology', 'pages::local.observation.radiology')->name('radiology');
            });
            Route::prefix('medication')->name('medication.')->middleware('module:local.medication')->group(function () {
                Route::livewire('/medicine', 'pages::local.medication.medicine')->name('medicine');
                Route::livewire('/vaccine', 'pages::local.medication.vaccine')->name('vaccine');
            });

            Route::prefix('device')->name('device.')->middleware('module:local.device')->group(function () {
                Route::livewire('/equipment', 'pages::local.device.equipment')->name('equipment');
            });

            Route::prefix('allergy')->name('allergy.')->middleware('module:local.allergy')->group(function () {
                Route::livewire('/allergy', 'pages::local.allergy.allergy')->name('allergy');
                Route::livewire('/reaction', 'pages::local.allergy.reaction')->name('reaction');
            });

            Route::prefix('healthcare-service')->name('healthcare-service.')->middleware('module:local.healthcare_service')->group(function () {
                Route::livewire('/polyclinic', 'pages::local.healthcare-service.polyclinic')->name('polyclinic');
                Route::livewire('/ward', 'pages::local.healthcare-service.ward')->name('ward');
            });

            Route::prefix('episode-of-care')->name('episode-of-care.')->middleware('module:local.episode_of_care')->group(function () {
                Route::livewire('/', 'pages::local.episode-of-care.index')->name('index');
            });

            Route::livewire('/general', 'pages::local.general')->name('general');
        });

        // Logs
        Route::prefix('logs')->name('logs.')->group(function () {
            Route::livewire('/satusehat', 'pages::logs.satusehat')->middleware('module:logs.satusehat')->name('satusehat');
            Route::livewire('/whatsapp', 'pages::logs.whatsapp')->middleware('module:logs.whatsapp')->name('whatsapp');
            Route::livewire('/bpjs', 'pages::logs.bpjs')->middleware('module:logs.bpjs')->name('bpjs');
            Route::livewire('/simrs', 'pages::logs.simrs')->middleware('module:logs.simrs')->name('simrs');
            Route::livewire('/tte', 'pages::logs.tte')->middleware('module:logs.tte')->name('tte');
            Route::livewire('/qrcode', 'pages::logs.qrcode')->middleware('module:logs.qrcode')->name('qrcode');
            Route::livewire('/ai', 'pages::logs.ai')->middleware('module:logs.ai')->name('ai');
            Route::livewire('/dicom', 'pages::logs.dicom')->middleware('module:logs.dicom')->name('dicom');
        });

        // SIRS Online
        Route::prefix('sirs')->name('sirs.')->group(function () {
            Route::livewire('/', 'pages::sirs.index')->middleware('module:sirs.dashboard')->name('index');
            Route::livewire('/rl31', 'pages::sirs.rl31')->middleware('module:sirs.rl3_bulanan')->name('rl31');
            Route::livewire('/rl32', 'pages::sirs.rl32')->middleware('module:sirs.rl3_bulanan')->name('rl32');
            Route::livewire('/rl33', 'pages::sirs.rl33')->middleware('module:sirs.rl3_bulanan')->name('rl33');
            Route::livewire('/rl34', 'pages::sirs.rl34')->middleware('module:sirs.rl3_bulanan')->name('rl34');
            Route::livewire('/rl35', 'pages::sirs.rl35')->middleware('module:sirs.rl3_bulanan')->name('rl35');
            Route::livewire('/rl36', 'pages::sirs.rl36')->middleware('module:sirs.rl3_bulanan')->name('rl36');
            Route::livewire('/rl37', 'pages::sirs.rl37')->middleware('module:sirs.rl3_bulanan')->name('rl37');
            Route::livewire('/rl38', 'pages::sirs.rl38')->middleware('module:sirs.rl3_bulanan')->name('rl38');
            Route::livewire('/rl39', 'pages::sirs.rl39')->middleware('module:sirs.rl3_bulanan')->name('rl39');
            Route::livewire('/rl310', 'pages::sirs.rl310')->middleware('module:sirs.rl3_bulanan')->name('rl310');
            Route::livewire('/rl311', 'pages::sirs.rl311')->middleware('module:sirs.rl3_tahunan')->name('rl311');
            Route::livewire('/rl312', 'pages::sirs.rl312')->middleware('module:sirs.rl3_bulanan')->name('rl312');
            Route::livewire('/rl313', 'pages::sirs.rl313')->middleware('module:sirs.rl3_tahunan')->name('rl313');
            Route::livewire('/rl314', 'pages::sirs.rl314')->middleware('module:sirs.rl3_bulanan')->name('rl314');
            Route::livewire('/rl315', 'pages::sirs.rl315')->middleware('module:sirs.rl3_tahunan')->name('rl315');
            Route::livewire('/rl316', 'pages::sirs.rl316')->middleware('module:sirs.rl3_tahunan')->name('rl316');
            Route::livewire('/rl317', 'pages::sirs.rl317')->middleware('module:sirs.rl3_tahunan')->name('rl317');
            Route::livewire('/rl318', 'pages::sirs.rl318')->middleware('module:sirs.rl3_tahunan')->name('rl318');
            Route::livewire('/rl319', 'pages::sirs.rl319')->middleware('module:sirs.rl3_bulanan')->name('rl319');
            Route::livewire('/rl41', 'pages::sirs.rl41')->middleware('module:sirs.rl4')->name('rl41');
            Route::livewire('/rl42', 'pages::sirs.rl42')->middleware('module:sirs.rl4')->name('rl42');
            Route::livewire('/rl43', 'pages::sirs.rl43')->middleware('module:sirs.rl4')->name('rl43');
            Route::livewire('/rl51', 'pages::sirs.rl51')->middleware('module:sirs.rl5')->name('rl51');
            Route::livewire('/rl52', 'pages::sirs.rl52')->middleware('module:sirs.rl5')->name('rl52');
            Route::livewire('/rl53', 'pages::sirs.rl53')->middleware('module:sirs.rl5')->name('rl53');
        });

        // RS Online
        Route::prefix('rsonline')->name('rsonline.')->group(function () {
            Route::redirect('/configuration', '/configuration/rsonline')->name('configuration');
            Route::livewire('/referensi', 'pages::rsonline.referensi')->middleware('module:rsonline.referensi')->name('referensi');
            Route::livewire('/pasien', 'pages::rsonline.pasien')->middleware('module:rsonline.pasien')->name('pasien');
            Route::livewire('/fasyankes', 'pages::rsonline.fasyankes')->middleware('module:rsonline.fasyankes')->name('fasyankes');
        });

        // Upload chunk — untuk file besar agar tidak kena 413
        Route::post('/upload/chunk', function (\Illuminate\Http\Request $request) {
            $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->input('upload_id', ''));
            $chunkIndex = (int) $request->input('chunk_index', 0);
            $totalChunks = (int) $request->input('total_chunks', 1);
            $ext = strtolower(pathinfo($request->input('filename', 'upload.csv'), PATHINFO_EXTENSION));

            abort_if(!in_array($ext, ['csv', 'txt', 'zip', 'jpg', 'jpeg', 'png', 'webp']), 422, 'Tipe file tidak didukung.');
            abort_if(strlen($uploadId) < 8, 422, 'Upload ID tidak valid.');
            abort_if(!$request->hasFile('chunk'), 422, 'Chunk tidak ditemukan.');

            $dir = storage_path("app/temp/chunks/{$uploadId}");
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $request->file('chunk')->move($dir, "chunk_{$chunkIndex}");

            if ($chunkIndex !== $totalChunks - 1) {
                return response()->json(['done' => false]);
            }

            // Rakit semua chunk menjadi satu file
            $finalPath = storage_path("app/temp/{$uploadId}.{$ext}");
            $out = fopen($finalPath, 'wb');
            for ($i = 0; $i < $totalChunks; $i++) {
                $part = "{$dir}/chunk_{$i}";
                $in = fopen($part, 'rb');
                while (!feof($in)) {
                    fwrite($out, fread($in, 1024 * 1024));
                }
                fclose($in);
                @unlink($part);
            }
            fclose($out);
            @rmdir($dir);

            return response()->json(['done' => true, 'path' => $finalPath]);
        })->name('upload.chunk');
    });

require __DIR__ . '/auth.php';
