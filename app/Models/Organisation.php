<?php

namespace App\Models;

use App\Enums\OrganisationStatut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organisation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'code',
        'email',
        'phone',
        'pays',
        'ville',
        'quartier',
        'adresse',
        'description',
        'statut',
        'forfait_id',
    ];

    protected function casts(): array
    {
        return [
            'statut' => OrganisationStatut::class,
        ];
    }

    // ── Boot ─────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Organisation $org) {
            if (empty($org->statut)) {
                $org->statut = OrganisationStatut::ACTIVE;
            }
        });
    }

    // ── Relations ────────────────────────────────────────────────────────

    /** Forfait souscrit par cette organisation */
    public function forfait(): BelongsTo
    {
        return $this->belongsTo(Forfait::class);
    }

    /** Sites opérationnels rattachés à cette organisation */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /** Utilisateurs appartenant à cette organisation */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeActives($query)
    {
        return $query->where('statut', OrganisationStatut::ACTIVE);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->statut === OrganisationStatut::ACTIVE;
    }
}
