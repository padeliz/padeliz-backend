<?php

declare(strict_types=1);

namespace App\Integrations\RankedinBpt;

enum BptCanonicalDivision: string
{
    case Division1Men = 'Division 1 Men';
    case Division2Men = 'Division 2 Men';
    case Division3Men = 'Division 3 Men';
    case Division4Men = 'Division 4 Men';
    case Division1Women = 'Division 1 Women';
    case Division2Women = 'Division 2 Women';
    case Division3Women = 'Division 3 Women';

    public static function all(): array
    {
        return self::cases();
    }
}
