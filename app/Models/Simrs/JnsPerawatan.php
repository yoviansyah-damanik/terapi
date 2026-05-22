<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JnsPerawatan extends SimrsModel
{
    use HasFactory;

    protected $table = 'jns_perawatan';
    protected $primaryKey = 'kd_jenis_prw';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Usually legacy tables don't have standard timestamps

    protected $guarded = [];

    public function scopeActive($query)
    {
        return $query->where('status', '1');
    }

    public function snomedMap()
    {
        return $this->hasOne(\App\Models\Mapping\ProcedureMap::class, 'procedure_code', 'kd_jenis_prw')
            ->where('source_table', 'jalan');
    }
}
