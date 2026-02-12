<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * Champs autorisés en mass assignment
     */
    protected $fillable = [
        'nom',
        'prenom',
        'phone',
        'email',
        'pays',
        'code_pays',
        'code_phone_pays',
        'ville',
        'quartier',
        'reference',
        'role',
        'password',
        'is_active',
        'last_login_at',
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
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
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

    /* =========================
       ACCESSEURS
       ========================= */

    public function getNomCompletAttribute(): string
    {
        return $this->prenom . ' ' . $this->nom;
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
}