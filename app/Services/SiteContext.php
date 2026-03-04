<?php

namespace App\Services;

/**
 * Contexte site pour la requête en cours.
 *
 * Enregistré comme singleton dans AppServiceProvider.
 * Le middleware ResolveSiteContext le peuple à partir du header X-Site-Id
 * ou du site par défaut de l'utilisateur.
 *
 * Modes :
 *  - currentSiteId défini, allSites = false  → filtre sur un site précis (HasSiteScope actif)
 *  - currentSiteId null,   allSites = true   → vue consolidée tous sites (HasSiteScope désactivé)
 *  - currentSiteId null,   allSites = false  → pas de contexte (aucun filtre, legacy)
 */
class SiteContext
{
    private ?int $currentSiteId = null;
    private bool $allSites      = false;

    public function setCurrentSiteId(?int $id): void
    {
        $this->currentSiteId = $id;
        $this->allSites      = false;
    }

    /**
     * Bascule en mode "tous les sites" (vue consolidée siège).
     * Le filtre site dans HasSiteScope est désactivé ;
     * les accesseurs de stock retournent la somme sur tous les sites.
     */
    public function setAllSites(): void
    {
        $this->currentSiteId = null;
        $this->allSites      = true;
    }

    public function getCurrentSiteId(): ?int
    {
        return $this->currentSiteId;
    }

    public function isAllSites(): bool
    {
        return $this->allSites;
    }

    /**
     * True si un contexte est actif (site précis OU mode all-sites).
     * HasSiteScope l'utilise pour décider s'il doit filtrer.
     */
    public function hasContext(): bool
    {
        return $this->currentSiteId !== null || $this->allSites;
    }

    public function clear(): void
    {
        $this->currentSiteId = null;
        $this->allSites      = false;
    }
}
