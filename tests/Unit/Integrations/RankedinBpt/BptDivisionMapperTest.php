<?php

declare(strict_types=1);

namespace Tests\Unit\Integrations\RankedinBpt;

use App\Integrations\RankedinBpt\BptCanonicalDivision;
use App\Integrations\RankedinBpt\BptDivisionMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BptDivisionMapperTest extends TestCase
{
    private BptDivisionMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new BptDivisionMapper();
    }

    public function test_taxonomy_from_load_ranking_info_builds_lookups(): void
    {
        $taxonomy = $this->mapper->taxonomyFromLoadRankingInfo([
            'DoublesAgeGroups' => [
                ['AgeGroupID' => 82, 'AgeGroupText' => 'Men-Main'],
                ['AgeGroupID' => 83, 'AgeGroupText' => 'Women-Main'],
            ],
            'RankingAgeGroupPair' => [
                ['RankingTypeID' => 3, 'RankingTypeText' => 'Men-Doubles'],
                ['RankingTypeID' => 4, 'RankingTypeText' => 'Women-Doubles'],
            ],
        ]);

        self::assertSame('Men-Main', $taxonomy['ageGroupTexts']['82']);
        self::assertSame('Women-Main', $taxonomy['ageGroupTexts']['83']);
        self::assertSame('Men-Doubles', $taxonomy['rankingTypeTexts']['3']);
        self::assertSame('Women-Doubles', $taxonomy['rankingTypeTexts']['4']);
    }

    public function test_taxonomy_handles_missing_sections(): void
    {
        $taxonomy = $this->mapper->taxonomyFromLoadRankingInfo([]);

        self::assertSame([], $taxonomy['ageGroupTexts']);
        self::assertSame([], $taxonomy['rankingTypeTexts']);
    }

    #[DataProvider('mapsMenMainDoublesProvider')]
    public function test_maps_men_main_doubles_divisions(string $className, int $expectedDivision): void
    {
        $result = $this->mapper->map($className, 82, 3);
        self::assertNotNull($result);
        $expected = match ($expectedDivision) {
            1 => BptCanonicalDivision::Division1Men,
            2 => BptCanonicalDivision::Division2Men,
            3 => BptCanonicalDivision::Division3Men,
            4 => BptCanonicalDivision::Division4Men,
            default => null,
        };
        self::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function mapsMenMainDoublesProvider(): iterable
    {
        yield 'div word digit' => ['Men Division 1', 1];
        yield 'divizia' => ['Divizia 2 Masculin', 2];
        yield 'd prefix' => ['Men D3', 3];
        yield 'ordinal fourth' => ['Men 4th division', 4];
        yield 'romanian fourth' => ['Al patrulea barbati', 4];
        yield 'no gender in name metadata men' => ['Division 1', 1];
    }

    #[DataProvider('mapsWomenMainDoublesProvider')]
    public function test_maps_women_main_doubles_divisions(string $className, int $expectedDivision): void
    {
        $result = $this->mapper->map($className, 83, 4);
        self::assertNotNull($result);
        $expected = match ($expectedDivision) {
            1 => BptCanonicalDivision::Division1Women,
            2 => BptCanonicalDivision::Division2Women,
            3 => BptCanonicalDivision::Division3Women,
            default => null,
        };
        self::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function mapsWomenMainDoublesProvider(): iterable
    {
        yield 'women division' => ['Women Division 1', 1];
        yield 'ladies' => ['Ladies 2nd division', 2];
        yield 'feminin' => ['Divizia 3 Feminin', 3];
    }

    public function test_returns_null_for_mixed_match_type_even_with_men_age_group(): void
    {
        self::assertNull($this->mapper->map('Men Division 1', 82, 5));
    }

    public function test_returns_null_when_class_name_contains_mixed_token(): void
    {
        self::assertNull($this->mapper->map('Mixed Division 1', 82, 3));
    }

    public function test_returns_null_for_men_non_main_age_group(): void
    {
        self::assertNull($this->mapper->map('Men Division 1', 81, 3));
    }

    public function test_returns_null_for_women_non_main_age_group(): void
    {
        self::assertNull($this->mapper->map('Women Division 1', 84, 4));
    }

    public function test_returns_null_for_unknown_match_type(): void
    {
        self::assertNull($this->mapper->map('Men Division 1', 82, 99));
    }

    public function test_returns_null_when_division_number_missing(): void
    {
        self::assertNull($this->mapper->map('Men Open', 82, 3));
    }

    public function test_returns_null_when_division_number_out_of_range(): void
    {
        self::assertNull($this->mapper->map('Men Division 5', 82, 3));
    }

    public function test_returns_null_when_name_says_women_but_results_are_men_doubles(): void
    {
        self::assertNull($this->mapper->map('Women Division 1', 82, 3));
    }

    public function test_returns_null_when_name_says_men_but_results_are_women_doubles(): void
    {
        self::assertNull($this->mapper->map('Men Division 1', 83, 4));
    }
}
