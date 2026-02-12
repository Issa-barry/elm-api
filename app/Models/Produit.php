<?php

namespace App\Models;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Produit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nom',
        'code',
        'prix_usine',
        'prix_vente',
        'prix_achat',
        'qte_stock',
        'cout',
        'type',
        'statut',
        'archived_at',
        'description',
        'image_url',
        'created_by',
        'updated_by',
        'deleted_by',
        'archived_by',
    ];

    protected $casts = [
        'prix_usine' => 'integer',
        'prix_vente' => 'integer',
        'prix_achat' => 'integer',
        'cout' => 'integer',
        'qte_stock' => 'integer',
        'type' => ProduitType::class,
        'statut' => ProduitStatut::class,
        'archived_at' => 'datetime',
    ];

    protected $appends = ['in_stock', 'is_archived'];

    // ========================================
    // BOOT / OBSERVERS
    // ========================================

    protected static function booted(): void
    {
        // Auto-assigner created_by à la création
        static::creating(function (Produit $produit) {
            if (Auth::check() && !$produit->created_by) {
                $produit->created_by = Auth::id();
            }
            $produit->updated_by = Auth::id();

            // Service : qte_stock = 0 par défaut
            if ($produit->type === ProduitType::SERVICE) {
                $produit->qte_stock = 0;
            }

            // Auto-ajuster statut si stock = 0 et type avec stock
            static::ajusterStatutStock($produit);
        });

        // Auto-assigner updated_by à la mise à jour
        static::updating(function (Produit $produit) {
            if (Auth::check()) {
                $produit->updated_by = Auth::id();
            }

            // Service : forcer qte_stock = 0
            if ($produit->type === ProduitType::SERVICE) {
                $produit->qte_stock = 0;
            }

            // Gérer archivage
            if ($produit->isDirty('statut')) {
                if ($produit->statut === ProduitStatut::ARCHIVE && !$produit->archived_at) {
                    $produit->archived_at = now();
                    $produit->archived_by = Auth::id();
                } elseif ($produit->getOriginal('statut') === ProduitStatut::ARCHIVE->value && $produit->statut !== ProduitStatut::ARCHIVE) {
                    $produit->archived_at = null;
                    $produit->archived_by = null;
                }
            }

            static::ajusterStatutStock($produit);
        });

        // Auto-assigner deleted_by à la suppression
        static::deleting(function (Produit $produit) {
            if (Auth::check()) {
                $produit->deleted_by = Auth::id();
                $produit->saveQuietly();
            }
        });
    }

    /**
     * Ajuste le statut selon le stock
     */
    protected static function ajusterStatutStock(Produit $produit): void
    {
        // Ne pas ajuster pour les services (pas de stock)
        if ($produit->type === ProduitType::SERVICE) {
            return;
        }

        // Si statut actif et stock = 0 => rupture_stock
        if ($produit->statut === ProduitStatut::ACTIF && $produit->qte_stock <= 0) {
            $produit->statut = ProduitStatut::RUPTURE_STOCK;
        }

        // Si rupture_stock et stock > 0 => retour actif
        if ($produit->statut === ProduitStatut::RUPTURE_STOCK && $produit->qte_stock > 0) {
            $produit->statut = ProduitStatut::ACTIF;
        }
    }

    // ========================================
    // ACCESSEURS CALCULÉS
    // ========================================

    /**
     * Vérifie si le produit est en stock (calculé)
     */
    public function getInStockAttribute(): bool
    {
        // Service : toujours disponible (pas de notion de stock)
        if ($this->type === ProduitType::SERVICE) {
            return true;
        }

        return $this->qte_stock > 0;
    }

    /**
     * Vérifie si le produit est archivé (calculé)
     */
    public function getIsArchivedAttribute(): bool
    {
        return $this->statut === ProduitStatut::ARCHIVE;
    }

    /**
     * Vérifie si le produit est disponible à la vente
     */
    public function getIsAvailableAttribute(): bool
    {
        return $this->statut === ProduitStatut::ACTIF && $this->in_stock;
    }

    // ========================================
    // RELATIONS
    // ========================================

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function archivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Produits actifs (disponibles à la vente)
     */
    public function scopeActifs($query)
    {
        return $query->where('statut', ProduitStatut::ACTIF);
    }

    /**
     * Produits en rupture de stock
     */
    public function scopeRuptureStock($query)
    {
        return $query->where('statut', ProduitStatut::RUPTURE_STOCK);
    }

    /**
     * Produits archivés
     */
    public function scopeArchives($query)
    {
        return $query->where('statut', ProduitStatut::ARCHIVE);
    }

    /**
     * Produits non archivés
     */
    public function scopeNonArchives($query)
    {
        return $query->where('statut', '!=', ProduitStatut::ARCHIVE);
    }

    /**
     * Produits disponibles (actifs avec stock ou services)
     */
    public function scopeDisponibles($query)
    {
        return $query->where(function ($q) {
            $q->where('statut', ProduitStatut::ACTIF)
              ->where(function ($sub) {
                  $sub->where('qte_stock', '>', 0)
                      ->orWhere('type', ProduitType::SERVICE);
              });
        });
    }

    /**
     * Par type
     */
    public function scopeDeType($query, ProduitType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Par statut
     */
    public function scopeDeStatut($query, ProduitStatut $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Brouillons
     */
    public function scopeBrouillons($query)
    {
        return $query->where('statut', ProduitStatut::BROUILLON);
    }

    // ========================================
    // MÉTHODES MÉTIER
    // ========================================

    /**
     * Change le statut du produit
     */
    public function changerStatut(ProduitStatut $nouveauStatut): bool
    {
        if (!$this->statut->canTransitionTo($nouveauStatut)) {
            return false;
        }

        $this->statut = $nouveauStatut;
        return $this->save();
    }

    /**
     * Archive le produit
     */
    public function archiver(): bool
    {
        return $this->changerStatut(ProduitStatut::ARCHIVE);
    }

    /**
     * Désarchive le produit
     */
    public function desarchiver(ProduitStatut $nouveauStatut = ProduitStatut::INACTIF): bool
    {
        if ($this->statut !== ProduitStatut::ARCHIVE) {
            return false;
        }

        return $this->changerStatut($nouveauStatut);
    }

    /**
     * Met à jour le stock
     */
    public function ajusterStock(int $quantite): bool
    {
        // Service : pas de stock
        if ($this->type === ProduitType::SERVICE) {
            return false;
        }

        $this->qte_stock = max(0, $this->qte_stock + $quantite);
        return $this->save();
    }

    /**
     * Vérifie si les prix sont valides selon le type
     */
    public function validatePricesForType(): array
    {
        $errors = [];

        if ($this->type === ProduitType::SERVICE) {
            $hasPrixAchat = !is_null($this->prix_achat) && $this->prix_achat !== '';
            $hasPrixVente = !is_null($this->prix_vente) && $this->prix_vente !== '';

            if (!$hasPrixAchat && !$hasPrixVente) {
                $errors['prix_achat'] = 'Pour un service, renseignez au moins un prix : achat ou vente.';
            }

            return $errors;
        }

        $requiredPrices = $this->type->requiredPrices();

        foreach ($requiredPrices as $priceField) {
            if (empty($this->{$priceField})) {
                $errors[$priceField] = "Le champ {$priceField} est obligatoire pour le type {$this->type->label()}";
            }
        }

        return $errors;
    }
}
