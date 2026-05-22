<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;
use App\Models\Simrs\JnsPerawatanLab;
use App\Models\Simrs\TemplateLaboratorium;

class LabItemMap extends BaseModel
{
    protected $table = 'map_lab_item';

    protected $fillable = [
        'kd_jenis_prw',
        'id_template',
        'system_code',
        'system_term',
        'system_display',
    ];

    public function jenisPerawatan()
    {
        return $this->belongsTo(JnsPerawatanLab::class, 'kd_jenis_prw', 'kd_jenis_prw');
    }

    public function template()
    {
        return $this->belongsTo(TemplateLaboratorium::class, 'id_template', 'id_template')
            ->where('template_laboratorium.kd_jenis_prw', $this->kd_jenis_prw);
    }
}
