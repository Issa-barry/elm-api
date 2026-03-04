<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMouvement extends Model
{
    protected $fillable = [
        'produit_id',
        'site_id',
        'variation',
        'qte_avant',
        'qte_apres',
    ];

    protected $casts = [
        'variation' => 'integer',
        'qte_avant' => 'integer',
        'qte_apres' => 'integer',
    ];

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
