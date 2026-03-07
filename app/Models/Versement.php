<?php

namespace App\Models;

use App\Models\Traits\HasSiteScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Versement extends Model
{
    use HasFactory, SoftDeletes, HasSiteScope;

    private const TEMP_REFERENCE_PREFIX = 'TMP-VERS-';

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
        'site_id',
        'reference',
        'packing_id',
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
            'montant'        => 'integer',
            'packing_id'     => 'integer',
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
        static::creating(function (Versement $versement) {
            if (empty($versement->reference)) {
                // Placeholder unique pour passer la contrainte NOT NULL/UNIQUE avant d'avoir l'id réel.
                $versement->reference = self::TEMP_REFERENCE_PREFIX . Str::uuid();
            }

            if (Auth::check() && !$versement->created_by) {
                $versement->created_by = Auth::id();
            }
        });

        static::created(function (Versement $versement): void {
            if (!str_starts_with((string) $versement->reference, self::TEMP_REFERENCE_PREFIX)) {
                return;
            }

            $datePart       = ($versement->created_at ?? now())->format('Ymd');
            $finalReference = 'VERS-' . $datePart . '-' . str_pad((string) $versement->id, 4, '0', STR_PAD_LEFT);

            static::withoutEvents(function () use ($versement, $finalReference): void {
                $versement->newQueryWithoutScopes()
                    ->whereKey($versement->id)
                    ->update(['reference' => $finalReference]);
            });

            $versement->reference = $finalReference;
            $versement->syncOriginalAttribute('reference');
        });
    }

    /* =========================
       RELATIONS
       ========================= */

    public function packing(): BelongsTo
    {
        return $this->belongsTo(Packing::class);
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
