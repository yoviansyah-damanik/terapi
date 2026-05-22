<?php

namespace App\Models\Simrs;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BangsalGroup extends SimrsModel
{
    protected $table = 'bangsal_group';

    protected $primaryKey = 'id_group';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id_group',
        'nama_group',
        'status',
    ];

    public function detailBangsalGroup(): HasMany
    {
        return $this->hasMany(DetailBangsalGroup::class, 'id_group', 'id_group');
    }

    public function bangsals(): BelongsToMany
    {
        return $this->belongsToMany(
            Bangsal::class,
            'detail_bangsal_group',
            'id_group',
            'kd_bangsal',
            'id_group',
            'kd_bangsal'
        );
    }
}
