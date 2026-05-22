<?php

namespace App\Models\WaGateway;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class WhatsappContact extends BaseModel
{
    protected $fillable = ['name', 'phone', 'group', 'notes'];

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    public function scopeGroup(Builder $query, ?string $group): Builder
    {
        if (!$group) {
            return $query;
        }
        return $query->where('group', $group);
    }
}
