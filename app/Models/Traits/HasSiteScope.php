<?php

namespace App\Models\Traits;

use App\Services\SiteContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait HasSiteScope
 *
 * Ajoute automatiquement :
 *  1) Un global scope qui filtre les requêtes par site_id si un contexte est défini.
 *     Exception : les produits globaux (is_global = true) sont toujours visibles.
 *  2) Un listener `creating` qui auto-remplit site_id à la création.
 *
 * Comportement :
 *  - Siège sans X-Site-Id  → SiteContext::hasContext() === false → pas de filtre → vue consolidée
 *  - Siège avec X-Site-Id  → filtre sur ce site (+ produits globaux si la table a is_global)
 *  - Non-siège             → toujours filtré sur son site par défaut (middleware impose le contexte)
 */
trait HasSiteScope
{
    public static function bootHasSiteScope(): void
    {
        // ── Global scope ─────────────────────────────────────────────────────
        static::addGlobalScope('site', function (Builder $builder) {
            /** @var SiteContext $ctx */
            $ctx = app(SiteContext::class);

            if (!$ctx->hasContext()) {
                return;
            }

            // Mode "tous les sites" : aucun filtre par site (vue consolidée siège)
            if ($ctx->isAllSites()) {
                return;
            }

            $table   = (new static())->getTable();
            $columns = \Schema::getColumnListing($table);

            if (in_array('is_global', $columns)) {
                // Produits globaux visibles par tous les sites
                $builder->where(function (Builder $q) use ($table, $ctx) {
                    $q->where("{$table}.is_global", true)
                      ->orWhere("{$table}.site_id", $ctx->getCurrentSiteId());
                });
            } else {
                $builder->where("{$table}.site_id", $ctx->getCurrentSiteId());
            }
        });

        // ── Auto-remplissage site_id à la création ────────────────────────
        static::creating(function ($model) {
            // Ne pas auto-remplir pour les produits globaux (site_id = null intentionnel)
            if (isset($model->is_global) && $model->is_global) {
                return;
            }

            if (empty($model->site_id)) {
                /** @var SiteContext $ctx */
                $ctx = app(SiteContext::class);

                // En mode all-sites, pas d'auto-remplissage (vue consolidée, lecture seule)
                if ($ctx->isAllSites()) {
                    return;
                }

                $model->site_id = $ctx->getCurrentSiteId();
            }
        });
    }

    /**
     * Retirer temporairement le scope site pour une requête donnée.
     * Utile pour les vérifications d'unicité cross-site ou les backfills.
     */
    public static function withoutSiteScope(): Builder
    {
        return static::withoutGlobalScope('site');
    }
}
