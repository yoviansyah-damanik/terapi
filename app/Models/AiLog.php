<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiLog extends BaseModel
{
    protected $guarded = [];

    // Jika ingin type casting, misalnya JSON, bisa ditambahkan jika prompt atau response berupa JSON.
    // Tapi karena longText berupa string mentah, kita biarkan default.
}
