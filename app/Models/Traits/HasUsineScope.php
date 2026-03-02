<?php

namespace App\Models\Traits;

use App\Services\UsineContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait HasUsineScope
 *
 * Ajoute automatiquement :
 *  1) Un global scope qui filtre les requêtes par usine_id si un contexte est défini.
 *     Exception : les produits globaux (is_global = true) sont toujours visibles.
 *  2) Un listener `creating` qui auto-remplit usine_id à la création.
 *
 * Comportement :
 *  - Siège sans X-Usine-Id  → UsineContext::hasContext() === false → pas de filtre → vue consolidée
 *  - Siège avec X-Usine-Id  → filtre sur cette usine (+ produits globaux si la table a is_global)
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

            if (!$ctx->hasContext()) {
                return;
            }

            // Mode "toutes les usines" : aucun filtre par usine (vue consolidée siège)
            if ($ctx->isAllUsines()) {
                return;
            }

            $table   = (new static())->getTable();
            $columns = \Schema::getColumnListing($table);

            if (in_array('is_global', $columns)) {
                // Produits globaux visibles par toutes les usines
                $builder->where(function (Builder $q) use ($table, $ctx) {
                    $q->where("{$table}.is_global", true)
                      ->orWhere("{$table}.usine_id", $ctx->getCurrentUsineId());
                });
            } else {
                $builder->where("{$table}.usine_id", $ctx->getCurrentUsineId());
            }
        });

        // ── Auto-remplissage usine_id à la création ───────────────────────
        static::creating(function ($model) {
            // Ne pas auto-remplir pour les produits globaux (usine_id = null intentionnel)
            if (isset($model->is_global) && $model->is_global) {
                return;
            }

            if (empty($model->usine_id)) {
                /** @var UsineContext $ctx */
                $ctx = app(UsineContext::class);

                // En mode all-usines, pas d'auto-remplissage (vue consolidée, lecture seule)
                if ($ctx->isAllUsines()) {
                    return;
                }

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
