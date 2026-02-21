<?php

namespace App\Services;

/**
 * Contexte usine pour la requête en cours.
 *
 * Enregistré comme singleton dans AppServiceProvider.
 * Le middleware ResolveUsineContext le peuple à partir du header X-Usine-Id
 * ou de l'usine par défaut de l'utilisateur.
 *
 * - Si currentUsineId est null  → pas de filtre usine (vue consolidée siège)
 * - Si currentUsineId est défini → filtre automatique via HasUsineScope
 */
class UsineContext
{
    private ?int $currentUsineId = null;

    public function setCurrentUsineId(?int $id): void
    {
        $this->currentUsineId = $id;
    }

    public function getCurrentUsineId(): ?int
    {
        return $this->currentUsineId;
    }

    public function hasContext(): bool
    {
        return $this->currentUsineId !== null;
    }

    public function clear(): void
    {
        $this->currentUsineId = null;
    }
}
