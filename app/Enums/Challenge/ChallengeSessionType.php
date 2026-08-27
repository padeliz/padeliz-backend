<?php

declare(strict_types=1);

namespace App\Enums\Challenge;

enum ChallengeSessionType: string
{
    case KingOfTheCourt = 'king_of_the_court';
    case SmashContest = 'smash_contest';
}
