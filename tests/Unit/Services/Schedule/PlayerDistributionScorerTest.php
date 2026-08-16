<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\PlayerDistributionScorer;
use PHPUnit\Framework\TestCase;

class PlayerDistributionScorerTest extends TestCase
{
    public function test_single_match_scores_one(): void
    {
        $scorer = new PlayerDistributionScorer();
        $matches = [
            0 => [
                0 => [[1, 2], [3, 4]],
            ],
        ];
        $this->assertSame(1.0, $scorer->score(1, $matches));
    }

    public function test_classify_thresholds(): void
    {
        $scorer = new PlayerDistributionScorer();
        $this->assertSame('excellent', $scorer->classify(0.90)['css_class']);
        $this->assertSame('good', $scorer->classify(0.80)['css_class']);
        $this->assertSame('fair', $scorer->classify(0.70)['css_class']);
        $this->assertSame('poor', $scorer->classify(0.69)['css_class']);
        $this->assertSame(90, $scorer->classify(0.90)['percentage']);
        $this->assertSame(89, $scorer->classify(0.894)['percentage']);
    }

    public function test_score_all_and_order_affects_distribution(): void
    {
        $scorer = new PlayerDistributionScorer();
        // Player 1 in rounds 0 and 1 (clustered) vs 0 and 3 (spread) over 4 rounds
        $clustered = [
            0 => [
                0 => [[1, 2], [3, 4]],
                1 => [[1, 3], [2, 4]],
                2 => [[5, 6], [7, 8]],
                3 => [[5, 7], [6, 8]],
            ],
        ];
        $spread = [
            0 => [
                0 => [[1, 2], [3, 4]],
                1 => [[5, 6], [7, 8]],
                2 => [[5, 7], [6, 8]],
                3 => [[1, 3], [2, 4]],
            ],
        ];

        $clusterScore = $scorer->score(1, $clustered);
        $spreadScore = $scorer->score(1, $spread);
        $this->assertGreaterThan($clusterScore, $spreadScore);

        $all = $scorer->scoreAll([1, 2, 3, 4], $spread);
        $this->assertArrayHasKey('per_player', $all);
        $this->assertArrayHasKey(1, $all['per_player']);
        $this->assertArrayHasKey('css_class', $scorer->classify($spreadScore));
    }

    public function test_rounds_total_uses_first_court_only(): void
    {
        $scorer = new PlayerDistributionScorer();
        // Court 0 has 2 rounds; court 1 has 4 — MiniTour uses first-court length (2).
        $matches = [
            0 => [
                0 => [[1, 2], [3, 4]],
                1 => [[1, 3], [2, 4]],
            ],
            1 => [
                0 => [[5, 6], [7, 8]],
                1 => [[5, 7], [6, 8]],
                2 => [[5, 8], [6, 7]],
                3 => [[5, 6], [7, 8]],
            ],
        ];

        // Player 1 only appears in court 0 rounds 0–1; with first-court roundsTotal=2,
        // both slots are filled → score 1.0. Max-across-courts would use 4 and penalize.
        $this->assertSame(1.0, $scorer->score(1, $matches));
    }
}
