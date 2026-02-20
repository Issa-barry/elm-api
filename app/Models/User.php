<?php

namespace App\Models;

use App\Enums\Civilite;
use App\Enums\PieceType;
use App\Enums\UsineRole;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, HasRoles;

    /**
     * Champs autorisés en mass assignment
     */
    protected $fillable = [
        'civilite',
        'nom',
        'prenom',
        'date_naissance',
        'phone',
        'email',
        'pays',
        'code_pays',
        'code_phone_pays',
        'ville',
        'quartier',
        'adresse',
        'reference',
        'type',
        'language',
        'default_usine_id',
        'password',
        'is_active',
        'piece_type',
        'piece_numero',
        'piece_delivree_le',
        'piece_expire_le',
        'piece_pays',
        'piece_fichier',
        'piece_fichier_verso',
        'activated_at',
        'last_login_at',
        'last_seen_at',
        'last_login_ip',
    ];

    /**
     * Champs cachés
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Champs en append
     */
    protected $appends = [
        'nom_complet',
        'role_names',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'activated_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'type' => UserType::class,
            'civilite' => Civilite::class,
            'date_naissance' => 'date:Y-m-d',
            'piece_type' => PieceType::class,
            'piece_delivree_le' => 'date',
            'piece_expire_le' => 'date',
        ];
    }

    /* =========================
       FORMATAGE AUTOMATIQUE
       ========================= */

    public function setNomAttribute($value): void
    {
        $this->attributes['nom'] = mb_strtoupper(trim($value), 'UTF-8');
    }

    public function setPrenomAttribute($value): void
    {
        $this->attributes['prenom'] = mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8');
    }

    public function setPhoneAttribute($value): void
    {
        // Nettoyer le numéro de téléphone
        $this->attributes['phone'] = preg_replace('/[^0-9+]/', '', $value);
    }

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = $value ? strtolower(trim($value)) : null;
    }

    public function setVilleAttribute($value): void
    {
        $this->attributes['ville'] = $this->normalizeLocation($value);
    }

    public function setQuartierAttribute($value): void
    {
        $this->attributes['quartier'] = $this->normalizeLocation($value);
    }

    /* =========================
       ACCESSEURS
       ========================= */

    public function getNomCompletAttribute(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function getRoleNamesAttribute(): array
    {
        return $this->getRoleNames()->toArray();
    }

    /* =========================
       RÉFÉRENCE AUTO-GÉNÉRÉE
       ========================= */

    protected static function booted(): void
    {
        static::creating(function ($user) {
            if (empty($user->reference)) {
                $lastId = self::withTrashed()->max('id') ?? 0;
                $user->reference = 'USR-' . now()->format('Ymd') . '-' . str_pad(
                    $lastId + 1,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
            }
        });
    }

    /* =========================
       TYPE DE COMPTE
       ========================= */

    public function isStaff(): bool
    {
        return $this->type === UserType::STAFF;
    }

    public function isClient(): bool
    {
        return $this->type === UserType::CLIENT;
    }

    public function isPrestataire(): bool
    {
        return $this->type === UserType::PRESTATAIRE;
    }

    public function isInvestisseur(): bool
    {
        return $this->type === UserType::INVESTISSEUR;
    }

    /* =========================
       RELATIONS USINES
       ========================= */

    /** Toutes les usines auxquelles cet utilisateur est affecté */
    public function usines(): BelongsToMany
    {
        return $this->belongsToMany(Usine::class, 'user_usines')
            ->withPivot(['role', 'is_default'])
            ->withTimestamps()
            ->using(UserUsine::class);
    }

    /** Entrées pivot user ↔ usine */
    public function userUsines(): HasMany
    {
        return $this->hasMany(UserUsine::class);
    }

    /** Usine par défaut de cet utilisateur */
    public function defaultUsine(): BelongsTo
    {
        return $this->belongsTo(Usine::class, 'default_usine_id');
    }

    /* =========================
       ACCÈS USINE
       ========================= */

    /**
     * L'utilisateur a-t-il un rôle siège sur au moins une usine de type SIEGE ?
     * Résultat mis en cache sur l'instance pour éviter les requêtes répétées.
     */
    public function isSiege(): bool
    {
        return $this->usines()
            ->where('usines.type', 'siege')
            ->whereIn('user_usines.role', UsineRole::siegeRoles())
            ->exists();
    }

    /**
     * L'utilisateur a-t-il accès à l'usine donnée (est-il affecté) ?
     */
    public function hasUsineAccess(int $usineId): bool
    {
        return $this->usines()->where('usines.id', $usineId)->exists();
    }

    /* =========================
       MÉTHODES UTILITAIRES
       ========================= */

    public function updateLastLogin(?string $ip = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip ?? request()->ip(),
        ]);
    }

    public function isEmailVerified(): bool
    {
        return !is_null($this->email_verified_at);
    }

    public function markEmailAsVerified(): void
    {
        $this->forceFill([
            'email_verified_at' => now(),
        ])->save();
    }

    private function normalizeLocation($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
    }
}
