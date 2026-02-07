<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class FacturePacking extends Model
{
    use HasFactory, SoftDeletes;

    /* =========================
       STATUTS
       ========================= */

    public const STATUT_IMPAYEE = 'impayee';
    public const STATUT_PARTIELLE = 'partielle';
    public const STATUT_PAYEE = 'payee';
    public const STATUT_ANNULEE = 'annulee';

    public const STATUTS = [
        self::STATUT_IMPAYEE => 'Impayée',
        self::STATUT_PARTIELLE => 'Partiellement payée',
        self::STATUT_PAYEE => 'Payée',
        self::STATUT_ANNULEE => 'Annulée',
    ];

    public const STATUT_DEFAUT = self::STATUT_IMPAYEE;

    protected $table = 'facture_packings';

    protected $fillable = [
        'reference',
        'prestataire_id',
        'periode_debut',
        'periode_fin',
        'montant_total',
        'nb_packings',
        'statut',
        'notes',
        'created_by',
    ];

    protected $appends = [
        'statut_label',
        'prestataire_nom',
        'montant_verse',
        'montant_restant',
    ];

    protected function casts(): array
    {
        return [
            'periode_debut' => 'date',
            'periode_fin' => 'date',
            'montant_total' => 'integer',
            'nb_packings' => 'integer',
        ];
    }

    /* =========================
       BOOT / OBSERVERS
       ========================= */

    protected static function booted(): void
    {
        static::creating(function ($facture) {
            // Auto-générer la référence
            if (empty($facture->reference)) {
                $lastId = self::withTrashed()->max('id') ?? 0;
                $facture->reference = 'FACT-PACK-' . now()->format('Ymd') . '-' . str_pad(
                    $lastId + 1,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
            }

            // Statut par défaut
            if (empty($facture->statut)) {
                $facture->statut = self::STATUT_DEFAUT;
            }

            // Traçabilité
            if (Auth::check() && !$facture->created_by) {
                $facture->created_by = Auth::id();
            }
        });
    }

    /* =========================
       RELATIONS
       ========================= */

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(Prestataire::class);
    }

    public function packings(): HasMany
    {
        return $this->hasMany(Packing::class, 'facture_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versements(): HasMany
    {
        return $this->hasMany(Versement::class, 'facture_packing_id');
    }

    /* =========================
       ACCESSEURS
       ========================= */

    public function getStatutLabelAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function getPrestataireNomAttribute(): ?string
    {
        return $this->prestataire?->nom_complet ?? $this->prestataire?->raison_sociale;
    }

    public function getMontantVerseAttribute(): int
    {
        return (int) $this->versements()->sum('montant');
    }

    public function getMontantRestantAttribute(): int
    {
        return $this->montant_total - $this->montant_verse;
    }

    /* =========================
       SCOPES
       ========================= */

    public function scopeParPrestataire($query, int $prestataireId)
    {
        return $query->where('prestataire_id', $prestataireId);
    }

    public function scopeParPeriode($query, $dateDebut, $dateFin)
    {
        return $query->where('periode_debut', '>=', $dateDebut)
                     ->where('periode_fin', '<=', $dateFin);
    }

    public function scopeImpayees($query)
    {
        return $query->where('statut', self::STATUT_IMPAYEE);
    }

    public function scopePartielles($query)
    {
        return $query->where('statut', self::STATUT_PARTIELLE);
    }

    public function scopePayees($query)
    {
        return $query->where('statut', self::STATUT_PAYEE);
    }

    public function scopeAnnulees($query)
    {
        return $query->where('statut', self::STATUT_ANNULEE);
    }

    public function scopeNonAnnulees($query)
    {
        return $query->whereIn('statut', [self::STATUT_IMPAYEE, self::STATUT_PARTIELLE, self::STATUT_PAYEE]);
    }

    public function scopeNonPayees($query)
    {
        return $query->whereIn('statut', [self::STATUT_IMPAYEE, self::STATUT_PARTIELLE]);
    }

    /* =========================
       MÉTHODES MÉTIER
       ========================= */

    /**
     * Mettre à jour le statut en fonction des versements
     */
    public function mettreAJourStatut(): bool
    {
        if ($this->statut === self::STATUT_ANNULEE) {
            return false;
        }

        $montantVerse = $this->montant_verse;

        if ($montantVerse <= 0) {
            $this->statut = self::STATUT_IMPAYEE;
        } elseif ($montantVerse >= $this->montant_total) {
            $this->statut = self::STATUT_PAYEE;
        } else {
            $this->statut = self::STATUT_PARTIELLE;
        }

        return $this->save();
    }

    /**
     * Annuler la facture et libérer les packings
     */
    public function annuler(): bool
    {
        // Remettre les packings en statut "a_valider" (facturables à nouveau)
        $this->packings()->update([
            'statut' => Packing::STATUT_A_VALIDER,
            'facture_id' => null,
        ]);

        $this->statut = self::STATUT_ANNULEE;
        return $this->save();
    }

    /**
     * Supprimer la facture et libérer les packings
     */
    public function supprimer(): bool
    {
        // Remettre les packings en statut "a_valider" (facturables à nouveau)
        $this->packings()->update([
            'statut' => Packing::STATUT_A_VALIDER,
            'facture_id' => null,
        ]);

        return $this->delete();
    }

    /**
     * Récupérer les statuts disponibles
     */
    public static function getStatuts(): array
    {
        return self::STATUTS;
    }
}
