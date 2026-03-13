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
        // Si vide ou ne contient pas '@', considérer comme absent (champ facultatif mal rempli)
        if ($v === '' || !str_contains($v, '@')) {
            return null;
        }
        return strtolower($v);
    }

    /**
     * Supprime tout caractère autre que chiffres et '+'.
     * Si code_phone_pays est fourni, supprime le zéro local redondant.
     * Ex: +33 + 0658855039 → +33658855039 (format E.164)
     * Retourne null si vide après nettoyage.
     */
    protected function normalizePhone(mixed $value, mixed $countryCode = null): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = preg_replace('/[^0-9+]/', '', (string) $value);
        if ($v === null || $v === '') {
            return null;
        }

        // Supprime le 0 local redondant : +33 0658... → +33658...
        if ($countryCode !== null) {
            $prefix = preg_replace('/[^0-9+]/', '', (string) $countryCode);
            if ($prefix !== '' && str_starts_with($v, $prefix . '0')) {
                $v = $prefix . substr($v, strlen($prefix) + 1);
            }
        }

        return $v;
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
