<?php

namespace App\Models\Traits;

use App\Services\UsineContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait HasUsineScope
 *
 * Ajoute automatiquement :
 *  1) Un global scope qui filtre les requêtes par usine_id si un contexte est défini.
 *  2) Un listener `creating` qui auto-remplit usine_id à la création.
 *
 * Comportement :
 *  - Siège sans X-Usine-Id  → UsineContext::hasContext() === false → pas de filtre → vue consolidée
 *  - Siège avec X-Usine-Id  → filtre sur cette usine
 *  - Non-siège              → toujours filtré sur son usine par défaut (middleware impose le contexte)
 */
trait HasUsineScope
{
    public static function bootHasUsineScope(): void
    {
        // ── Global scope ─────────────────────────────────────────────────────
        static::addGlobalScope('usine', function (Builder $builder) {
            /** @var UsineContext $ctx */
            $ctx = app(UsineContext::class);

            if ($ctx->hasContext()) {
                $table = (new static())->getTable();
                $builder->where("{$table}.usine_id", $ctx->getCurrentUsineId());
            }
        });

        // ── Auto-remplissage usine_id à la création ───────────────────────
        static::creating(function ($model) {
            if (empty($model->usine_id)) {
                /** @var UsineContext $ctx */
                $ctx = app(UsineContext::class);
                $model->usine_id = $ctx->getCurrentUsineId();
            }
        });
    }

    /**
     * Retirer temporairement le scope usine pour une requête donnée.
     * Utile pour les vérifications d'unicité cross-usine ou les backfills.
     */
    public static function withoutUsineScope(): Builder
    {
        return static::withoutGlobalScope('usine');
    }
}
