<?php

namespace App\Enums;

enum BillingEventStatus: string
{
    case PENDING   = 'pending';
    case INVOICED  = 'invoiced';
    case PAID      = 'paid';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::PENDING   => 'En attente',
            self::INVOICED  => 'Facturé',
            self::PAID      => 'Payé',
            self::CANCELLED => 'Annulé',
        };
    }
}
