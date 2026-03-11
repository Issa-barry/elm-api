<?php

namespace App\Models;

use App\Enums\StatutVersementCommission;
use App\Models\Traits\HasSiteScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VersementCommission extends Model
{
    use HasSiteScope;

    protected $table = 'versements_commission';

    protected $fillable = [
        'site_id',
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

    protected $appends = ['montant_restant'];

    protected function casts(): array
    {
        return [
            'montant_attendu' => 'decimal:2',
            'montant_verse'   => 'decimal:2',
            'statut'          => StatutVersementCommission::class,
            'verse_at'        => 'datetime',
        ];
    }

    public function getMontantRestantAttribute(): float
    {
        return max(0, (float) $this->montant_attendu - (float) $this->montant_verse);
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(CommissionVente::class, 'commission_vente_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementVersementCommission::class, 'versement_commission_id');
    }

    public function versePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verse_par');
    }

    /**
     * Recalcule et persiste le statut + montant_verse d'après la somme des paiements.
     */
    public function recalculStatut(): void
    {
        $totalVerse = (float) $this->paiements()->sum('montant');
        $attendu    = (float) $this->montant_attendu;

        if ($totalVerse <= 0) {
            $statut = StatutVersementCommission::EN_ATTENTE;
        } elseif ($totalVerse >= $attendu) {
            $statut     = StatutVersementCommission::EFFECTUE;
            $totalVerse = $attendu; // plafonnement au centième
        } else {
            $statut = StatutVersementCommission::PARTIELLEMENT_VERSE;
        }

        $this->update([
            'montant_verse' => $totalVerse,
            'statut'        => $statut->value,
            'verse_at'      => $totalVerse > 0 ? now() : null,
        ]);
    }
}
