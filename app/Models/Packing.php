<?php

namespace App\Models;

use App\Enums\PackingStatut;
use App\Models\Traits\HasSiteScope;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Packing extends Model
{
    use HasFactory, SoftDeletes, HasSiteScope;

    public const STATUT_IMPAYEE  = PackingStatut::IMPAYEE->value;
    public const STATUT_PARTIELLE = PackingStatut::PARTIELLE->value;
    public const STATUT_PAYEE    = PackingStatut::PAYEE->value;
    public const STATUT_ANNULEE  = PackingStatut::ANNULEE->value;

    public const STATUTS      = PackingStatut::LABELS;
    public const STATUT_DEFAUT = self::STATUT_IMPAYEE;

    protected $fillable = [
        'site_id',
        'prestataire_id',
        'date',
        'nb_rouleaux',
        'prix_par_rouleau',
        'statut',
        'notes',
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
            'date'            => 'date:Y-m-d',
            'nb_rouleaux'     => 'integer',
            'prix_par_rouleau'=> 'integer',
            'montant'         => 'integer',
            'created_by'      => 'integer',
            'updated_by'      => 'integer',
            'statut'          => PackingStatut::class,
        ];
    }

    /* =========================
       BOOT
       ========================= */

    protected static function booted(): void
    {
        static::creating(function (Packing $packing) {
            $packing->prepareForPersistence(true);
        });

        static::updating(function (Packing $packing) {
            $packing->prepareForPersistence(false);
        });
    }

    protected function prepareForPersistence(bool $isCreating): void
    {
        if ($isCreating && empty($this->reference)) {
            $this->reference = self::generateReference();
        }

        if (empty($this->statut)) {
            $this->statut = self::STATUT_DEFAUT;
        }

        if ((int) $this->nb_rouleaux > 0) {
            $configured = Parametre::query()
                ->where('cle', Parametre::CLE_PRODUIT_ROULEAU_ID)
                ->whereNotNull('valeur')
                ->where('valeur', '!=', '')
                ->value('valeur');

            if (!$configured) {
                throw ValidationException::withMessages([
                    'nb_rouleaux' => "Le produit rouleau n'est pas configure. Veuillez definir le parametre 'produit_rouleau_id'.",
                ]);
            }
        }

        $this->montant = $this->calculerMontant();

        if (Auth::check()) {
            if ($isCreating && !$this->created_by) {
                $this->created_by = Auth::id();
            }
            $this->updated_by = Auth::id();
        }
    }

    protected static function generateReference(): string
    {
        // Important: la référence est unique globalement sur la table packings.
        // On doit donc ignorer le scope usine, sinon chaque usine repart à 0001.
        $lastId = self::withoutSiteScope()->withTrashed()->max('id') ?? 0;

        return 'PACK-' . now()->format('Ymd') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
    }

    public function setNotesAttribute($value): void
    {
        $this->attributes['notes'] = $value ? trim($value) : null;
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

    public function versements(): HasMany
    {
        return $this->hasMany(Versement::class);
    }

    /* =========================
       ACCESSEURS
       ========================= */

    public function getStatutLabelAttribute(): string
    {
        if ($this->statut instanceof PackingStatut) {
            return $this->statut->label();
        }

        $value = is_string($this->statut) ? $this->statut : null;
        if ($value) {
            $enum = PackingStatut::tryFrom($value);
            if ($enum) {
                return $enum->label();
            }
        }

        return PackingStatut::IMPAYEE->label();
    }

    public function getPrestataireNomAttribute(): ?string
    {
        return $this->prestataire?->nom_complet;
    }

    public function getMontantVerseAttribute(): int
    {
        return (int) $this->versements()->sum('montant');
    }

    public function getMontantRestantAttribute(): int
    {
        return max(0, $this->montant - $this->montant_verse);
    }

    /* =========================
       SCOPES
       ========================= */

    public function scopeAValider(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_IMPAYEE);
    }

    public function scopeValides(Builder $query): Builder
    {
        return $query->whereIn('statut', [self::STATUT_IMPAYEE, self::STATUT_PARTIELLE, self::STATUT_PAYEE]);
    }

    public function scopeAnnules(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_ANNULEE);
    }

    public function scopeNonAnnules(Builder $query): Builder
    {
        return $query->whereIn('statut', [self::STATUT_IMPAYEE, self::STATUT_PARTIELLE, self::STATUT_PAYEE]);
    }

    public function scopePourPrestataire(Builder $query, int $prestataireId): Builder
    {
        return $query->where('prestataire_id', $prestataireId);
    }

    public function scopeParPrestataire(Builder $query, int $prestataireId): Builder
    {
        return $query->pourPrestataire($prestataireId);
    }

    public function scopeDuJour(Builder $query, mixed $date = null): Builder
    {
        if ($date instanceof DateTimeInterface) {
            $targetDate = $date->format('Y-m-d');
        } elseif (is_string($date) && trim($date) !== '') {
            $timestamp  = strtotime($date);
            $targetDate = $timestamp !== false ? date('Y-m-d', $timestamp) : now()->toDateString();
        } else {
            $targetDate = now()->toDateString();
        }

        return $query->whereDate('date', $targetDate);
    }

    public function scopeParPeriode(Builder $query, mixed $dateDebut, mixed $dateFin): Builder
    {
        return $query->where('date', '>=', $dateDebut)->where('date', '<=', $dateFin);
    }

    public function scopeParStatut(Builder $query, PackingStatut|string $statut): Builder
    {
        $value = $statut instanceof PackingStatut ? $statut->value : $statut;

        return $query->where('statut', $value);
    }

    public function scopeNonPayes(Builder $query): Builder
    {
        return $query->whereIn('statut', [self::STATUT_IMPAYEE, self::STATUT_PARTIELLE]);
    }

    /* =========================
       MÉTHODES MÉTIER
       ========================= */

    public function calculerMontant(): int
    {
        return self::calculerMontantPour((int) $this->nb_rouleaux, (int) $this->prix_par_rouleau);
    }

    public static function calculerMontantPour(int $nbRouleaux, int $prixParRouleau): int
    {
        if ($nbRouleaux < 0 || $prixParRouleau < 0) {
            throw new \InvalidArgumentException('nb_rouleaux et prix_par_rouleau doivent etre positifs.');
        }

        return $nbRouleaux * $prixParRouleau;
    }

    /**
     * Recalcule et sauvegarde le statut du packing depuis ses versements.
     * Retourne false si le packing est annulé (statut non modifiable).
     */
    public function mettreAJourStatut(): bool
    {
        if ($this->statut === PackingStatut::ANNULEE) {
            return false;
        }

        $montantVerse = (int) $this->versements()->sum('montant');

        if ($montantVerse <= 0) {
            $this->statut = PackingStatut::IMPAYEE;
        } elseif ($montantVerse >= $this->montant) {
            $this->statut = PackingStatut::PAYEE;
        } else {
            $this->statut = PackingStatut::PARTIELLE;
        }

        return $this->save();
    }

    /**
     * Décrémente le stock de rouleaux et met à jour le statut depuis les versements.
     * Appelé lors de la création d'un packing non annulé.
     */
    public function initialiserPaiement(): void
    {
        $this->decrementerStockRouleaux();
        $this->mettreAJourStatut();
    }

    /**
     * Annule le packing. Restaure le stock si $compenserStock = true.
     */
    public function annuler(bool $compenserStock = true): bool
    {
        return DB::transaction(function () use ($compenserStock) {
            /** @var Packing $packing */
            $packing = self::query()->lockForUpdate()->findOrFail($this->id);

            if ($packing->statut === PackingStatut::ANNULEE) {
                $this->syncFrom($packing);

                return true;
            }

            if ($compenserStock) {
                $packing->restaurerStockRouleaux();
            }

            $packing->statut = PackingStatut::ANNULEE;
            $saved           = $packing->save();

            $this->syncFrom($packing);

            return $saved;
        });
    }

    /**
     * Réactive un packing annulé : décrémente le stock et recalcule le statut.
     * On bascule d'abord le statut à IMPAYEE pour lever le verrou de mettreAJourStatut().
     */
    public function reactiver(): void
    {
        $this->statut = PackingStatut::IMPAYEE;
        $this->decrementerStockRouleaux();
        $this->mettreAJourStatut();
    }

    public function decrementerStockRouleaux(): void
    {
        if ($this->nb_rouleaux <= 0) {
            return;
        }

        $produitId = Parametre::getProduitRouleauId();
        if (!$produitId) {
            throw ValidationException::withMessages([
                'nb_rouleaux' => "Le produit rouleau n'est pas configure. Veuillez definir le parametre 'produit_rouleau_id'.",
            ]);
        }

        $stock = Stock::where('produit_id', $produitId)
            ->where('site_id', $this->site_id)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            throw ValidationException::withMessages([
                'nb_rouleaux' => "Stock rouleau non trouve pour cette usine.",
            ]);
        }

        if ($stock->qte_stock < $this->nb_rouleaux) {
            throw ValidationException::withMessages([
                'nb_rouleaux' => "Stock insuffisant. Stock disponible : {$stock->qte_stock} rouleaux.",
            ]);
        }

        $stock->ajuster(-$this->nb_rouleaux);
    }

    /**
     * Ajuste le stock rouleaux selon un delta de modification:
     * - delta > 0 : consommation supplémentaire (décrément)
     * - delta < 0 : restitution (incrément)
     */
    public function ajusterStockRouleauxSelonDelta(int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $produitId = Parametre::getProduitRouleauId();
        if (!$produitId) {
            throw ValidationException::withMessages([
                'nb_rouleaux' => "Le produit rouleau n'est pas configure. Veuillez definir le parametre 'produit_rouleau_id'.",
            ]);
        }

        $stock = Stock::where('produit_id', $produitId)
            ->where('site_id', $this->site_id)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            if ($delta < 0) {
                // Cas de restitution: créer la ligne stock manquante pour ne pas perdre l'ajustement.
                $stock = Stock::create([
                    'produit_id' => $produitId,
                    'site_id'    => $this->site_id,
                    'qte_stock'  => 0,
                ]);
            } else {
                throw ValidationException::withMessages([
                    'nb_rouleaux' => 'Stock rouleau non trouve pour cette usine.',
                ]);
            }
        }

        if ($delta > 0 && $stock->qte_stock < $delta) {
            throw ValidationException::withMessages([
                'nb_rouleaux' => "Stock insuffisant. Stock disponible : {$stock->qte_stock} rouleaux.",
            ]);
        }

        $stock->ajuster(-$delta);
    }

    protected function restaurerStockRouleaux(): void
    {
        $produitId = Parametre::getProduitRouleauId();
        if (!$produitId || $this->nb_rouleaux <= 0) {
            return;
        }

        $stock = Stock::where('produit_id', $produitId)
            ->where('site_id', $this->site_id)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            return;
        }

        $stock->ajuster($this->nb_rouleaux);
    }

    protected function syncFrom(self $packing): void
    {
        $this->setRawAttributes($packing->getAttributes(), true);
        $this->syncOriginal();
    }

    public static function getStatuts(): array
    {
        return PackingStatut::labels();
    }
}
