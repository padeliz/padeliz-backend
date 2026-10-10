<?php

declare(strict_types=1);

namespace App\Services\PlayerLevel;

final class LevelBand
{
    public function __construct(
        public readonly string $name,
        public readonly string $levelMin,
        public readonly string $levelMax,
    ) {
    }

    /**
     * @return list<self>
     */
    public static function catalog(): array
    {
        $bands = [];
        for ($n = 5; $n >= 1; $n--) {
            $bands[] = new self(sprintf('Division %d Low', $n), sprintf('%d.99', $n), sprintf('%d.67', $n));
            $bands[] = new self(sprintf('Division %d Mid', $n), sprintf('%d.66', $n), sprintf('%d.34', $n));
            $bands[] = new self(sprintf('Division %d High', $n), sprintf('%d.33', $n), sprintf('%d.00', $n));
        }

        return $bands;
    }

    public function midpoint(): string
    {
        $mid = ((float) $this->levelMin + (float) $this->levelMax) / 2;

        return number_format($mid, 2, '.', '');
    }

    public function contains(float|string $level): bool
    {
        $value = (float) $level;

        return $value <= (float) $this->levelMin && $value >= (float) $this->levelMax;
    }

    public static function findContaining(float|string $level): ?self
    {
        foreach (self::catalog() as $band) {
            if ($band->contains($level)) {
                return $band;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> division name => band name
     */
    public static function divisionAttachments(): array
    {
        return [
            'Rookies' => 'Division 5 Low',
            'Beginners' => 'Division 5 Mid',
            'Learners' => 'Division 5 High',
            'Novice' => 'Division 4 Low',
            'Explorers' => 'Division 4 Mid',
            'Advancers' => 'Division 4 High',
            'Challengers' => 'Division 3 Low',
        ];
    }
}
