<?php

namespace App\Models\Simrs;

class IndexIns extends SimrsModel
{
    protected $table = 'indexins';

    protected $primaryKey = 'dep_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'dep_id',
        'persen',
    ];

    protected function casts(): array
    {
        return [
            'persen' => 'decimal:2',
        ];
    }
}
