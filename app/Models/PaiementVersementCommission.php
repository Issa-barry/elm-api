<?php

namespace App\Models;

use App\Models\Traits\HasSiteScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementVersementCommission extends Model
{
    use HasSiteScope;

    protected $table = 'paiements_versements_commission';

    protected $fillable = [
        'site_id',
        'versement_commission_id',
        'montant',
        'date_paiement',
        'mode_paiement',
        'note',
        'verse_par',
    ];

    protected function casts(): array
    {
        return [
            'montant'        => 'decimal:2',
            'date_paiement'  => 'date',
        ];
    }

    public function versement(): BelongsTo
    {
        return $this->belongsTo(VersementCommission::class, 'versement_commission_id');
    }

    public function versePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verse_par');
    }
}
