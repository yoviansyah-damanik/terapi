<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailBangsalGroup extends SimrsModel
{
    protected $table = 'detail_bangsal_group';

    public $incrementing = false;

    protected $fillable = [
        'id_group',
        'kd_bangsal',
    ];

    public function bangsalGroup(): BelongsTo
    {
        return $this->belongsTo(BangsalGroup::class, 'id_group', 'id_group');
    }

    public function bangsal(): BelongsTo
    {
        return $this->belongsTo(Bangsal::class, 'kd_bangsal', 'kd_bangsal');
    }
}
