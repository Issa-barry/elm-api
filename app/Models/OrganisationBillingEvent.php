<?php

namespace App\Models;

use App\Enums\BillingEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationBillingEvent extends Model
{
    protected $fillable = [
        'organisation_id',
        'user_id',
        'event_type',
        'unit_price',
        'quantity',
        'amount',
        'status',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status'      => BillingEventStatus::class,
            'unit_price'  => 'decimal:2',
            'amount'      => 'decimal:2',
            'quantity'    => 'integer',
            'occurred_at' => 'datetime',
            'metadata'    => 'array',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeForOrganisation($query, int $organisationId)
    {
        return $query->where('organisation_id', $organisationId);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
