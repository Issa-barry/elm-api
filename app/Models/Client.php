<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'prenom',
        'raison_sociale',
        'phone',
        'email',
        'pays',
        'code_pays',
        'code_phone_pays',
        'ville',
        'quartier',
        'adresse',
        'notes',
        'reference',
        'is_active',
    ];

    protected $appends = [
        'nom_complet',
    ];

    protected function casts(): array
    {
        return [
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
        static::creating(function ($client) {
            if (empty($client->reference)) {
                $lastId = self::withTrashed()->max('id') ?? 0;
                $client->reference = 'CLI-' . now()->format('Ymd') . '-' . str_pad(
                    $lastId + 1,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
            }
        });
    }

    /* =========================
       SCOPES
       ========================= */

    public function scopeActifs($query)
    {
        return $query->where('is_active', true);
    }
}
