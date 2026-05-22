<?php

namespace App\Models\Mapping;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model penghubung Alat Kesehatan dengan Tindakan Lab/Radiologi.
 */
class DeviceActionMap extends BaseModel
{
    protected $table = 'map_device_actions';

    protected $fillable = [
        'device_code',
        'action_code',
        'action_type',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(DeviceMap::class, 'device_code', 'local_code');
    }

    /**
     * Mematikan auto-generation UUID jika sudah dihandle di database atau BaseModel.
     * Karena BaseModel sudah menggunakan HasUuids, maka 'id' akan terisi otomatis.
     */
}
