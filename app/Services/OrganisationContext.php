<?php

namespace App\Services;

/**
 * Contexte organisation par requête (singleton).
 *
 * Utilisé par le middleware ResolveOrganisationContext pour
 * propager l'organisation courante à travers la pile applicative.
 *
 * Pattern miroir de SiteContext, niveau tenant supérieur.
 */
class OrganisationContext
{
    private ?int $currentOrganisationId = null;

    public function setCurrentOrganisationId(?int $id): void
    {
        $this->currentOrganisationId = $id;
    }

    public function getCurrentOrganisationId(): ?int
    {
        return $this->currentOrganisationId;
    }

    public function hasContext(): bool
    {
        return $this->currentOrganisationId !== null;
    }

    public function clear(): void
    {
        $this->currentOrganisationId = null;
    }
}
