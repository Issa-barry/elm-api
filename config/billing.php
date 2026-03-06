<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Prix unitaire compte utilisateur
     |--------------------------------------------------------------------------
     |
     | Montant facturé par compte utilisateur créé.
     | Gestion manuelle (pas de Stripe/SumUp).
     | Valeur en GNF (ou devise locale configurée).
     |
     */
    'user_account_price' => (float) env('BILLING_USER_ACCOUNT_PRICE', 0),
];
