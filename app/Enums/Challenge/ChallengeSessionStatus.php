<?php

declare(strict_types=1);

namespace App\Enums\Challenge;

enum ChallengeSessionStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
}
