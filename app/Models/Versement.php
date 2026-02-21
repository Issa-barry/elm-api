<?php

namespace App\Models;

use App\Models\Traits\HasUsineScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Versement extends Model
{
    use HasFactory, SoftDeletes, HasUsineScope;

    /* =========================
       MODES DE PAIEMENT
       ========================= */

    public const MODE_ESPECES = 'especes';
    public const MODE_VIREMENT = 'virement';
    public const MODE_CHEQUE = 'cheque';
    public const MODE_MOBILE_MONEY = 'mobile_money';

    public const MODES_PAIEMENT = [
        self::MODE_ESPECES => 'Espèces',
        self::MODE_VIREMENT => 'Virement bancaire',
        self::MODE_CHEQUE => 'Chèque',
        self::MODE_MOBILE_MONEY => 'Mobile Money',
    ];

    protected $fillable = [
        'usine_id',
        'reference',
        'facture_packing_id',
        'montant',
        'date_versement',
        'mode_paiement',
        'notes',
        'created_by',
    ];

    protected $appends = [
        'mode_paiement_label',
    ];

    protected function casts(): array
    {
        return [
            'date_versement' => 'date:Y-m-d',
            'montant' => 'integer',
        ];
    }

    /* =========================
       FORMATAGE AUTOMATIQUE
       ========================= */

    public function setNotesAttribute($value): void
    {
        $this->attributes['notes'] = $value ? trim($value) : null;
    }

    /* =========================
       BOOT / OBSERVERS
       ========================= */

    protected static function booted(): void
    {
        static::creating(function ($versement) {
            if (empty($versement->reference)) {
                $lastId = self::withTrashed()->max('id') ?? 0;
                $versement->reference = 'VERS-' . now()->format('Ymd') . '-' . str_pad(
                    $lastId + 1,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
            }

            if (Auth::check() && !$versement->created_by) {
                $versement->created_by = Auth::id();
            }
        });
    }

    /* =========================
       RELATIONS
       ========================= */

    public function facturePacking(): BelongsTo
    {
        return $this->belongsTo(FacturePacking::class, 'facture_packing_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* =========================
       ACCESSEURS
       ========================= */

    public function getModePaiementLabelAttribute(): string
    {
        return self::MODES_PAIEMENT[$this->mode_paiement] ?? $this->mode_paiement;
    }

    /* =========================
       MÉTHODES STATIQUES
       ========================= */

    public static function getModesPaiement(): array
    {
        return self::MODES_PAIEMENT;
    }
}
