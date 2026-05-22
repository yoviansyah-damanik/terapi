<?php

namespace App\Models\Terminology;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MedicalLexicon extends BaseModel
{
    use HasFactory;

    protected $table = 'medical_lexicons';
    protected $guarded = [];
}
