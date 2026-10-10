<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerLevel;

use App\Services\PlayerLevel\CustomFieldValueMigrator;
use PHPUnit\Framework\TestCase;

final class CustomFieldValueMigratorTest extends TestCase
{
    public function test_age_to_year_of_birth_subtracts_from_the_reference_year(): void
    {
        $this->assertSame(1996, CustomFieldValueMigrator::ageToYearOfBirth('30'));
        $this->assertSame(1996, CustomFieldValueMigrator::ageToYearOfBirth('30 ani'));
        $this->assertSame(CustomFieldValueMigrator::REFERENCE_YEAR - 18, CustomFieldValueMigrator::ageToYearOfBirth('18'));
    }

    public function test_age_to_year_of_birth_rejects_unusable_input(): void
    {
        $this->assertNull(CustomFieldValueMigrator::ageToYearOfBirth(null));
        $this->assertNull(CustomFieldValueMigrator::ageToYearOfBirth('   '));
        $this->assertNull(CustomFieldValueMigrator::ageToYearOfBirth('nu stiu'));
        $this->assertNull(CustomFieldValueMigrator::ageToYearOfBirth('0'));
        $this->assertNull(CustomFieldValueMigrator::ageToYearOfBirth('130'));
    }

    public function test_playtomic_url_to_id_extracts_the_digits(): void
    {
        $this->assertSame(
            '123456',
            CustomFieldValueMigrator::playtomicUrlToId('https://playtomic.com/profile/user/123456')
        );
        $this->assertSame(
            '987',
            CustomFieldValueMigrator::playtomicUrlToId('PLAYTOMIC.COM/profile/user/987?utm=x')
        );
    }

    public function test_playtomic_url_to_id_rejects_other_urls(): void
    {
        $this->assertNull(CustomFieldValueMigrator::playtomicUrlToId(null));
        $this->assertNull(CustomFieldValueMigrator::playtomicUrlToId(''));
        $this->assertNull(CustomFieldValueMigrator::playtomicUrlToId('https://example.test/profile/user/1'));
        $this->assertNull(CustomFieldValueMigrator::playtomicUrlToId('playtomic.com/profile/user/abc'));
    }

    public function test_build_racket_experience_is_empty_without_usable_answers(): void
    {
        $this->assertSame([], CustomFieldValueMigrator::buildRacketExperience(null, null, null));
        $this->assertSame([], CustomFieldValueMigrator::buildRacketExperience('nu', 'nu', 'nu'));
    }

    public function test_build_racket_experience_reads_confident_padel_counts(): void
    {
        $this->assertSame(
            [['sport' => 'padel', 'matches_played' => 50]],
            CustomFieldValueMigrator::buildRacketExperience('50+', null, null)
        );
        $this->assertSame(
            [['sport' => 'padel', 'matches_played' => 40]],
            CustomFieldValueMigrator::buildRacketExperience('aprox 40', null, null)
        );
    }

    public function test_build_racket_experience_skips_ambiguous_padel_counts(): void
    {
        $this->assertSame([], CustomFieldValueMigrator::buildRacketExperience('intre 10 si 20', null, null));
    }

    public function test_build_racket_experience_reads_tennis_years_and_matches(): void
    {
        $this->assertSame(
            [['sport' => 'tennis', 'years_of_playing' => 5.0]],
            CustomFieldValueMigrator::buildRacketExperience(null, 'am jucat 5 ani', null)
        );
        $this->assertSame(
            [['sport' => 'tennis', 'matches_played' => 3]],
            CustomFieldValueMigrator::buildRacketExperience(null, '3 ori', null)
        );
    }

    public function test_build_racket_experience_merges_lessons_into_existing_sports(): void
    {
        $this->assertSame(
            [
                ['sport' => 'padel', 'matches_played' => 50, 'lessons_had' => 10],
                ['sport' => 'tennis', 'years_of_playing' => 5.0],
            ],
            CustomFieldValueMigrator::buildRacketExperience('50', 'am jucat 5 ani', 'padel 10')
        );
    }

    public function test_build_racket_experience_adds_sports_known_only_from_lessons(): void
    {
        $this->assertSame(
            [['sport' => 'badminton', 'lessons_had' => 4]],
            CustomFieldValueMigrator::buildRacketExperience(null, null, 'badminton 4')
        );
    }

    public function test_build_racket_experience_ignores_lessons_without_a_sport(): void
    {
        $this->assertSame([], CustomFieldValueMigrator::buildRacketExperience(null, null, '12'));
    }
}
