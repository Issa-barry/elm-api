<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Packing extends Model
{
    use HasFactory, SoftDeletes;

    /* =========================
       STATUTS
       ========================= */

    public const STATUT_EN_COURS = 'en_cours';
    public const STATUT_TERMINE = 'termine';
    public const STATUT_PAYE = 'paye';
    public const STATUT_ANNULE = 'annule';

    public const STATUTS = [
        self::STATUT_EN_COURS => 'En cours',
        self::STATUT_TERMINE => 'Terminé',
        self::STATUT_PAYE => 'Payé',
        self::STATUT_ANNULE => 'Annulé',
    ];

    protected $fillable = [
        'prestataire_id',
        'date_debut',
        'date_fin',
        'nb_rouleaux',
        'prix_par_rouleau',
        'montant',
        'reference',
        'statut',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'statut_label',
        'prestataire_nom',
        'duree_jours',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'nb_rouleaux' => 'integer',
            'prix_par_rouleau' => 'integer',
            'montant' => 'integer',
        ];
    }

    /* =========================
       BOOT / OBSERVERS
       ========================= */

    protected static function booted(): void
    {
        static::creating(function ($packing) {
            // Auto-générer la référence
            if (empty($packing->reference)) {
                $lastId = self::withTrashed()->max('id') ?? 0;
                $packing->reference = 'PACK-' . now()->format('Ymd') . '-' . str_pad(
                    $lastId + 1,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
            }

            // Calculer le montant
            $packing->montant = $packing->nb_rouleaux * $packing->prix_par_rouleau;

            // Traçabilité
            if (Auth::check() && !$packing->created_by) {
                $packing->created_by = Auth::id();
            }
            $packing->updated_by = Auth::id();
        });

        static::updating(function ($packing) {
            // Recalculer le montant
            $packing->montant = $packing->nb_rouleaux * $packing->prix_par_rouleau;

            // Traçabilité
            if (Auth::check()) {
                $packing->updated_by = Auth::id();
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /* =========================
       ACCESSEURS
       ========================= */

    public function getStatutLabelAttribute(): string
    {
        if (!$this->statut) {
            return self::STATUTS[self::STATUT_EN_COURS];
        }
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function getPrestataireNomAttribute(): ?string
    {
        return $this->prestataire?->nom_complet ?? $this->prestataire?->raison_sociale;
    }

    public function getDureeJoursAttribute(): int
    {
        if (!$this->date_debut || !$this->date_fin) {
            return 0;
        }
        return $this->date_debut->diffInDays($this->date_fin) + 1;
    }

    /* =========================
       SCOPES
       ========================= */

    public function scopeEnCours($query)
    {
        return $query->where('statut', self::STATUT_EN_COURS);
    }

    public function scopeTermines($query)
    {
        return $query->where('statut', self::STATUT_TERMINE);
    }

    public function scopePayes($query)
    {
        return $query->where('statut', self::STATUT_PAYE);
    }

    public function scopeNonPayes($query)
    {
        return $query->whereIn('statut', [self::STATUT_EN_COURS, self::STATUT_TERMINE]);
    }

    public function scopeParPrestataire($query, int $prestataireId)
    {
        return $query->where('prestataire_id', $prestataireId);
    }

    public function scopeParPeriode($query, $dateDebut, $dateFin)
    {
        return $query->where('date_debut', '>=', $dateDebut)
                     ->where('date_fin', '<=', $dateFin);
    }

    public function scopeParStatut($query, string $statut)
    {
        return $query->where('statut', $statut);
    }

    /* =========================
       MÉTHODES MÉTIER
       ========================= */

    public function calculerMontant(): int
    {
        return $this->nb_rouleaux * $this->prix_par_rouleau;
    }

    public function terminer(): bool
    {
        $this->statut = self::STATUT_TERMINE;
        return $this->save();
    }

    public function marquerPaye(): bool
    {
        $this->statut = self::STATUT_PAYE;
        return $this->save();
    }

    public function annuler(): bool
    {
        $this->statut = self::STATUT_ANNULE;
        return $this->save();
    }

    public static function getStatuts(): array
    {
        return self::STATUTS;
    }
}
