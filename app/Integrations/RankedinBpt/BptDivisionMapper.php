<?php

declare(strict_types=1);

namespace App\Integrations\RankedinBpt;

/**
 * Maps RankedIn tournament class name + results metadata to one of seven BPT divisions.
 * Men: MatchType 3 + AgeGroup 82 (Men-Main). Women: MatchType 4 + AgeGroup 83 (Women-Main). Mixed excluded.
 */
final class BptDivisionMapper
{
    private const MATCH_TYPE_MEN_DOUBLES = 3;

    private const MATCH_TYPE_WOMEN_DOUBLES = 4;

    private const MATCH_TYPE_MIXED = 5;

    /**
     * @param  array<string, string>  $ageGroupTexts  ageGroupId (string) => AgeGroupText
     * @param  array<string, string>  $rankingTypeTexts  rankingTypeId (string) => RankingTypeText
     */
    public function map(
        string $className,
        int $ageGroupId,
        int $matchTypeId,
    ): ?BptCanonicalDivision {
        if ($matchTypeId === self::MATCH_TYPE_MIXED) {
            return null;
        }

        if ($matchTypeId === self::MATCH_TYPE_MEN_DOUBLES && $ageGroupId !== 82) {
            return null;
        }

        if ($matchTypeId === self::MATCH_TYPE_WOMEN_DOUBLES && $ageGroupId !== 83) {
            return null;
        }

        if ($matchTypeId !== self::MATCH_TYPE_MEN_DOUBLES && $matchTypeId !== self::MATCH_TYPE_WOMEN_DOUBLES) {
            return null;
        }

        $divisionNumber = $this->extractDivisionNumber($className);
        if ($divisionNumber === null) {
            return null;
        }

        $genderFromName = $this->inferGenderFromName($className);
        if ($genderFromName === 'mixed') {
            return null;
        }

        $isMen = $matchTypeId === self::MATCH_TYPE_MEN_DOUBLES;
        $isWomen = $matchTypeId === self::MATCH_TYPE_WOMEN_DOUBLES;

        if ($genderFromName === 'men' && ! $isMen) {
            return null;
        }

        if ($genderFromName === 'women' && ! $isWomen) {
            return null;
        }

        // If name contradicts metadata, trust metadata (already enforced above for strict men/women main only).

        if ($isMen) {
            return match ($divisionNumber) {
                1 => BptCanonicalDivision::Division1Men,
                2 => BptCanonicalDivision::Division2Men,
                3 => BptCanonicalDivision::Division3Men,
                4 => BptCanonicalDivision::Division4Men,
                default => null,
            };
        }

        if ($isWomen) {
            return match ($divisionNumber) {
                1 => BptCanonicalDivision::Division1Women,
                2 => BptCanonicalDivision::Division2Women,
                3 => BptCanonicalDivision::Division3Women,
                default => null,
            };
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $loadRankingInfoResponse
     * @return array{ageGroupTexts: array<string, string>, rankingTypeTexts: array<string, string>}
     */
    public function taxonomyFromLoadRankingInfo(array $loadRankingInfoResponse): array
    {
        $ageGroupTexts = [];
        $rankingTypeTexts = [];

        foreach ($loadRankingInfoResponse['DoublesAgeGroups'] ?? [] as $row) {
            if (isset($row['AgeGroupID'], $row['AgeGroupText'])) {
                $ageGroupTexts[(string) $row['AgeGroupID']] = (string) $row['AgeGroupText'];
            }
        }

        foreach ($loadRankingInfoResponse['RankingAgeGroupPair'] ?? [] as $pair) {
            if (isset($pair['RankingTypeID'], $pair['RankingTypeText'])) {
                $rankingTypeTexts[(string) $pair['RankingTypeID']] = (string) $pair['RankingTypeText'];
            }
        }

        return ['ageGroupTexts' => $ageGroupTexts, 'rankingTypeTexts' => $rankingTypeTexts];
    }

    private function inferGenderFromName(string $className): ?string
    {
        $n = $this->normalize($className);

        if (preg_match('/\b(mixed|mixt)\b/u', $n)) {
            return 'mixed';
        }

        if (preg_match('/\b(women|woman|female|feminin|ladies)\b/u', $n)) {
            return 'women';
        }

        if (preg_match('/\b(men|man|male|masculin|mens)\b/u', $n)) {
            return 'men';
        }

        return null;
    }

    private function extractDivisionNumber(string $className): ?int
    {
        $n = $this->normalize($className);

        if (preg_match('/\b(?:1st|first|i st|primul|prima)\b/u', $n)) {
            return 1;
        }
        if (preg_match('/\b(?:2nd|second|ii nd|al\s*doilea)\b/u', $n)) {
            return 2;
        }
        if (preg_match('/\b(?:3rd|third|iii rd|al\s*treilea)\b/u', $n)) {
            return 3;
        }
        if (preg_match('/\b(?:4th|fourth|iv th|al\s*patrulea)\b/u', $n)) {
            return 4;
        }

        if (preg_match('/\bdiv(?:ision|izia)?\s*([1-4])\b/u', $n, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/\b(?:^|\s)([1-4])\s*(?:st|nd|rd|th)?\s*div/u', $n, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/\bd([1-4])\b/u', $n, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return (string) $s;
    }
}
