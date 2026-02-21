<?php

namespace App\Models;

use App\Enums\ModePaiement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncaissementLivraison extends Model
{
    use HasFactory;

    protected $table = 'encaissements_livraisons';

    protected $fillable = [
        'facture_livraison_id',
        'montant',
        'date_encaissement',
        'mode_paiement',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'montant'           => 'decimal:2',
            'date_encaissement' => 'date',
            'mode_paiement'     => ModePaiement::class,
        ];
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(FactureLivraison::class, 'facture_livraison_id');
    }
}
