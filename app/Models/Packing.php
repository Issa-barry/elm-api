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

    public const STATUT_A_VALIDER = 'a_valider';
    public const STATUT_VALIDE = 'valide';
    public const STATUT_ANNULE = 'annule';

    public const STATUTS = [
        self::STATUT_A_VALIDER => 'À valider',
        self::STATUT_VALIDE => 'Validé',
        self::STATUT_ANNULE => 'Annulé',
    ];

    public const STATUT_DEFAUT = self::STATUT_VALIDE;

    protected $fillable = [
        'prestataire_id',
        'date_debut',
        'date_fin',
        'nb_rouleaux',
        'prix_par_rouleau',
        'montant',
        'reference',
        'statut',
        'facture_id',
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

            // Statut par défaut
            if (empty($packing->statut)) {
                $packing->statut = self::STATUT_DEFAUT;
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

        // Après création, créer automatiquement une facture si statut = valide
        static::created(function ($packing) {
            if ($packing->statut === self::STATUT_VALIDE && !$packing->facture_id) {
                $facture = FacturePacking::create([
                    'prestataire_id' => $packing->prestataire_id,
                    'periode_debut' => $packing->date_debut,
                    'periode_fin' => $packing->date_fin,
                    'montant_total' => $packing->montant,
                    'nb_packings' => 1,
                    'statut' => FacturePacking::STATUT_IMPAYEE,
                ]);

                // Associer la facture au packing (sans déclencher d'events)
                $packing->updateQuietly(['facture_id' => $facture->id]);
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

    public function facture(): BelongsTo
    {
        return $this->belongsTo(FacturePacking::class, 'facture_id');
    }

    /* =========================
       ACCESSEURS
       ========================= */

    public function getStatutLabelAttribute(): string
    {
        if (!$this->statut) {
            return self::STATUTS[self::STATUT_DEFAUT];
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

    public function scopeAValider($query)
    {
        return $query->where('statut', self::STATUT_A_VALIDER);
    }

    public function scopeValides($query)
    {
        return $query->where('statut', self::STATUT_VALIDE);
    }

    public function scopeAnnules($query)
    {
        return $query->where('statut', self::STATUT_ANNULE);
    }

    public function scopeNonAnnules($query)
    {
        return $query->whereIn('statut', [self::STATUT_A_VALIDER, self::STATUT_VALIDE]);
    }

    public function scopeFacturables($query)
    {
        return $query->where('statut', self::STATUT_VALIDE)
                     ->whereNull('facture_id');
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

    /**
     * Valider le packing et créer automatiquement une facture
     */
    public function valider(): FacturePacking
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            // Créer la facture pour ce packing
            $facture = FacturePacking::create([
                'prestataire_id' => $this->prestataire_id,
                'periode_debut' => $this->date_debut,
                'periode_fin' => $this->date_fin,
                'montant_total' => $this->montant,
                'nb_packings' => 1,
                'statut' => FacturePacking::STATUT_IMPAYEE,
            ]);

            // Mettre à jour le packing
            $this->statut = self::STATUT_VALIDE;
            $this->facture_id = $facture->id;
            $this->save();

            return $facture;
        });
    }

    public function annuler(): bool
    {
        $this->statut = self::STATUT_ANNULE;
        return $this->save();
    }

    public function estFacturable(): bool
    {
        return $this->statut === self::STATUT_VALIDE && $this->facture_id === null;
    }

    public static function getStatuts(): array
    {
        return self::STATUTS;
    }
}
