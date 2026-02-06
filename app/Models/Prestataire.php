<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prestataire extends Model
{
    use HasFactory, SoftDeletes;

    /* =========================
       TYPES DE PRESTATAIRE
       ========================= */

    public const TYPE_MACHINISTE = 'machiniste';
    public const TYPE_MECANICIEN = 'mecanicien';
    public const TYPE_CONSULTANT = 'consultant';
    public const TYPE_FOURNISSEUR = 'fournisseur';

    public const TYPES = [
        self::TYPE_MACHINISTE => 'Machiniste',
        self::TYPE_MECANICIEN => 'Mécanicien',
        self::TYPE_CONSULTANT => 'Consultant',
        self::TYPE_FOURNISSEUR => 'Fournisseur',
    ];

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
        'specialite',
        'type',
        'tarif_horaire',
        'notes',
        'reference',
        'is_active',
    ];

    protected $appends = [
        'nom_complet',
        'type_label',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'tarif_horaire' => 'integer',
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
        static::creating(function ($prestataire) {
            if (empty($prestataire->reference)) {
                $lastId = self::withTrashed()->max('id') ?? 0;
                $prestataire->reference = 'PREST-' . now()->format('Ymd') . '-' . str_pad(
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

    public function scopeParSpecialite($query, string $specialite)
    {
        return $query->where('specialite', 'like', "%{$specialite}%");
    }

    public function scopeParType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeMachinistes($query)
    {
        return $query->where('type', self::TYPE_MACHINISTE);
    }

    /* =========================
       RELATIONS
       ========================= */

    public function packings(): HasMany
    {
        return $this->hasMany(Packing::class);
    }

    /* =========================
       HELPERS
       ========================= */

    public function getTypeLabelAttribute(): string
    {
        if (!$this->type) {
            return '';
        }
        return self::TYPES[$this->type] ?? $this->type;
    }

    public static function getTypes(): array
    {
        return self::TYPES;
    }
}
