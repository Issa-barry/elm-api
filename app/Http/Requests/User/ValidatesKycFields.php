<?php

namespace App\Http\Requests\User;

use App\Enums\PieceType;
use Illuminate\Validation\Rule;

/**
 * Trait partagé entre StoreUserRequest et UpdateUserRequest
 * pour la validation conditionnelle du bloc KYC (pièce d'identité).
 *
 * Stratégie A (par défaut) : piece_expire_le >= today (strict).
 * Passer KYC_STRICT_EXPIRY=false dans .env pour activer la stratégie B (souple).
 */
trait ValidatesKycFields
{
    // ──────────────────────────────────────────
    //  Champs KYC concernés
    // ──────────────────────────────────────────

    private function kycFields(): array
    {
        return [
            'piece_type',
            'piece_numero',
            'piece_pays',
            'piece_delivree_le',
            'piece_expire_le',
            'piece_fichier',
            'piece_fichier_verso',
        ];
    }

    // ──────────────────────────────────────────
    //  Nettoyage des inputs KYC
    // ──────────────────────────────────────────

    protected function prepareKycFields(): void
    {
        $normalized = [];

        foreach ($this->kycFields() as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);

            // trim + '' → null
            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }

            // Normaliser piece_type en lowercase
            if ($field === 'piece_type' && is_string($value)) {
                $value = strtolower($value);
            }

            $normalized[$field] = $value;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    // ──────────────────────────────────────────
    //  Détection : un champ KYC quelconque est présent ?
    // ──────────────────────────────────────────

    private function hasAnyKycField(): bool
    {
        foreach ($this->kycFields() as $field) {
            if ($field === 'piece_type') {
                continue;
            }
            if ($this->filled($field)) {
                return true;
            }
        }

        return false;
    }

    // ──────────────────────────────────────────
    //  Règles KYC
    // ──────────────────────────────────────────

    protected function kycRules(): array
    {
        $kycActive = $this->filled('piece_type') || $this->hasAnyKycField();
        $strictExpiry = config('kyc.strict_expiry', true);

        // --- piece_type ---
        $pieceTypeRules = [
            Rule::requiredIf($this->hasAnyKycField()),
            'nullable',
            'string',
            Rule::in(PieceType::values()),
        ];

        // --- piece_numero ---
        $pieceNumeroRules = $kycActive
            ? ['required', 'string', 'min:3', 'max:100']
            : ['nullable', 'string', 'min:3', 'max:100'];

        // --- piece_pays ---
        $piecePaysRules = $kycActive
            ? ['required', 'string', 'min:2', 'max:5']
            : ['nullable', 'string', 'min:2', 'max:5'];

        // --- piece_delivree_le ---
        $delivreeRules = $kycActive
            ? ['required', 'date', 'before_or_equal:today']
            : ['nullable', 'date', 'before_or_equal:today'];

        // --- piece_expire_le ---
        $expireRules = $kycActive
            ? ['required', 'date', 'after:piece_delivree_le']
            : ['nullable', 'date', 'after:piece_delivree_le'];

        if ($strictExpiry) {
            $expireRules[] = 'after_or_equal:today';
        }

        // --- fichiers (toujours optionnels) ---
        $fichierRules = ['nullable', 'string', 'max:500'];

        return [
            'piece_type'           => $pieceTypeRules,
            'piece_numero'         => $pieceNumeroRules,
            'piece_pays'           => $piecePaysRules,
            'piece_delivree_le'    => $delivreeRules,
            'piece_expire_le'      => $expireRules,
            'piece_fichier'        => $fichierRules,
            'piece_fichier_verso'  => $fichierRules,
        ];
    }

    // ──────────────────────────────────────────
    //  Messages FR pour le bloc KYC
    // ──────────────────────────────────────────

    protected function kycMessages(): array
    {
        return [
            'piece_type.required'    => 'Le type de pièce est obligatoire lorsqu\'un champ KYC est renseigné.',
            'piece_type.in'          => 'Le type de pièce doit être : ' . implode(', ', PieceType::values()) . '.',

            'piece_numero.required'  => 'Le numéro de pièce est obligatoire lorsque le type de pièce est renseigné.',
            'piece_numero.min'       => 'Le numéro de pièce doit contenir au moins 3 caractères.',
            'piece_numero.max'       => 'Le numéro de pièce ne peut pas dépasser 100 caractères.',

            'piece_pays.required'    => 'Le pays émetteur est obligatoire lorsque le type de pièce est renseigné.',
            'piece_pays.min'         => 'Le code pays émetteur doit contenir au moins 2 caractères.',
            'piece_pays.max'         => 'Le code pays émetteur ne peut pas dépasser 5 caractères.',

            'piece_delivree_le.required'       => 'La date de délivrance est obligatoire lorsque le type de pièce est renseigné.',
            'piece_delivree_le.date'           => 'La date de délivrance doit être une date valide.',
            'piece_delivree_le.before_or_equal' => 'La date de délivrance ne peut pas être dans le futur.',

            'piece_expire_le.required'         => 'La date d\'expiration est obligatoire lorsque le type de pièce est renseigné.',
            'piece_expire_le.date'             => 'La date d\'expiration doit être une date valide.',
            'piece_expire_le.after'            => 'La date d\'expiration doit être postérieure à la date de délivrance.',
            'piece_expire_le.after_or_equal'   => 'La date d\'expiration ne peut pas être dans le passé (pièce expirée).',
        ];
    }
}
