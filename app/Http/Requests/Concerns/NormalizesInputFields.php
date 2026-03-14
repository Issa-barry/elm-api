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
     * Normalise un numéro de téléphone au format E.164 international.
     *
     * Cas gérés (avec code_phone_pays = "+33") :
     *   "0658855039"      → "+33658855039"   (format local : préfixe + suppression 0)
     *   "+330658855039"   → "+33658855039"   (déjà international mais 0 redondant)
     *   "0033658855039"   → "+33658855039"   (préfixe 00)
     *   "+33658855039"    → "+33658855039"   (déjà correct, inchangé)
     *   "658855039"       → "+33658855039"   (national sans 0 : préfixe ajouté)
     *
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

        // Normalise le préfixe 00XXXXX → +XXXXX
        if (str_starts_with($v, '00')) {
            $v = '+' . substr($v, 2);
        }

        if ($countryCode !== null) {
            $prefix = preg_replace('/[^0-9+]/', '', (string) $countryCode);
            if ($prefix === '') {
                return $v;
            }

            // Déjà international avec 0 redondant : +CC0XXXXXXX → +CCXXXXXXX
            if (str_starts_with($v, $prefix . '0')) {
                return $prefix . substr($v, strlen($prefix) + 1);
            }

            // Déjà international et correct : retourner tel quel
            if (str_starts_with($v, $prefix)) {
                return $v;
            }

            // Format local avec 0 : 0XXXXXXX → +CCXXXXXXX
            if (str_starts_with($v, '0')) {
                return $prefix . substr($v, 1);
            }

            // Nombre national sans 0 : XXXXXXX → +CCXXXXXXX
            return $prefix . $v;
        }

        // Pas de code pays fourni mais numéro déjà international (+CC0XXXXXXX).
        // Ex : front concatène +33 + 0654321987 → +330654321987 → +33654321987.
        if (str_starts_with($v, '+')) {
            $v = (string) preg_replace('/^(\+\d{1,4})0(\d{6,})$/', '$1$2', $v);
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
