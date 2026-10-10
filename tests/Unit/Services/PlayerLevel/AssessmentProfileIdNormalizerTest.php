<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerLevel;

use App\Services\PlayerLevel\AssessmentProfileIdNormalizer;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AssessmentProfileIdNormalizerTest extends TestCase
{
    public function test_playtomic_url_becomes_stored_id(): void
    {
        $out = AssessmentProfileIdNormalizer::normalize([
            'playtomic_id' => 'https://playtomic.com/profile/user/123456?utm=x',
        ]);

        $this->assertSame('123456', $out['playtomic_id']);
    }

    public function test_playtomic_bare_id_accepted(): void
    {
        $out = AssessmentProfileIdNormalizer::normalize(['playtomic_id' => '98765']);

        $this->assertSame('98765', $out['playtomic_id']);
    }

    public function test_playtomic_unparseable_url_throws_422_style_validation(): void
    {
        $this->expectException(ValidationException::class);

        AssessmentProfileIdNormalizer::normalize([
            'playtomic_id' => 'https://example.test/profile/user/1',
        ]);
    }

    public function test_rankedin_url_becomes_stored_id(): void
    {
        $out = AssessmentProfileIdNormalizer::normalize([
            'rankedin_id' => 'https://rankedin.com/player/R-abc123/',
        ]);

        $this->assertSame('R-abc123', $out['rankedin_id']);
    }

    public function test_rankedin_bare_id_accepted(): void
    {
        $out = AssessmentProfileIdNormalizer::normalize(['rankedin_id' => 'R-keep']);

        $this->assertSame('R-keep', $out['rankedin_id']);
    }

    public function test_rankedin_unparseable_url_throws(): void
    {
        $this->expectException(ValidationException::class);

        AssessmentProfileIdNormalizer::normalize([
            'rankedin_id' => 'https://rankedin.com/not-a-player-page',
        ]);
    }

    public function test_empty_values_pass_through(): void
    {
        $out = AssessmentProfileIdNormalizer::normalize([
            'playtomic_id' => '',
            'rankedin_id' => '  ',
        ]);

        $this->assertSame('', $out['playtomic_id']);
        $this->assertSame('', $out['rankedin_id']);
    }
}
