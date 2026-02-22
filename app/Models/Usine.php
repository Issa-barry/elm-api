<?php

namespace App\Models;

use App\Enums\UsineRole;
use App\Enums\UsineStatut;
use App\Enums\UsineType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Usine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'code',
        'type',
        'statut',
        'localisation',
        'pays',
        'ville',
        'quartier',
        'description',
        'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'type'   => UsineType::class,
            'statut' => UsineStatut::class,
        ];
    }

    protected $appends = ['type_label', 'statut_label'];

    // ── Boot ─────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Usine $usine) {
            if (empty($usine->statut)) {
                $usine->statut = UsineStatut::ACTIVE;
            }
        });
    }

    // ── Accesseurs ───────────────────────────────────────────────────────

    public function getTypeLabelAttribute(): string
    {
        return $this->type instanceof UsineType ? $this->type->label() : '';
    }

    public function getStatutLabelAttribute(): string
    {
        return $this->statut instanceof UsineStatut ? $this->statut->label() : '';
    }

    // ── Relations ────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Usine::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(Usine::class, 'parent_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_usines')
            ->withPivot(['role', 'is_default'])
            ->withTimestamps();
    }

    public function userUsines(): HasMany
    {
        return $this->hasMany(UserUsine::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeActives($query)
    {
        return $query->where('statut', UsineStatut::ACTIVE);
    }

    public function scopeDuType($query, UsineType $type)
    {
        return $query->where('type', $type);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function isSiege(): bool
    {
        return $this->type === UsineType::SIEGE;
    }

    public function isActive(): bool
    {
        return $this->statut === UsineStatut::ACTIVE;
    }
}
