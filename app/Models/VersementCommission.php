<?php

namespace App\Models;

use App\Enums\StatutVersementCommission;
use App\Models\Traits\HasUsineScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersementCommission extends Model
{
    use HasUsineScope;

    protected $table = 'versements_commission';

    protected $fillable = [
        'usine_id',
        'commission_vente_id',
        'beneficiaire_type',
        'beneficiaire_id',
        'montant_attendu',
        'montant_verse',
        'statut',
        'verse_par',
        'verse_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'montant_attendu' => 'decimal:2',
            'montant_verse'   => 'decimal:2',
            'statut'          => StatutVersementCommission::class,
            'verse_at'        => 'datetime',
        ];
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(CommissionVente::class, 'commission_vente_id');
    }
}
