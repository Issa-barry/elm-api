<?php

namespace App\Enums;

enum UsineType: string
{
    case SIEGE = 'siege';
    case USINE = 'usine';

    public function label(): string
    {
        return match ($this) {
            self::SIEGE => 'Siège',
            self::USINE => 'Usine',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn ($c) => $c->label(), self::cases())
        );
    }
}
