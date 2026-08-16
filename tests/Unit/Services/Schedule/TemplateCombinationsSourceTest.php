<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Schedule;

use App\Services\Schedule\TemplateCombinations;
use App\Services\Schedule\TemplateMatchesGenerator;
use PHPUnit\Framework\TestCase;

final class TemplateCombinationsSourceTest extends TestCase
{
    public function test_generator_combinations_alias_template_combinations_matrix(): void
    {
        $this->assertSame(
            TemplateCombinations::COMBINATIONS,
            TemplateMatchesGenerator::COMBINATIONS
        );
    }

    public function test_generator_stop_reason_constants_alias_template_combinations(): void
    {
        $this->assertSame(
            TemplateCombinations::STOP_REASON_TRIVIAL,
            TemplateMatchesGenerator::STOP_REASON_TRIVIAL
        );
        $this->assertSame(
            TemplateCombinations::STOP_REASON_DEADLINE,
            TemplateMatchesGenerator::STOP_REASON_DEADLINE
        );
        $this->assertSame(
            TemplateCombinations::STOP_REASON_FACTORIAL_COMPLETE,
            TemplateMatchesGenerator::STOP_REASON_FACTORIAL_COMPLETE
        );
        $this->assertSame(
            TemplateCombinations::STOP_REASON_PRUNE_INFEASIBLE,
            TemplateMatchesGenerator::STOP_REASON_PRUNE_INFEASIBLE
        );
        $this->assertSame(
            TemplateCombinations::STOP_REASONS,
            TemplateMatchesGenerator::STOP_REASONS
        );
    }
}
