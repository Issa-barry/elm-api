<?php

namespace App\Services;

/**
 * Contexte usine pour la requête en cours.
 *
 * Enregistré comme singleton dans AppServiceProvider.
 * Le middleware ResolveUsineContext le peuple à partir du header X-Usine-Id
 * ou de l'usine par défaut de l'utilisateur.
 *
 * Modes :
 *  - currentUsineId défini, allUsines = false  → filtre sur une usine précise (HasUsineScope actif)
 *  - currentUsineId null,   allUsines = true   → vue consolidée toutes usines (HasUsineScope désactivé)
 *  - currentUsineId null,   allUsines = false  → pas de contexte (aucun filtre, legacy)
 */
class UsineContext
{
    private ?int $currentUsineId = null;
    private bool $allUsines      = false;

    public function setCurrentUsineId(?int $id): void
    {
        $this->currentUsineId = $id;
        $this->allUsines      = false;
    }

    /**
     * Bascule en mode "toutes les usines" (vue consolidée siège).
     * Le filtre usine dans HasUsineScope est désactivé ;
     * les accesseurs de stock retournent la somme sur toutes les usines.
     */
    public function setAllUsines(): void
    {
        $this->currentUsineId = null;
        $this->allUsines      = true;
    }

    public function getCurrentUsineId(): ?int
    {
        return $this->currentUsineId;
    }

    public function isAllUsines(): bool
    {
        return $this->allUsines;
    }

    /**
     * True si un contexte est actif (usine précise OU mode all-usines).
     * HasUsineScope l'utilise pour décider s'il doit filtrer.
     */
    public function hasContext(): bool
    {
        return $this->currentUsineId !== null || $this->allUsines;
    }

    public function clear(): void
    {
        $this->currentUsineId = null;
        $this->allUsines      = false;
    }
}
