<?php

namespace App\Models;

use App\Enums\PackingStatut;
use App\Models\Traits\HasUsineScope;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Packing extends Model
{
    use HasFactory, SoftDeletes, HasUsineScope;

    public const STATUT_A_VALIDER = PackingStatut::A_VALIDER->value;
    public const STATUT_VALIDE = PackingStatut::VALIDE->value;
    public const STATUT_ANNULE = PackingStatut::ANNULE->value;

    public const STATUTS = PackingStatut::LABELS;

    public const STATUT_DEFAUT = self::STATUT_VALIDE;

    protected $fillable = [
        'usine_id',
        'prestataire_id',
        'date',
        'nb_rouleaux',
        'prix_par_rouleau',
        'statut',
        'facture_id',
        'notes',
    ];

    protected $appends = [
        'statut_label',
        'prestataire_nom',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'nb_rouleaux' => 'integer',
            'prix_par_rouleau' => 'integer',
            'montant' => 'integer',
            'facture_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'statut' => PackingStatut::class,
        ];
    }

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
        do {
            $reference = 'PACK-' . now()->format('Ymd') . '-' . Str::upper((string) Str::ulid());
        } while (self::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    public function setNotesAttribute($value): void
    {
        $this->attributes['notes'] = $value ? trim($value) : null;
    }

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

        return PackingStatut::VALIDE->label();
    }

    public function getPrestataireNomAttribute(): ?string
    {
        return $this->prestataire?->nom_complet;
    }

    public function scopeAValider(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_A_VALIDER);
    }

    public function scopeValides(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_VALIDE);
    }

    public function scopeAnnules(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_ANNULE);
    }

    public function scopeNonAnnules(Builder $query): Builder
    {
        return $query->whereIn('statut', [self::STATUT_A_VALIDER, self::STATUT_VALIDE]);
    }

    public function scopeFacturables(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_VALIDE)->whereNull('facture_id');
    }

    public function scopeSansFacture(Builder $query): Builder
    {
        return $query->whereNull('facture_id');
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
            $timestamp = strtotime($date);
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
        return $query->whereNull('facture_id');
    }

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

    public function valider(): ?FacturePacking
    {
        return DB::transaction(function () {
            /** @var Packing $packing */
            $packing = self::query()->lockForUpdate()->findOrFail($this->id);

            if ($packing->statut === PackingStatut::ANNULE) {
                throw ValidationException::withMessages([
                    'statut' => 'Un packing annule ne peut pas etre valide.',
                ]);
            }

            if ($packing->statut === PackingStatut::VALIDE) {
                $this->syncFrom($packing);
                return $packing->facture;
            }

            $packing->decrementerStockRouleaux();

            $facture = $packing->facture;
            if (!$facture && !$packing->facture_id) {
                $factureDate = $packing->date;
                if ($factureDate instanceof DateTimeInterface) {
                    $factureDate = $factureDate->format('Y-m-d');
                }

                $facture = FacturePacking::create([
                    'prestataire_id' => $packing->prestataire_id,
                    'date' => $factureDate ?: now()->toDateString(),
                    'montant_total' => $packing->montant,
                    'nb_packings' => 1,
                    'statut' => FacturePacking::STATUT_IMPAYEE,
                ]);

                $packing->facture_id = $facture->id;
            }

            $packing->statut = PackingStatut::VALIDE;
            $packing->save();

            $this->syncFrom($packing);

            return $facture ?? $packing->facture;
        });
    }

    public function annuler(bool $compenserStock = true): bool
    {
        return DB::transaction(function () use ($compenserStock) {
            /** @var Packing $packing */
            $packing = self::query()->lockForUpdate()->findOrFail($this->id);

            if ($packing->statut === PackingStatut::ANNULE) {
                $this->syncFrom($packing);
                return true;
            }

            if ($compenserStock && $packing->statut === PackingStatut::VALIDE) {
                $packing->restaurerStockRouleaux();
            }

            $packing->statut = PackingStatut::ANNULE;
            $saved = $packing->save();

            $this->syncFrom($packing);

            return $saved;
        });
    }

    protected function decrementerStockRouleaux(): void
    {
        $produitId = Parametre::getProduitRouleauId();
        if (!$produitId || $this->nb_rouleaux <= 0) {
            return;
        }

        $produit = Produit::query()->lockForUpdate()->find($produitId);
        if (!$produit) {
            return;
        }

        if ($produit->qte_stock < $this->nb_rouleaux) {
            throw ValidationException::withMessages([
                'nb_rouleaux' => "Stock insuffisant. Stock disponible : {$produit->qte_stock} rouleaux.",
            ]);
        }

        $produit->ajusterStock(-$this->nb_rouleaux);
    }

    protected function restaurerStockRouleaux(): void
    {
        $produitId = Parametre::getProduitRouleauId();
        if (!$produitId || $this->nb_rouleaux <= 0) {
            return;
        }

        $produit = Produit::query()->lockForUpdate()->find($produitId);
        if (!$produit) {
            return;
        }

        $produit->ajusterStock($this->nb_rouleaux);
    }

    protected function syncFrom(self $packing): void
    {
        $this->setRawAttributes($packing->getAttributes(), true);
        $this->syncOriginal();
    }

    public function estFacturable(): bool
    {
        return $this->statut === PackingStatut::VALIDE && $this->facture_id === null;
    }

    public static function getStatuts(): array
    {
        return PackingStatut::labels();
    }
}
