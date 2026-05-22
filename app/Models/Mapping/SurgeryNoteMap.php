<?php
namespace App\Models\Mapping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SurgeryNoteMap extends Model
{
    use HasUuids;

    protected $table = 'map_surgery_note';

    protected $fillable = [
        'procedure_code',
        'loinc_code',
        'loinc_term',
    ];
}
