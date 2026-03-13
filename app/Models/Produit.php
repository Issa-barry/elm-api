<?php

namespace App\Models;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Models\Traits\HasSiteScope;
use App\Services\SiteContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Produit extends Model
{
    use HasFactory, SoftDeletes, HasSiteScope;

    protected $fillable = [
        'site_id',
        'is_global',
        'nom',
        'code',
        'prix_usine',
        'prix_vente',
        'prix_achat',
        'cout',
        'type',
        'statut',
        'archived_at',
        'description',
        'image_url',
        'is_critique',
        'last_stockout_notified_at',
        'code_interne',
        'code_fournisseur',
        'created_by',
        'updated_by',
        'deleted_by',
        'archived_by',
    ];

    protected $casts = [
        'prix_usine'               => 'integer',
        'prix_vente'               => 'integer',
        'prix_achat'               => 'integer',
        'cout'                     => 'integer',
        'type'                     => ProduitType::class,
        'statut'                   => ProduitStatut::class,
        'archived_at'              => 'datetime',
        'is_critique'              => 'boolean',
        'is_global'                => 'boolean',
        'last_stockout_notified_at'=> 'datetime',
    ];

    protected $appends = ['qte_stock', 'in_stock', 'is_archived', 'is_low_stock', 'is_out_of_stock', 'low_stock_threshold'];

    /* =========================
       FORMATAGE AUTOMATIQUE
       ========================= */

    public function setNomAttribute($value): void
    {
        $normalizedNom = $this->normalizeText($value);

        if ($normalizedNom === null) {
            $this->attributes['nom'] = null;
            return;
        }

        $lowerNom  = mb_strtolower($normalizedNom, 'UTF-8');
        $firstChar = mb_strtoupper(mb_substr($lowerNom, 0, 1, 'UTF-8'), 'UTF-8');
        $rest      = mb_substr($lowerNom, 1, null, 'UTF-8');

        $this->attributes['nom'] = $firstChar . $rest;
    }

    public function setCodeAttribute($value): void
    {
        $normalizedCode = $this->normalizeText($value, false);

        if ($normalizedCode === null) {
            $this->attributes['code'] = null;
            return;
        }

        $normalizedCode = preg_replace('/\s+/u', '', $normalizedCode) ?? $normalizedCode;

        $this->attributes['code'] = mb_strtoupper($normalizedCode, 'UTF-8');
    }

    /**
     * Code128 : trim + suppression espaces parasites + uppercase.
     * Accepte tout caractère ASCII imprimable (0x20–0x7E).
     */
    public function setCodeInterneAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['code_interne'] = null;
            return;
        }
        $this->attributes['code_interne'] = mb_strtoupper(
            preg_replace('/\s+/', '', trim((string) $value)),
            'UTF-8'
        );
    }

    public function setCodeFournisseurAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['code_fournisseur'] = null;
            return;
        }
        $this->attributes['code_fournisseur'] = mb_strtoupper(
            preg_replace('/\s+/', '', trim((string) $value)),
            'UTF-8'
        );
    }

    public function setDescriptionAttribute($value): void
    {
        $this->attributes['description'] = $this->normalizeText($value);
    }

    public function setPrixUsineAttribute($value): void
    {
        $this->attributes['prix_usine'] = $this->normalizeNonNegativeInteger($value);
    }

    public function setPrixVenteAttribute($value): void
    {
        $this->attributes['prix_vente'] = $this->normalizeNonNegativeInteger($value);
    }

    public function setPrixAchatAttribute($value): void
    {
        $this->attributes['prix_achat'] = $this->normalizeNonNegativeInteger($value);
    }

    public function setCoutAttribute($value): void
    {
        $this->attributes['cout'] = $this->normalizeNonNegativeInteger($value);
    }

    public function setImageUrlAttribute($value): void
    {
        $this->attributes['image_url'] = $this->normalizeText($value, false);
    }

    public function setTypeAttribute($value): void
    {
        if ($value instanceof ProduitType) {
            $this->attributes['type'] = $value->value;
            return;
        }

        if (is_string($value)) {
            $this->attributes['type'] = strtolower(trim($value));
            return;
        }

        $this->attributes['type'] = $value;
    }

    public function setStatutAttribute($value): void
    {
        if ($value instanceof ProduitStatut) {
            $this->attributes['statut'] = $value->value;
            return;
        }

        if (is_string($value)) {
            $this->attributes['statut'] = strtolower(trim($value));
            return;
        }

        $this->attributes['statut'] = $value;
    }

    private function normalizeText($value, bool $collapseSpaces = true): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($collapseSpaces) {
            $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        }

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNonNegativeInteger($value, bool $nullable = true): mixed
    {
        if ($value === null || $value === '') {
            return $nullable ? null : 0;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+$/', $value))) {
            return max(0, (int) $value);
        }

        return $nullable ? null : 0;
    }

    // ========================================
    // BOOT / OBSERVERS
    // ========================================

    protected static function booted(): void
    {
        static::creating(function (Produit $produit) {
            if (Auth::check() && !$produit->created_by) {
                $produit->created_by = Auth::id();
            }
            $produit->updated_by = Auth::id();
        });

        static::updating(function (Produit $produit) {
            if (Auth::check()) {
                $produit->updated_by = Auth::id();
            }

            if ($produit->isDirty('statut')) {
                if ($produit->statut === ProduitStatut::ARCHIVE && !$produit->archived_at) {
                    $produit->archived_at = now();
                    $produit->archived_by = Auth::id();
                } elseif ($produit->getOriginal('statut') === ProduitStatut::ARCHIVE->value && $produit->statut !== ProduitStatut::ARCHIVE) {
                    $produit->archived_at = null;
                    $produit->archived_by = null;
                }
            }
        });

        static::deleting(function (Produit $produit) {
            if (Auth::check()) {
                $produit->deleted_by = Auth::id();
                $produit->saveQuietly();
            }
        });
    }

    // ========================================
    // ACCESSEURS CALCULÉS
    // ========================================

    private function isAllSitesMode(): bool
    {
        return app(SiteContext::class)->isAllSites();
    }

    /**
     * Stock actuel.
     * - Mode usine précise : délègue à stockCourant.
     * - Mode all-usines    : somme sur toutes les usines via la relation stocks.
     */
    public function getQteStockAttribute(): int
    {
        if ($this->isAllSitesMode()) {
            if ($this->relationLoaded('stocks')) {
                return (int) $this->stocks->sum('qte_stock');
            }
            return (int) $this->stocks()->sum('qte_stock');
        }

        return $this->stockCourant?->qte_stock ?? 0;
    }

    /**
     * Seuil d'alerte pour l'usine courante (non pertinent en mode all-usines).
     */
    public function getSeuilAlerteStockAttribute(): ?int
    {
        if ($this->isAllSitesMode()) {
            return null;
        }

        return $this->stockCourant?->seuil_alerte_stock;
    }

    public function getInStockAttribute(): bool
    {
        if ($this->type === ProduitType::SERVICE) {
            return true;
        }

        if ($this->isAllSitesMode()) {
            if ($this->relationLoaded('stocks')) {
                return $this->stocks->sum('qte_stock') > 0;
            }
            return $this->stocks()->sum('qte_stock') > 0;
        }

        return ($this->stockCourant?->qte_stock ?? 0) > 0;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        if ($this->type === ProduitType::SERVICE) {
            return false;
        }

        if ($this->isAllSitesMode()) {
            if ($this->relationLoaded('stocks')) {
                return $this->stocks->sum('qte_stock') <= 0;
            }
            return $this->stocks()->sum('qte_stock') <= 0;
        }

        return ($this->stockCourant?->qte_stock ?? 0) <= 0;
    }

    public function getIsLowStockAttribute(): bool
    {
        if ($this->type === ProduitType::SERVICE || $this->is_out_of_stock) {
            return false;
        }

        // En mode all-usines, le seuil n'est pas défini par usine — non évaluable
        if ($this->isAllSitesMode()) {
            return false;
        }

        $stock = $this->stockCourant;
        if (!$stock) {
            return false;
        }

        $seuil = $stock->low_stock_threshold;

        return $seuil > 0 && $stock->qte_stock <= $seuil;
    }

    /**
     * Seuil effectif : personnalisé si renseigné, sinon paramètre global.
     */
    public function getLowStockThresholdAttribute(): int
    {
        if ($this->isAllSitesMode()) {
            return Parametre::getSeuilStockFaible();
        }

        return $this->stockCourant?->low_stock_threshold ?? Parametre::getSeuilStockFaible();
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->statut === ProduitStatut::ARCHIVE;
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->statut === ProduitStatut::ACTIF && $this->in_stock;
    }

    // ========================================
    // RELATIONS
    // ========================================

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

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

    /**
     * Tous les stocks de ce produit (toutes usines).
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * Stock pour l'usine du contexte courant.
     */
    public function stockCourant(): HasOne
    {
        $siteId = app(SiteContext::class)->getCurrentSiteId();

        return $this->hasOne(Stock::class)->where('site_id', $siteId);
    }

    /**
     * Toutes les configurations locales (produit_usines) pour ce produit.
     */
    public function produitSites(): HasMany
    {
        return $this->hasMany(ProduitSite::class);
    }

    /**
     * Configuration locale pour le site du contexte courant.
     */
    public function produitSiteCourant(): HasOne
    {
        $siteId = app(SiteContext::class)->getCurrentSiteId();

        return $this->hasOne(ProduitSite::class)->where('site_id', $siteId);
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeActifs($query)
    {
        return $query->where('statut', ProduitStatut::ACTIF);
    }

    /**
     * Produits en rupture de stock pour l'usine courante.
     */
    public function scopeRuptureStock($query)
    {
        return $query->where('type', '!=', ProduitType::SERVICE)
            ->whereHas('stockCourant', fn ($sq) => $sq->where('qte_stock', '<=', 0));
    }

    public function scopeArchives($query)
    {
        return $query->where('statut', ProduitStatut::ARCHIVE);
    }

    public function scopeNonArchives($query)
    {
        return $query->where('statut', '!=', ProduitStatut::ARCHIVE);
    }

    /**
     * Produits disponibles backoffice : actifs avec stock > 0 (ou services).
     */
    public function scopeDisponibles($query)
    {
        return $query->where(function ($q) {
            $q->where('statut', ProduitStatut::ACTIF)
              ->where(function ($sub) {
                  $sub->where('type', ProduitType::SERVICE)
                      ->orWhereHas('stockCourant', fn ($sq) => $sq->where('qte_stock', '>', 0));
              });
        });
    }

    /**
     * Produits visibles par une usine (ont une config produit_usines pour cette usine).
     */
    public function scopeVisiblePourUsine(Builder $query, int $siteId): Builder
    {
        return $query->whereHas('produitSites', fn ($q) => $q->where('site_id', $siteId));
    }

    /**
     * Produits activés dans un site (config locale is_active = true ET statut global = actif).
     */
    public function scopeActifDansUsine(Builder $query, int $siteId): Builder
    {
        return $query->where('statut', ProduitStatut::ACTIF)
            ->whereHas('produitSites', fn ($q) => $q->where('site_id', $siteId)->where('is_active', true));
    }

    /**
     * Produits disponibles au POS d'un site :
     * actifs globalement + activés localement + en stock (sauf services).
     */
    public function scopeDisponiblesPOS(Builder $query, int $siteId): Builder
    {
        return $query->where('statut', ProduitStatut::ACTIF)
            ->whereHas('produitSites', fn ($q) => $q->where('site_id', $siteId)->where('is_active', true))
            ->where(function (Builder $q) use ($siteId) {
                $q->where('type', ProduitType::SERVICE)
                  ->orWhereHas('stocks', fn ($sq) => $sq->where('site_id', $siteId)->where('qte_stock', '>', 0));
            });
    }

    public function scopeDeType($query, ProduitType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeDeStatut($query, ProduitStatut $statut)
    {
        return $query->where('statut', $statut);
    }

    public function scopeBrouillons($query)
    {
        return $query->where('statut', ProduitStatut::BROUILLON);
    }

    // ========================================
    // MÉTHODES MÉTIER
    // ========================================

    public function changerStatut(ProduitStatut $nouveauStatut): bool
    {
        if (!$this->statut->canTransitionTo($nouveauStatut)) {
            return false;
        }

        $this->statut = $nouveauStatut;
        return $this->save();
    }

    public function archiver(): bool
    {
        return $this->changerStatut(ProduitStatut::ARCHIVE);
    }

    public function desarchiver(ProduitStatut $nouveauStatut = ProduitStatut::INACTIF): bool
    {
        if ($this->statut !== ProduitStatut::ARCHIVE) {
            return false;
        }

        return $this->changerStatut($nouveauStatut);
    }

    /**
     * Ajuste le stock de l'usine courante via la table stocks.
     */
    public function ajusterStock(int $quantite): bool
    {
        if ($this->type === ProduitType::SERVICE) {
            return false;
        }

        $stock = $this->stockCourant;
        if (!$stock) {
            return false;
        }

        $stock->ajuster($quantite);
        $this->setRelation('stockCourant', $stock->fresh());

        return true;
    }

    /**
     * Retourne les prix effectifs pour une usine donnée.
     * Si un prix local est défini dans produit_usines, il prend le dessus sur le prix global.
     */
    public function prixEffectifDansUsine(?int $siteId): array
    {
        $local = $siteId
            ? $this->produitSites()->where('site_id', $siteId)->first()
            : null;

        return [
            'prix_usine' => $local?->prix_usine ?? $this->attributes['prix_usine'] ?? null,
            'prix_achat' => $local?->prix_achat ?? $this->attributes['prix_achat'] ?? null,
            'prix_vente' => $local?->prix_vente ?? $this->attributes['prix_vente'] ?? null,
            'cout'       => $local?->cout       ?? $this->attributes['cout']       ?? null,
            'tva'        => $local?->tva,
        ];
    }

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
