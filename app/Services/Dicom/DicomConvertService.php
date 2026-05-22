<?php

namespace App\Services\Dicom;

use App\Models\Dicom\Worklist;
use Illuminate\Http\UploadedFile;

class DicomConvertService
{
    public function __construct(
        private readonly OrthancService $orthanc,
    ) {}

    /**
     * Konversi array gambar (JPG/PNG) ke instance DICOM via Orthanc.
     *
     * @param  UploadedFile[]  $files
     * @param  array           $tags   DICOM tags: PatientName, PatientID, StudyDate, Modality, dll
     * @return array  ['success' => bool, 'instances' => [...], 'message' => string]
     */
    public function convertImages(array $files, array $tags, ?string $noRawat = null, ?string $noorder = null, bool $force = false): array
    {
        if (!$this->orthanc->isConfigured()) {
            return ['success' => false, 'instances' => [], 'message' => 'Orthanc belum dikonfigurasi.'];
        }

        $instances  = [];
        $errors     = [];
        $studyId    = null;

        foreach ($files as $index => $file) {
            $base64   = $this->toBase64DataUri($file);
            $fileTags = array_merge($tags, [
                'SeriesNumber'   => '1',
                'InstanceNumber' => (string) (1000 + ($index + 1)),
                'SOPClassUID'    => '1.2.840.10008.5.1.4.1.1.7',
            ]);

            $result = $this->orthanc->createDicom($fileTags, $base64);

            if ($result['success'] && isset($result['data']['ID'])) {
                $instanceId = $result['data']['ID'];
                $instances[] = [
                    'instance_id' => $instanceId,
                    'file_name'   => $file->getClientOriginalName(),
                    'index'       => $index + 1,
                ];

                // Ambil study ID dari instance pertama
                if ($studyId === null && isset($result['data']['ParentStudy'])) {
                    $studyId = $result['data']['ParentStudy'];
                }
            } else {
                $errors[] = sprintf('File #%d (%s): %s', $index + 1, $file->getClientOriginalName(), $result['message']);
            }
        }

        // Cek duplikasi sebelum simpan
        if ($studyId !== null) {
            $accession = $tags['AccessionNumber'] ?? $noorder ?? $studyId;
            $existing = Worklist::find($accession);

            if ($existing && $existing->orthanc_study_id !== $studyId && !$force) {
                return [
                    'success'   => false,
                    'duplicate' => true,
                    'existing'  => $existing->toArray(),
                    'new_study' => $studyId,
                    'message'   => "Order #{$accession} sudah memiliki study di PACS. Timpa data lama dengan study baru ini?",
                ];
            }

            $this->recordStudy($studyId, $tags, $instances, $noRawat, $noorder);
        }

        if (!empty($errors) && empty($instances)) {
            return ['success' => false, 'instances' => [], 'message' => implode('; ', $errors)];
        }

        return [
            'success'   => !empty($instances),
            'instances' => $instances,
            'study_id'  => $studyId,
            'errors'    => $errors,
            'message'   => empty($errors)
                ? sprintf('%d gambar berhasil dikonversi ke DICOM.', count($instances))
                : sprintf('%d berhasil, %d gagal.', count($instances), count($errors)),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function toBase64DataUri(UploadedFile $file): string
    {
        $mime    = $file->getMimeType() ?? 'image/jpeg';
        $content = base64_encode(file_get_contents($file->getRealPath()));

        return "data:{$mime};base64,{$content}";
    }

    private function recordStudy(string $orthancStudyId, array $tags, array $instances, ?string $noRawat, ?string $noorder): void
    {
        $accession = $tags['AccessionNumber'] ?? $noorder ?? $orthancStudyId;

        Worklist::updateOrCreate(
            ['accession_number' => $accession],
            [
                'orthanc_study_id'  => $orthancStudyId,
                'no_rawat'          => $noRawat,
                'noorder'           => $noorder,
                'patient_id'        => $tags['PatientID'] ?? null,
                'patient_name'      => str_replace('^', ' ', $tags['PatientName'] ?? 'UNKNOWN'),
                'modality'          => $tags['Modality'] ?? null,
                'procedure_desc'    => $tags['StudyDescription'] ?? null,
                'scheduled_date'    => isset($tags['StudyDate'])
                    ? \Carbon\Carbon::createFromFormat('Ymd', $tags['StudyDate'])?->toDateTimeString()
                    : now()->toDateTimeString(),
                'instance_count'    => count($instances),
                'status'            => 'received',
            ]
        );
    }
}
