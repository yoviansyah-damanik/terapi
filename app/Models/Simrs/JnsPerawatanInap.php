<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JnsPerawatanInap extends SimrsModel
{
    use HasFactory;

    protected $table = 'jns_perawatan_inap';
    protected $primaryKey = 'kd_jenis_prw';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $guarded = [];

    public function scopeActive($query)
    {
        return $query->where('status', '1');
    }

    public function snomedMap()
    {
        return $this->hasOne(\App\Models\Mapping\ProcedureMap::class, 'procedure_code', 'kd_jenis_prw')
            ->where('source_table', 'inap');
    }
}
