<?php

declare(strict_types=1);

namespace App\Support;

final class PlayerFullNameSql
{
    public static function expression(string $tableAlias = ''): string
    {
        $prefix = $tableAlias !== '' ? $tableAlias.'.' : '';

        return "CONCAT_WS(' ', "
            ."NULLIF(TRIM({$prefix}first_name), ''), "
            ."NULLIF(TRIM({$prefix}middle_name), ''), "
            ."NULLIF(TRIM({$prefix}last_name), ''))";
    }
}
