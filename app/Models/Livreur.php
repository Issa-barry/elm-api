<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Livreur extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'prenom',
        'phone',
        'email',
        'pays',
        'ville',
        'quartier',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ── Mutateurs : formatage automatique à l'écriture ───────────────────

    public function setNomAttribute($value): void
    {
        $this->attributes['nom'] = mb_strtoupper(trim($value), 'UTF-8');
    }

    public function setPrenomAttribute($value): void
    {
        $this->attributes['prenom'] = mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8');
    }

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = $value !== null ? mb_strtolower(trim($value), 'UTF-8') : null;
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = preg_replace('/[^0-9+]/', '', (string) $value);
    }

    public function setPaysAttribute($value): void
    {
        $this->attributes['pays'] = $value !== null ? mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8') : null;
    }

    public function setVilleAttribute($value): void
    {
        $this->attributes['ville'] = $value !== null ? mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8') : null;
    }

    public function setQuartierAttribute($value): void
    {
        $this->attributes['quartier'] = $value !== null ? mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8') : null;
    }

    // ── Relations ─────────────────────────────────────────────────────────

    public function vehiculesPrincipaux(): HasMany
    {
        return $this->hasMany(Vehicule::class, 'livreur_principal_id');
    }

    public function sorties(): HasMany
    {
        return $this->hasMany(SortieVehicule::class, 'livreur_id_effectif');
    }
}
