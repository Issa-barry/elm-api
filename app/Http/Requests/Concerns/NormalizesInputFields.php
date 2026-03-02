<?php

namespace App\Http\Requests\Concerns;

/**
 * Normalisation systématique des données reçues avant validation.
 *
 * À utiliser dans prepareForValidation() de chaque FormRequest.
 */
trait NormalizesInputFields
{
    /**
     * Trim + lowercase. Retourne null si vide.
     * Usage : email, role, type, statut, enums en général.
     */
    protected function normalizeEmail(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim((string) $value);
        return $v === '' ? null : strtolower($v);
    }

    /**
     * Supprime tout caractère autre que chiffres et '+'.
     * Retourne null si vide après nettoyage.
     * Usage : numéros de téléphone basiques (non E.164).
     */
    protected function normalizePhone(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = preg_replace('/[^0-9+]/', '', (string) $value);
        return ($v === null || $v === '') ? null : $v;
    }

    /**
     * Trim + collapse des espaces multiples + Title Case UTF-8.
     * Retourne null si vide.
     * Usage : ville, quartier, pays, noms de lieux.
     */
    protected function normalizeLocation(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = preg_replace('/\s+/u', ' ', trim((string) $value));
        return $v === '' ? null : mb_convert_case($v, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Trim + lowercase. Retourne null si vide.
     * Usage : role, type_compte, enums côté frontend.
     */
    protected function normalizeLowercase(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim((string) $value);
        return $v === '' ? null : strtolower($v);
    }

    /**
     * Trim + collapse des espaces multiples. Retourne null si vide.
     * Usage : nom, prenom, raison_sociale, champs texte libres.
     */
    protected function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = preg_replace('/\s+/u', ' ', trim((string) $value));
        return $v === '' ? null : $v;
    }
}
