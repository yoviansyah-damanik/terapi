<?php

namespace App\Models\Simrs\Usg;

use App\Models\Simrs\SimrsModel;

abstract class HasilPemeriksaanUsgGambarBase extends SimrsModel
{
    protected $primaryKey = 'no_rawat';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['no_rawat', 'noorder', 'photo'];

    public function getPhotoUrlAttribute(): string
    {
        $folder = str_replace('gambar', '', strtolower(class_basename($this)));
        $path   = $folder . '/' . $this->photo;

        return route('proxy.simrs-image', ['path' => $path]);
    }
}
