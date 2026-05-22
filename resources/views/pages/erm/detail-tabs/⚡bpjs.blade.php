<?php

use App\Models\Bpjs\BpjsLog;
use App\Models\Simrs\RegPeriksa;
use App\Services\Bpjs\ErmValidator;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public RegPeriksa $reg;
    public int $totalTindakanERM = 0;
    public int $totalObatERM = 0;
    public int $totalLabERM = 0;
    public int $totalRadERM = 0;
    public int $totalVsERM = 0;
    public int $totalDiagnosaERM = 0;
    public int $totalProsedurERM = 0;

    public ?array $ermJson = null;
    public bool $showJsonModal = false;

    public function generateJson(): void
    {
        try {
            $this->reg = RegPeriksa::with(['pasien', 'dokter', 'poliklinik', 'penjab', 'bridgingSep', 'diagnosaPasien.penyakit', 'prosedurPasien.icd9'])->find($this->reg->no_rawat);
            if (!$this->reg) {
                $this->toastError('Data registrasi tidak ditemukan.');
                return;
            }
            if (!$this->reg->bridgingSep) {
                $this->toastError('Data SEP tidak ditemukan. eRM hanya dapat dikirim untuk pasien dengan SEP.');
                return;
            }
            $this->ermJson = new \App\Services\Bpjs\Erm\ErmBundleBuilder()->build($this->reg);
            $this->showJsonModal = true;
            $this->toastSuccess('JSON berhasil di-generate.');
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function sendErm(): void
    {
        try {
            $this->reg = RegPeriksa::with(['pasien', 'dokter', 'poliklinik', 'penjab', 'bridgingSep', 'diagnosaPasien.penyakit', 'prosedurPasien.icd9', 'kamarInap'])->find($this->reg->no_rawat);
            if (!$this->reg) {
                $this->toastError('Data registrasi tidak ditemukan.');
                return;
            }
            if (!$this->reg->bridgingSep) {
                $this->toastError('Data SEP tidak ditemukan. eRM hanya dapat dikirim untuk pasien dengan SEP.');
                return;
            }
            $bundle = new \App\Services\Bpjs\Erm\ErmBundleBuilder()->build($this->reg);
            $noSep = $this->reg->bridgingSep->no_sep;
            $isRanap = $this->reg->status_lanjut === 'Ranap';
            $jnsPelayanan = $isRanap ? 1 : 2;
            $tglSep = $this->reg->bridgingSep->tglsep ?? now();
            $roomCode = $isRanap ? $this->reg->kamarInap->last()?->kd_kamar ?? $this->reg->kd_poli : $this->reg->kd_poli;
            $response = new \App\Services\Bpjs\ErmService()->insertRekamMedis($this->reg->no_rawat, $noSep, $jnsPelayanan, (int) $tglSep->format('m'), (int) $tglSep->format('Y'), $roomCode, $this->reg->kd_dokter, $bundle);
            $success = isset($response['code']) && $response['code'] == 200;
            $success ? $this->toastSuccess('eRM berhasil dikirim ke BPJS.') : $this->toastError('Gagal mengirim eRM: ' . ($response['message'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            $this->toastError('Error: ' . $e->getMessage());
        }
    }

    public function downloadJson(): mixed
    {
        if (!$this->ermJson) {
            $this->toastError('Generate JSON terlebih dahulu.');
            return null;
        }
        $filename = 'erm_' . $this->reg->no_rawat . '_' . now()->format('YmdHis') . '.json';
        $json = json_encode($this->ermJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return response()->streamDownload(fn() => print $json, $filename, ['Content-Type' => 'text/plain']);
    }

    public function getCodings(): array
    {
        $codings = [
            'Composition' => [],
            'Patient' => [],
            'Practitioner' => [],
            'Organization — RS' => [],
            'Organization — Unit' => [],
            'Organization — Farmasi' => [],
            'Organization — Lab' => [],
            'Organization — Radiologi' => [],
            'Encounter' => [],
            'Condition' => [],
            'Procedure' => [],
            'MedicationRequest' => [],
            'DiagnosticReport Lab' => [],
            'DiagnosticReport Radiologi' => [],
            'DiagnosticReport Vital Sign' => [],
        ];

        try {
            $this->reg->loadMissing(['diagnosaPasien.penyakit', 'prosedurPasien.icd9', 'pasien', 'dokter', 'poliklinik']);

            // Composition
            $codings['Composition'][] = ['system' => 'http://loinc.org', 'code' => '28659-1', 'display' => 'Discharge summary', 'type' => 'LOINC'];

            // Patient
            $codings['Patient'][] = ['system' => 'https://fhir.kemkes.go.id/id/nik', 'code' => $this->reg->pasien?->no_ktp ?? '-', 'display' => $this->reg->pasien?->nm_pasien ?? '-', 'type' => 'NIK'];
            $bpjsPatient = \App\Models\Bpjs\BpjsPatient::where('nik', $this->reg->pasien?->no_ktp)->first();
            if ($bpjsPatient) {
                $codings['Patient'][] = ['system' => 'BPJS Master', 'code' => $bpjsPatient->id, 'display' => 'UUID', 'type' => 'Mapped (IHS)'];
            } else {
                $codings['Patient'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (IHS)'];
            }

            // Practitioner
            $nik = \App\Models\Simrs\Pegawai::where('nik', $this->reg->dokter?->kd_dokter)->value('no_ktp') ?? '-';
            $codings['Practitioner'][] = ['system' => 'https://fhir.kemkes.go.id/id/nik', 'code' => $nik, 'display' => $this->reg->dokter?->nm_dokter ?? '-', 'type' => 'NIK'];
            $bpjsPractitioner = \App\Models\Bpjs\BpjsPractitioner::where('identifier', $this->reg->kd_dokter)->first();
            if ($bpjsPractitioner) {
                $codings['Practitioner'][] = ['system' => 'BPJS Master', 'code' => $bpjsPractitioner->id, 'display' => 'UUID', 'type' => 'Mapped (IHS)'];
            } else {
                $codings['Practitioner'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (IHS)'];
            }

            // Encounter
            $isIgd = \Illuminate\Support\Str::contains(strtolower($this->reg->poliklinik?->nm_poli ?? ''), 'gawat') || \Illuminate\Support\Str::contains(strtolower($this->reg->poliklinik?->nm_poli ?? ''), 'darurat');
            $encClass = $this->reg->status_lanjut === 'Ranap' ? 'IMP' : ($isIgd ? 'EMER' : 'AMB');
            $encDisplay = $this->reg->status_lanjut === 'Ranap' ? 'inpatient encounter' : ($isIgd ? 'emergency' : 'ambulatory');
            $codings['Encounter'][] = ['system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode', 'code' => $encClass, 'display' => $encDisplay, 'type' => 'ActCode'];

            // Organization — RS
            $codings['Organization — RS'][] = ['system' => 'http://terminology.hl7.org/CodeSystem/organization-type', 'code' => 'prov', 'display' => 'Healthcare Provider', 'type' => 'Org Type'];
            $rsOrg = \App\Models\Bpjs\BpjsOrganization::where('identifier', 'RS')->first();
            if ($rsOrg) {
                $codings['Organization — RS'][] = ['system' => 'BPJS Master', 'code' => $rsOrg->id, 'display' => 'UUID Organisasi', 'type' => 'Mapped (UUID)'];
            } else {
                $codings['Organization — RS'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (UUID)'];
            }

            // Organization — Unit
            $roomCode = $this->reg->status_lanjut === 'Ranap' ? 'RI' : ($isIgd ? 'IGD' : 'RJ');
            $orgMap = \App\Models\Mapping\OrganizationMap::where('dep_id', $roomCode)->first();
            if ($orgMap) {
                $codings['Organization — Unit'][] = ['system' => $orgMap->org_type_display, 'code' => $orgMap->org_type_code, 'display' => $orgMap->org_type_term, 'type' => 'Mapped Unit'];
            }

            $unitOrg = \App\Models\Bpjs\BpjsOrganization::where('identifier', $roomCode)->first();
            if ($unitOrg) {
                $codings['Organization — Unit'][] = ['system' => 'BPJS Master', 'code' => $unitOrg->id, 'display' => 'UUID Organisasi', 'type' => 'Mapped (UUID)'];
            } else {
                $codings['Organization — Unit'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (UUID)'];
            }

            // Organization — Farmasi, Lab, Radiologi
            $codings['Organization — Farmasi'][] = ['system' => 'http://terminology.hl7.org/CodeSystem/organization-type', 'code' => 'dept', 'display' => 'Hospital Department', 'type' => 'Org Type'];
            $farOrg = \App\Models\Bpjs\BpjsOrganization::where('identifier', 'FAR')->first();
            if ($farOrg) {
                $codings['Organization — Farmasi'][] = ['system' => 'BPJS Master', 'code' => $farOrg->id, 'display' => 'UUID Organisasi', 'type' => 'Mapped (UUID)'];
            } else {
                $codings['Organization — Farmasi'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (UUID)'];
            }

            $codings['Organization — Lab'][] = ['system' => 'http://terminology.hl7.org/CodeSystem/organization-type', 'code' => 'dept', 'display' => 'Hospital Department', 'type' => 'Org Type'];
            $labOrg = \App\Models\Bpjs\BpjsOrganization::where('identifier', 'LAB')->first();
            if ($labOrg) {
                $codings['Organization — Lab'][] = ['system' => 'BPJS Master', 'code' => $labOrg->id, 'display' => 'UUID Organisasi', 'type' => 'Mapped (UUID)'];
            } else {
                $codings['Organization — Lab'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (UUID)'];
            }

            $codings['Organization — Radiologi'][] = ['system' => 'http://terminology.hl7.org/CodeSystem/organization-type', 'code' => 'dept', 'display' => 'Hospital Department', 'type' => 'Org Type'];
            $radOrg = \App\Models\Bpjs\BpjsOrganization::where('identifier', 'RAD')->first();
            if ($radOrg) {
                $codings['Organization — Radiologi'][] = ['system' => 'BPJS Master', 'code' => $radOrg->id, 'display' => 'UUID Organisasi', 'type' => 'Mapped (UUID)'];
            } else {
                $codings['Organization — Radiologi'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (UUID)'];
            }

            // Condition
            foreach ($this->reg->diagnosaPasien ?? [] as $diag) {
                $icdMap = \App\Models\Mapping\Icd10Map::where('icd10_code', $diag->kd_penyakit)->first();
                if ($icdMap) {
                    $codings['Condition'][] = ['system' => $icdMap->system_display, 'code' => $icdMap->system_code, 'display' => $icdMap->system_term, 'type' => 'SNOMED (Mapped)'];
                }
                $codings['Condition'][] = ['system' => 'http://hl7.org/fhir/sid/icd-10', 'code' => $diag->kd_penyakit, 'display' => $diag->penyakit?->nm_penyakit ?? '-', 'type' => 'ICD-10'];
            }

            // Procedure
            foreach ($this->reg->prosedurPasien ?? [] as $proc) {
                $map = \App\Models\Mapping\Icd9Map::where('icd9_code', $proc->kode)->first();
                if ($map) {
                    $codings['Procedure'][] = ['system' => $map->system_display, 'code' => $map->system_code, 'display' => $map->system_term, 'type' => 'ICD-9 (Mapped)'];
                } else {
                    $codings['Procedure'][] = ['system' => 'Unmapped', 'code' => $proc->kode, 'display' => $proc->icd9?->deskripsi_panjang ?? '-', 'type' => 'ICD-9 (Missing Map)'];
                }

                $bpjsIcd9 = \App\Models\Bpjs\BpjsIcd9::where('code', $proc->kode)->first();
                if ($bpjsIcd9) {
                    $codings['Procedure'][] = ['system' => 'BPJS Master', 'code' => $bpjsIcd9->id, 'display' => 'UUID', 'type' => 'Mapped (UUID)'];
                } else {
                    $codings['Procedure'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (UUID)'];
                }
            }

            $tindakanCollections = $this->reg->status_lanjut === 'Ranap' ? [$this->reg->rawatInapDr ?? [], $this->reg->rawatInapPr ?? [], $this->reg->rawatInapDrPr ?? []] : [$this->reg->rawatJlDr ?? [], $this->reg->rawatJlPr ?? [], $this->reg->rawatJlDrPr ?? []];
            foreach ($tindakanCollections as $collection) {
                foreach ($collection as $tindakan) {
                    $sourceTable = str_contains($tindakan->getTable() ?? '', 'jl_') ? 'jalan' : 'inap';
                    $map = \App\Models\Mapping\ProcedureMap::where('procedure_code', $tindakan->kd_jenis_prw)->where('source_table', $sourceTable)->first();
                    if ($map) {
                        $codings['Procedure'][] = ['system' => $map->system_display, 'code' => $map->system_code, 'display' => $map->system_term, 'type' => 'Tindakan (Mapped)'];
                    } else {
                        $codings['Procedure'][] = ['system' => 'Unmapped', 'code' => $tindakan->kd_jenis_prw, 'display' => $tindakan->perawatan?->nm_perawatan ?? '-', 'type' => 'Tindakan (Missing Map)'];
                    }

                    $bpjsProc = \App\Models\Bpjs\BpjsProcedure::where('type', strtolower($this->reg->status_lanjut))
                        ->where('local_code', $tindakan->kd_jenis_prw)
                        ->first();
                    if ($bpjsProc) {
                        $codings['Procedure'][] = ['system' => 'BPJS Master', 'code' => $bpjsProc->id, 'display' => 'UUID', 'type' => 'Mapped (UUID)'];
                    } else {
                        $codings['Procedure'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (UUID)'];
                    }
                }
            }

            // MedicationRequest
            $obats = \App\Models\Simrs\DetailPemberianObat::where('no_rawat', $this->reg->no_rawat)->with('dataBarang.kfa')->get();
            foreach ($obats as $obat) {
                $kfa = $obat->dataBarang?->kfa;
                if ($kfa) {
                    $codings['MedicationRequest'][] = ['system' => 'http://sys-ids.kemkes.go.id/kfa', 'code' => $kfa->kfa_code, 'display' => $kfa->kfa_display, 'type' => 'KFA'];
                } else {
                    $codings['MedicationRequest'][] = ['system' => 'Unmapped', 'code' => $obat->kode_brng, 'display' => $obat->dataBarang?->nama_brng ?? '-', 'type' => 'Obat (Missing KFA)'];
                }

                $bpjsMed = \App\Models\Bpjs\BpjsMedication::where('local_code', $obat->kode_brng)->first();
                if ($bpjsMed) {
                    $codings['MedicationRequest'][] = ['system' => 'BPJS Master', 'code' => $bpjsMed->id, 'display' => 'UUID', 'type' => 'Mapped (UUID)'];
                } else {
                    $codings['MedicationRequest'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (UUID)'];
                }
            }
            $resepPulangs = \App\Models\Simrs\ResepPulang::where('no_rawat', $this->reg->no_rawat)->with('dataBarang.kfa')->get();
            foreach ($resepPulangs as $resep) {
                $kfa = $resep->dataBarang?->kfa;
                if ($kfa) {
                    $codings['MedicationRequest'][] = ['system' => 'http://sys-ids.kemkes.go.id/kfa', 'code' => $kfa->kfa_code, 'display' => $kfa->kfa_display, 'type' => 'KFA'];
                } else {
                    $codings['MedicationRequest'][] = ['system' => 'Unmapped', 'code' => $resep->kode_brng, 'display' => $resep->dataBarang?->nama_brng ?? '-', 'type' => 'Obat (Missing KFA)'];
                }

                $bpjsMed = \App\Models\Bpjs\BpjsMedication::where('local_code', $resep->kode_brng)->first();
                if ($bpjsMed) {
                    $codings['MedicationRequest'][] = ['system' => 'BPJS Master', 'code' => $bpjsMed->id, 'display' => 'UUID', 'type' => 'Mapped (UUID)'];
                } else {
                    $codings['MedicationRequest'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (UUID)'];
                }
            }

            // DiagnosticReport Lab
            $permintaanLabs = \App\Models\Simrs\PermintaanLab::where('no_rawat', $this->reg->no_rawat)->with('periksaLab.detailPeriksaLab.template.map')->get();
            foreach ($permintaanLabs as $pl) {
                foreach ($pl->periksaLab as $plab) {
                    $bpjsProcLab = \App\Models\Bpjs\BpjsProcedure::where('type', 'lab')->where('local_code', $plab->kd_jenis_prw)->first();
                    if ($bpjsProcLab) {
                        $codings['DiagnosticReport Lab'][] = ['system' => 'BPJS Master', 'code' => $bpjsProcLab->id, 'display' => 'UUID Specimen', 'type' => 'Mapped (UUID)'];
                    } else {
                        $codings['DiagnosticReport Lab'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (UUID)'];
                    }

                    foreach ($plab->detailPeriksaLab as $detail) {
                        $map = $detail->template?->map;
                        if ($map) {
                            $codings['DiagnosticReport Lab'][] = ['system' => $map->system_display, 'code' => $map->system_code, 'display' => $map->system_term, 'type' => 'LOINC (Mapped)'];
                        } else {
                            $codings['DiagnosticReport Lab'][] = ['system' => 'Unmapped', 'code' => $detail->id_template, 'display' => $detail->template?->Pemeriksaan ?? '-', 'type' => 'Lab Item (Missing Map)'];
                        }

                        $bpjsProcItemLab = \App\Models\Bpjs\BpjsProcedure::where('type', 'item_lab')->where('local_code', $detail->id_template)->first();
                        if ($bpjsProcItemLab) {
                            $codings['DiagnosticReport Lab'][] = ['system' => 'BPJS Master', 'code' => $bpjsProcItemLab->id, 'display' => 'UUID Lab Item', 'type' => 'Mapped (UUID)'];
                        } else {
                            $codings['DiagnosticReport Lab'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (UUID)'];
                        }
                    }
                }
            }
            // DiagnosticReport Radiologi
            $permintaanRads = \App\Models\Simrs\PermintaanRadiologi::where('no_rawat', $this->reg->no_rawat)->with('allPeriksaRad.jenisPerawatan')->get();
            foreach ($permintaanRads as $pr) {
                foreach ($pr->allPeriksaRad as $prad) {
                    $map = \App\Models\Mapping\RadMap::where('local_code', $prad->kd_jenis_prw)->first();
                    if ($map) {
                        $codings['DiagnosticReport Radiologi'][] = ['system' => $map->system_display, 'code' => $map->system_code, 'display' => $map->system_term, 'type' => 'LOINC (Mapped)'];
                    } else {
                        $codings['DiagnosticReport Radiologi'][] = ['system' => 'Unmapped', 'code' => $prad->kd_jenis_prw, 'display' => $prad->jenisPerawatan?->nm_perawatan ?? '-', 'type' => 'Rad Item (Missing Map)'];
                    }

                    $bpjsProcRad = \App\Models\Bpjs\BpjsProcedure::where('type', 'rad')->where('local_code', $prad->kd_jenis_prw)->first();
                    if ($bpjsProcRad) {
                        $codings['DiagnosticReport Radiologi'][] = ['system' => 'BPJS Master', 'code' => $bpjsProcRad->id, 'display' => 'UUID', 'type' => 'Mapped (UUID)'];
                    } else {
                        $codings['DiagnosticReport Radiologi'][] = ['system' => 'BPJS Master', 'code' => 'Belum sinkron', 'display' => '-', 'type' => 'Missing (UUID)'];
                    }
                }
            }

            // DiagnosticReport Vital Sign
            $pemeriksaan = $this->reg->status_lanjut === 'Ranap' ? \App\Models\Simrs\PemeriksaanRanap::where('no_rawat', $this->reg->no_rawat)->orderBy('tgl_perawatan', 'desc')->orderBy('jam_rawat', 'desc')->first() : \App\Models\Simrs\PemeriksaanRalan::where('no_rawat', $this->reg->no_rawat)->orderBy('tgl_perawatan', 'desc')->orderBy('jam_rawat', 'desc')->first();

            if ($pemeriksaan) {
                $vitalMap = [
                    'suhu_tubuh' => \App\Constants\BpjsErmCodes::LOINC_VITAL_BODY_TEMPERATURE,
                    'tensi' => \App\Constants\BpjsErmCodes::LOINC_VITAL_BLOOD_PRESSURE_PANEL,
                    'nadi' => \App\Constants\BpjsErmCodes::LOINC_VITAL_HEART_RATE,
                    'respirasi' => \App\Constants\BpjsErmCodes::LOINC_VITAL_RESPIRATORY_RATE,
                    'spo2' => \App\Constants\BpjsErmCodes::LOINC_VITAL_SPO2,
                    'gcs' => \App\Constants\BpjsErmCodes::LOINC_VITAL_GCS,
                    'kesadaran' => \App\Constants\BpjsErmCodes::LOINC_VITAL_CONSCIOUSNESS,
                    'tinggi' => \App\Constants\BpjsErmCodes::LOINC_VITAL_BODY_HEIGHT,
                    'berat' => \App\Constants\BpjsErmCodes::LOINC_VITAL_BODY_WEIGHT,
                    'lingkar_perut' => \App\Constants\BpjsErmCodes::LOINC_VITAL_WAIST_CIRCUMFERENCE,
                ];
                foreach ($vitalMap as $col => $loincCode) {
                    if (!empty($pemeriksaan->{$col})) {
                        $loinc = \App\Models\Terminology\Loinc::where('loinc_num', $loincCode)->first();
                        if ($loinc) {
                            $codings['DiagnosticReport Vital Sign'][] = ['system' => 'http://loinc.org', 'code' => $loincCode, 'display' => $loinc->long_common_name, 'type' => 'LOINC'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignored
        }

        return $codings;
    }

    public function with()
    {
        $conn = DB::connection('simrs');

        // IGD detection
        $isIgd = $this->reg->status_lanjut === 'Ralan' && $this->reg->poliklinik && (Str::contains(strtolower($this->reg->poliklinik->nm_poli ?? ''), 'gawat') || Str::contains(strtolower($this->reg->poliklinik->kd_poli ?? ''), 'igd') || Str::contains(strtolower($this->reg->poliklinik->kd_poli ?? ''), 'ugd'));

        // Antrean Online Mobile JKN (hanya Ralan bukan IGD)
        $antreanTasks = collect();
        $antreanMetrics = [];
        if ($this->reg->status_lanjut === 'Ralan' && !$isIgd) {
            try {
                $antreanTasks = $conn->table('referensi_mobilejkn_bpjs_taskid')->where('no_rawat', $this->reg->no_rawat)->orderBy('taskid')->get()->keyBy('taskid');
                foreach ([['Waktu Tunggu Admisi', 2, 1], ['Waktu Layan Admisi', 3, 2], ['Waktu Tunggu Poli', 4, 3], ['Waktu Layan Poli', 5, 4], ['Waktu Tunggu Farmasi', 6, 5], ['Waktu Layan Farmasi', 7, 6]] as [$metricLabel, $end, $start]) {
                    if (isset($antreanTasks[$end]) && isset($antreanTasks[$start])) {
                        $t1 = \Carbon\Carbon::parse($antreanTasks[$start]->waktu);
                        $t2 = \Carbon\Carbon::parse($antreanTasks[$end]->waktu);
                        $diffSec = abs($t1->diffInSeconds($t2));
                        $antreanMetrics[] = [
                            'label' => $metricLabel,
                            'display' => sprintf('%d menit %d detik', intdiv($diffSec, 60), $diffSec % 60),
                        ];
                    }
                }
            } catch (\Exception $e) {
            }
        }

        // Apotek Online
        $apotekBpjs = collect();
        if ($this->reg->bridgingSep) {
            try {
                $apotekRaw = $conn
                    ->table('bridging_apotek_bpjs')
                    ->where('no_sep', $this->reg->bridgingSep->no_sep)
                    ->get();
                $noApoteks = $apotekRaw->pluck('no_apotek')->unique()->filter();
                $apotekObats = $noApoteks->isNotEmpty() ? $conn->table('bridging_apotek_bpjs_obat')->whereIn('no_apotek', $noApoteks)->get()->groupBy('no_apotek') : collect();
                $apotekBpjs = $apotekRaw->map(function ($a) use ($apotekObats) {
                    $a->obats = $apotekObats[$a->no_apotek] ?? collect();
                    return $a;
                });
            } catch (\Exception $e) {
            }
        }

        // Validasi eRM BPJS dan flag penjamin
        $ermValidator = new ErmValidator();
        $validationMessages = $ermValidator->validate($this->reg);
        $hasErmErrors = $ermValidator->hasErrors($validationMessages);
        $isBpjsKesehatan = Str::contains(strtolower($this->reg->penjab?->png_jawab ?? ''), 'bpjs');

        // Status kirim
        $hasSuccessErm = BpjsLog::forService('erm')->where('no_rawat', $this->reg->no_rawat)->where('status', 'success')->exists();
        $ermLog = BpjsLog::forService('erm')->where('no_rawat', $this->reg->no_rawat)->orderByDesc('created_at')->first();
        $ermLogs = BpjsLog::forService('erm')->where('no_rawat', $this->reg->no_rawat)->orderByDesc('created_at')->paginate(5, ['*'], 'ermPage');

        $isBpjsKesehatan ??= false;
        $hasErmErrors ??= false;
        $validationMessages ??= [];
        $antreanTasks ??= collect();
        $antreanMetrics ??= [];
        $apotekBpjs ??= collect();
        $isIgd ??= false;

        $tabs = [
            [
                'id' => 'sep',
                'icon' => 'document-check',
                'label' => 'SEP',
                'desc' => 'Surat Eligibilitas Peserta',
                'badgeText' => $this->reg->bridgingSep ? $this->reg->bridgingSep->no_sep : 'Tidak ada',
                'badgeColor' => $this->reg->bridgingSep ? 'green' : 'zinc',
                'show' => true,
            ],
            [
                'id' => 'erm',
                'icon' => 'paper-airplane',
                'label' => 'eRM',
                'desc' => 'Rekam Medis Elektronik',
                'badgeText' => $hasSuccessErm ? 'Sudah Kirim' : 'Belum Kirim',
                'badgeColor' => $hasSuccessErm ? 'green' : 'zinc',
                'show' => true,
            ],
            [
                'id' => 'antrean',
                'icon' => 'queue-list',
                'label' => 'Antrean Online',
                'desc' => 'Task ID Mobile JKN',
                'badgeText' => null,
                'badgeColor' => 'zinc',
                'show' => !$isIgd && $this->reg->status_lanjut === 'Ralan',
            ],
            [
                'id' => 'apotek',
                'icon' => 'beaker',
                'label' => 'Apotek Online',
                'desc' => 'Resep Elektronik BPJS',
                'badgeText' => $apotekBpjs->isNotEmpty() ? collect($apotekBpjs)->count() : 'Tidak ada',
                'badgeColor' => 'zinc',
                'show' => true,
            ],
        ];

        return compact('tabs', 'ermLog', 'ermLogs', 'hasSuccessErm', 'isBpjsKesehatan', 'hasErmErrors', 'validationMessages', 'antreanTasks', 'antreanMetrics', 'apotekBpjs', 'isIgd');
    }

    public function placeholder()
    {
        return view('placeholders._erm-bpjs');
    }
};
?>

<div x-data="{
    bpjsTab: new URLSearchParams(location.search).get('bpjsTab') || 'sep'
}" x-init="$watch('bpjsTab', val => {
    if (activeTab === 'bpjs') {
        let url = new URL(window.location.href);
        url.searchParams.set('bpjsTab', val);
        window.history.replaceState({}, '', url);
    }
});
$watch('activeTab', val => {
    if (val === 'bpjs') {
        let url = new URL(window.location.href);
        url.searchParams.set('bpjsTab', bpjsTab);
        window.history.replaceState({}, '', url);
    }
});">
    <div class="flex flex-col lg:flex-row gap-6 mt-4">

        {{-- Sidebar Navigasi --}}
        <aside class="w-full lg:w-64 shrink-0">
            <div
                class="bg-white dark:bg-primary-dark-800 lg:rounded-2xl lg:border border-zinc-200 dark:border-primary-dark-700 lg:shadow-sm overflow-hidden flex flex-col gap-1 lg:block">

                <div
                    class="hidden lg:block px-4 pt-4 pb-2 border-b border-zinc-100 dark:border-primary-dark-700/50 mb-2">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-400 dark:text-primary-dark-500">
                        Status BPJS</p>
                </div>

                {{-- Horizontal Layout for Mobile / Tablet --}}
                <div class="flex lg:hidden gap-1 p-1 overflow-x-auto bg-zinc-100 rounded-xl dark:bg-primary-dark-900/50">
                    @foreach ($tabs as $tab)
                    @if ($tab['show'])
                    <button @click="bpjsTab = '{{ $tab['id'] }}'"
                        :class="bpjsTab === '{{ $tab['id'] }}'
                                    ?
                                    'bg-white shadow-sm text-zinc-900 dark:bg-primary-dark-800 dark:text-primary-dark-100' :
                                    'text-zinc-500 hover:text-zinc-700 dark:text-primary-dark-400 dark:hover:text-primary-dark-200'"
                        class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-all whitespace-nowrap border dark:border-primary-dark-700/50"
                        :class="bpjsTab === '{{ $tab['id'] }}' ? 'border-zinc-200 dark:border-primary-dark-600' :
                                    'border-transparent'">
                        <flux:icon name="{{ $tab['icon'] }}" class="h-4 w-4" />
                        {{ $tab['label'] }}
                        @if ($tab['badgeText'])
                        <flux:badge color="{{ $tab['badgeColor'] }}" size="sm">{{ $tab['badgeText'] }}
                        </flux:badge>
                        @endif
                    </button>
                    @endif
                    @endforeach
                </div>

                {{-- Vertical Layout for Desktop --}}
                <nav class="hidden lg:flex flex-col px-2 pb-2 space-y-0.5">
                    @foreach ($tabs as $tab)
                    @if ($tab['show'])
                    <button @click="bpjsTab = '{{ $tab['id'] }}'"
                        :class="bpjsTab === '{{ $tab['id'] }}'
                                    ?
                                    'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300' :
                                    'text-zinc-600 dark:text-primary-dark-400 hover:bg-zinc-50 dark:hover:bg-primary-dark-700/40 hover:text-zinc-800 dark:hover:text-primary-dark-200'"
                        class="group flex items-center w-full gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors text-left relative">
                        <flux:icon name="{{ $tab['icon'] }}" class="h-5 w-5 shrink-0" />
                        <div class="flex-1 flex flex-col min-w-0 pr-16">
                            <span class="truncate">{{ $tab['label'] }}</span>
                            <span
                                :class="bpjsTab === '{{ $tab['id'] }}' ? 'text-primary-500 dark:text-primary-400' :
                                            'text-zinc-400 dark:text-primary-dark-500 group-hover:text-zinc-500'"
                                class="text-[10px] font-normal truncate">{{ $tab['desc'] }}</span>
                        </div>
                        @if ($tab['badgeText'])
                        <div class="absolute right-3 top-1/2 -translate-y-1/2">
                            <flux:badge color="{{ $tab['badgeColor'] }}" size="sm"
                                class="truncate max-w-[80px]">{{ $tab['badgeText'] }}</flux:badge>
                        </div>
                        @endif
                    </button>
                    @endif
                    @endforeach
                </nav>

            </div>
        </aside>

        {{-- Konten Dinamis --}}
        <div class="flex-1 min-w-0">

            {{-- 1. SEP --}}
            <div x-show="bpjsTab === 'sep'" x-cloak>
                @if ($this->reg->bridgingSep)
                @php $sep = $this->reg->bridgingSep; @endphp
                @include('pages.erm.detail-tabs._bpjs.sep', ['sep' => $sep])
                @else
                <div
                    class="flex flex-col items-center py-12 bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                    <flux:icon name="document-minus" class="w-12 h-12 text-zinc-300 dark:text-primary-dark-600" />
                    <p class="mt-2 text-sm text-zinc-500 dark:text-primary-dark-400">Pasien tidak memiliki SEP untuk
                        kunjungan ini.</p>
                </div>
                @endif
            </div>

            {{-- 2. eRM BPJS --}}
            <div x-show="bpjsTab === 'erm'" x-cloak
                class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                @include('pages.erm.detail-tabs._bpjs.erm')
            </div>

            {{-- 3. Antrian Online Mobile JKN (hanya Ralan bukan IGD) --}}
            @if (!$isIgd && $this->reg->status_lanjut === 'Ralan')
            <div x-show="bpjsTab === 'antrean'" x-cloak
                class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                @include('pages.erm.detail-tabs._bpjs.antrol')
            </div>
            @endif

            {{-- 4. Apotek Online --}}
            <div x-show="bpjsTab === 'apotek'" x-cloak
                class="overflow-hidden bg-white rounded-xl border border-zinc-200/80 shadow-sm dark:bg-primary-dark-800 dark:border-primary-dark-700/60">
                @include('pages.erm.detail-tabs._bpjs.apotek')
            </div>
        </div>
        @if ($showJsonModal && $ermJson)
        @php $jsonString = json_encode($ermJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); @endphp
        <x-organisms.modal name="json-preview" wire:model="showJsonModal" maxWidth="4xl" title="">
            <div x-data="{
                    copied: false,
                    copyToClipboard() {
                        const text = this.$refs.jsonCode.textContent;
                        const done = () => {
                            this.copied = true;
                            setTimeout(() => this.copied = false, 2000);
                        };
                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(text).then(done).catch(() => this.fallbackCopy(text, done));
                        } else { this.fallbackCopy(text, done); }
                    },
                    fallbackCopy(text, done) {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.cssText = 'position:fixed;left:-9999px';
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                        done();
                    }
                }">
                {{-- Header --}}
                <div class="mb-4">
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-primary-dark-100">FHIR Bundle JSON
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-primary-dark-400 mt-0.5">{{ $this->reg->no_rawat }}
                    </p>
                </div>

                {{-- Content --}}
                <span x-ref="jsonCode" class="hidden">{{ $jsonString }}</span>
                <x-atoms.code-block :searchable="true" language="json"
                    maxHeight="max-h-[65vh]">{{ $jsonString }}</x-atoms.code-block>

                {{-- Footer --}}
                <div class="flex items-center justify-between mt-4">
                    <div class="flex items-center gap-2">
                        <x-atoms.button icon="arrow-down-tray" wire:click="downloadJson" size="sm">Unduh
                            JSON
                        </x-atoms.button>
                        <x-atoms.button icon="clipboard-document" x-on:click="copyToClipboard()" size="sm">
                            <span x-text="copied ? 'Tersalin!' : 'Salin'">Salin</span>
                        </x-atoms.button>
                    </div>
                    <x-atoms.button icon="x-mark" wire:click="$set('showJsonModal', false)" variant="ghost"
                        size="sm">
                        Tutup
                    </x-atoms.button>
                </div>
            </div>

        </x-organisms.modal>
        @endif
    </div>
</div>