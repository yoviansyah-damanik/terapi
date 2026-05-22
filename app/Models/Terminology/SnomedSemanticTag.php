<?php

namespace App\Models\Terminology;

use Illuminate\Database\Eloquent\Model;

class SnomedSemanticTag extends Model
{
    protected $fillable = ['tag', 'description', 'active'];
}
