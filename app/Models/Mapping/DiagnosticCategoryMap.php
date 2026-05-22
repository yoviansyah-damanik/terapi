<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;

/** Mapping kategori diagnostik (LAB, RAD, dll) untuk tindakan lokal */
class DiagnosticCategoryMap extends BaseModel
{
    protected $table = 'map_diagnostic_category';

    protected $fillable = [
        'local_code',
        'diagnostic_category',
        'diagnostic_category_term',
        'source',
    ];

    public function scopeLab($query)
    {
        return $query->where('source', 'lab')->first();
    }

    public function scopeRad($query)
    {
        return $query->where('source', 'rad')->first();
    }

    public function scopeObs($query)
    {
        return $query->where('source', 'observation')->first();
    }
}
