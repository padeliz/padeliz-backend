<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\EditionDivision;
use App\Models\EditionDivisionPoster;
use Illuminate\Support\Facades\Storage;

/**
 * Clears generated poster files while keeping printed_path intact.
 */
final class EditionDivisionPosterInvalidator
{
    public function __construct(
        private readonly EditionPosterInvalidationGuard $guard = new EditionPosterInvalidationGuard(),
    ) {
    }

    /**
     * @param  list<string>  $kinds
     */
    public function clearGenerated(EditionDivision $editionDivision, array $kinds): int
    {
        if ($kinds === []) {
            return 0;
        }

        $editionDivision->loadMissing('edition');
        $editionId = (int) ($editionDivision->edition_id ?? $editionDivision->edition?->id_edition ?? 0);
        if ($editionId <= 0 || ! $this->guard->shouldInvalidateEditionId($editionId)) {
            return 0;
        }

        $posters = EditionDivisionPoster::query()
            ->where('edition_division_id', $editionDivision->id_edition_division)
            ->whereIn('poster_kind', $kinds)
            ->whereNotNull('generated_path')
            ->get();

        return $this->clearPosters($posters);
    }

    public function clearMatches(EditionDivision $editionDivision): int
    {
        return $this->clearGenerated($editionDivision, ['MATCHES']);
    }

    public function clearMatchesAndRanking(EditionDivision $editionDivision): int
    {
        return $this->clearGenerated($editionDivision, ['MATCHES', 'RANKING']);
    }

    public function clearAttendanceForEdition(int $editionId): int
    {
        if (! $this->guard->shouldInvalidateEditionId($editionId)) {
            return 0;
        }

        $posters = EditionDivisionPoster::query()
            ->where('edition_id', $editionId)
            ->whereNull('edition_division_id')
            ->where('poster_kind', 'ATTENDANCE')
            ->whereNotNull('generated_path')
            ->get();

        return $this->clearPosters($posters);
    }

    /**
     * @param  iterable<EditionDivisionPoster>  $posters
     */
    private function clearPosters(iterable $posters): int
    {
        $cleared = 0;
        foreach ($posters as $poster) {
            $path = $poster->generated_path;
            if ($path !== null && $path !== '') {
                Storage::disk($poster->storageDisk())->delete($path);
            }
            $poster->generated_path = null;
            $poster->save();
            $cleared++;
        }

        return $cleared;
    }
}
