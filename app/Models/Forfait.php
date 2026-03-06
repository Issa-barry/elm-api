<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Forfait extends Model
{
    protected $fillable = [
        'slug',
        'nom',
        'prix',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'prix'      => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function organisations(): HasMany
    {
        return $this->hasMany(Organisation::class);
    }
}
