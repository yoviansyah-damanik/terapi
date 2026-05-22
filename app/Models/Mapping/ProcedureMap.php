<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;
use Illuminate\Support\Facades\DB;

class ProcedureMap extends BaseModel
{
    protected $table = 'map_procedure';

    public const PROCEDURE_CATEGORIES = [
        '24642003' => 'Psychiatry procedure or service',
        '409063005' => 'Counselling',
        '409073007' => 'Education',
        '387713003' => 'Surgical procedure',
        '103693007' => 'Diagnostic procedure',
        '46947000' => 'Chiropractic manipulation',
        '410606002' => 'Social service procedure',
        '277132007' => 'Therapeutic procedure',
        '161664006' => 'History of blood transfusion',
        '107733003' => 'Introduction procedure',
        '171201007' => 'Anemia screening',
        '119270007' => 'Management procedure',
        '113018007' => 'Cardioassist',
        '2517002' => 'Stroke rehabilitation',
        '440626008' => 'Procedure related to breastfeeding',
        '61310001' => 'Nutrition education',
        '709543002' => 'Administration of vitamin',
    ];

    protected $fillable = [
        'procedure_code',
        'source_table',
        'system_code',
        'system_term',
        'system_display',
        'category_code',
        'category_term',
        'category_display',
    ];

    public function getProcedureNameAttribute()
    {
        return match ($this->source_table) {
            'jalan' => DB::table('jns_perawatan')->where('kd_jenis_prw', $this->procedure_code)->value('nm_perawatan'),
            'inap' => DB::table('jns_perawatan_inap')->where('kd_jenis_prw', $this->procedure_code)->value('nm_perawatan'),
            'lab' => DB::table('jns_perawatan_lab')->where('kd_jenis_prw', $this->procedure_code)->value('nm_perawatan'),
            'radiologi' => DB::table('jns_perawatan_radiologi')->where('kd_jenis_prw', $this->procedure_code)->value('nm_perawatan'),
            default => null,
        };
    }
}
