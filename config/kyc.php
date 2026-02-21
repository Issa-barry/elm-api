<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation stricte de l'expiration de la pièce d'identité
    |--------------------------------------------------------------------------
    |
    | true  (A - strict)  : piece_expire_le >= today — refuse les pièces expirées
    | false (B - souple)  : piece_expire_le peut être dans le passé,
    |                        le blocage se fait plus tard dans le workflow KYC
    |
    */
    'strict_expiry' => (bool) env('KYC_STRICT_EXPIRY', true),

];
