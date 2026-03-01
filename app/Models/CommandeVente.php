<?php

namespace App\Models;

use App\Models\Traits\HasUsineScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommandeVente extends Model
{
    use HasFactory, SoftDeletes, HasUsineScope;

    protected $table = 'commandes_ventes';

    protected $fillable = [
        'usine_id',
        'vehicule_id',
        'reference',
        'total_commande',
    ];

    protected function casts(): array
    {
        return [
            'total_commande' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CommandeVente $commande) {
            if (empty($commande->reference)) {
                $lastId = self::withTrashed()->max('id') ?? 0;
                $commande->reference = 'VNT-' . now()->format('Ymd') . '-' . str_pad(
                    $lastId + 1,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
            }
        });
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function usine(): BelongsTo
    {
        return $this->belongsTo(Usine::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(CommandeVenteLigne::class, 'commande_vente_id');
    }

    public function facture(): HasOne
    {
        return $this->hasOne(FactureVente::class, 'commande_vente_id');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(CommissionVente::class, 'commande_vente_id');
    }
}
