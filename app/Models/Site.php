<?php

namespace App\Models;

use App\Enums\SiteRole;
use App\Enums\SiteStatut;
use App\Enums\SiteType;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sites';

    protected $fillable = [
        'nom',
        'code',
        'type',
        'statut',
        'subscription_status',
        'localisation',
        'pays',
        'ville',
        'quartier',
        'description',
        'parent_id',
        'organisation_id',
    ];

    protected function casts(): array
    {
        return [
            'type'                => SiteType::class,
            'statut'              => SiteStatut::class,
            'subscription_status' => SubscriptionStatus::class,
        ];
    }

    protected $appends = ['type_label', 'statut_label'];

    // ── Boot ─────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Site $site) {
            if (empty($site->statut)) {
                $site->statut = SiteStatut::ACTIVE;
            }
        });

        static::created(function (Site $site) {
            // Auto-créer les entrées stock (qte=0) et config locale pour tous les produits globaux
            $produitGlobaux = Produit::withoutGlobalScopes()
                ->where('is_global', true)
                ->get();

            foreach ($produitGlobaux as $produit) {
                Stock::firstOrCreate(
                    ['produit_id' => $produit->id, 'site_id' => $site->id],
                    ['qte_stock' => 0, 'seuil_alerte_stock' => null]
                );

                ProduitSite::firstOrCreate(
                    ['produit_id' => $produit->id, 'site_id' => $site->id],
                    ['is_active' => false]
                );
            }
        });
    }

    // ── Accesseurs ───────────────────────────────────────────────────────

    public function getTypeLabelAttribute(): string
    {
        return $this->type instanceof SiteType ? $this->type->label() : '';
    }

    public function getStatutLabelAttribute(): string
    {
        return $this->statut instanceof SiteStatut ? $this->statut->label() : '';
    }

    // ── Relations ────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(Site::class, 'parent_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_sites')
            ->withPivot(['role', 'is_default'])
            ->withTimestamps();
    }

    public function userSites(): HasMany
    {
        return $this->hasMany(UserSite::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeActives($query)
    {
        return $query->where('statut', SiteStatut::ACTIVE);
    }

    public function scopeDuType($query, SiteType $type)
    {
        return $query->where('type', $type);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function isSiege(): bool
    {
        return $this->type === SiteType::SIEGE;
    }

    public function isActive(): bool
    {
        return $this->statut === SiteStatut::ACTIVE;
    }
}
