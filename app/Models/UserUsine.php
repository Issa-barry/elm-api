<?php

namespace App\Models;

use App\Enums\UsineRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserUsine extends Pivot
{
    protected $table = 'user_usines';

    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'usine_id',
        'role',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'role'       => UsineRole::class,
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usine(): BelongsTo
    {
        return $this->belongsTo(Usine::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isSiegeRole(): bool
    {
        return $this->role instanceof UsineRole && $this->role->isSiegeRole();
    }
}
