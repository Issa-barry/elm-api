<?php

namespace App\Models;

use App\Enums\CibleDeduction;
use App\Enums\TypeDeduction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionCommission extends Model
{
    use HasFactory;

    protected $table = 'deductions_commissions';

    protected $fillable = [
        'sortie_vehicule_id',
        'cible',
        'type_deduction',
        'montant',
        'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'cible'          => CibleDeduction::class,
            'type_deduction' => TypeDeduction::class,
            'montant'        => 'decimal:2',
        ];
    }

    public function sortie(): BelongsTo
    {
        return $this->belongsTo(SortieVehicule::class, 'sortie_vehicule_id');
    }
}
