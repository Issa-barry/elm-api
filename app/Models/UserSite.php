<?php

namespace App\Models;

use App\Enums\SiteRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserSite extends Pivot
{
    protected $table = 'user_sites';

    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'site_id',
        'role',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'role'       => SiteRole::class,
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isSiegeRole(): bool
    {
        return $this->role instanceof SiteRole && $this->role->isSiegeRole();
    }
}
