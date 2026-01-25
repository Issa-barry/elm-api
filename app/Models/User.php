<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

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
        'password',
    ];

    /**
     * Champs cachés
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /* =========================
       FORMATAGE AUTOMATIQUE
       ========================= */

    public function setNomAttribute($value)
    {
        $this->attributes['nom'] = strtoupper(trim($value));
    }

    public function setPrenomAttribute($value)
    {
        $this->attributes['prenom'] = ucfirst(strtolower(trim($value)));
    }

    /* =========================
       RÉFÉRENCE AUTO
       ========================= */

    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->reference)) {
                $user->reference = 'USR-' . now()->format('Ymd') . '-' . str_pad(
                    (self::max('id') + 1),
                    4,
                    '0',
                    STR_PAD_LEFT
                );
            }
        });
    }
}
