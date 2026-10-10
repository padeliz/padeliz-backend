<?php

declare(strict_types=1);

namespace App\Services\StudentProof;

use App\Enums\PlayerLevel\AssessmentReviewStatus;
use App\Models\Player;
use App\Models\PlayerStudentProof;
use App\Models\PlayerStudentProofFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * A player owns at most one pending student proof (generated unique index) holding 1-3 files. The
 * student benefit applies while any approved proof still has `student_until >= today`.
 */
final class StudentProofService
{
    public function getPending(int $playerId): ?PlayerStudentProof
    {
        return PlayerStudentProof::query()
            ->where('player_id', $playerId)
            ->where('review_status', AssessmentReviewStatus::Pending->value)
            ->first();
    }

    /**
     * Creates the pending proof with its first 1-3 files. Fails while the player already has a
     * pending proof (edit it through the update/file methods instead).
     *
     * @param  list<UploadedFile>  $files
     */
    public function createPending(Player $player, array $files, string $studentUntil): PlayerStudentProof
    {
        if ($this->getPending((int) $player->id_player) !== null) {
            throw ValidationException::withMessages([
                'student_proof' => ['A pending student proof already exists. Update it or discard it first.'],
            ]);
        }

        $count = count($files);
        if ($count < 1 || $count > PlayerStudentProof::MAX_FILES) {
            throw ValidationException::withMessages([
                'files' => ['Provide between 1 and ' . PlayerStudentProof::MAX_FILES . ' files.'],
            ]);
        }

        $disk = (string) config('filesystems.user_uploads_disk', 'public');
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($player, $files, $studentUntil, &$storedPaths): PlayerStudentProof {
                $proof = PlayerStudentProof::query()->create([
                    'player_id' => $player->id_player,
                    'student_until' => $studentUntil,
                    'sort_order' => 0,
                    'ready_for_review' => false,
                    'review_status' => AssessmentReviewStatus::Pending,
                ]);

                foreach (array_values($files) as $index => $file) {
                    $path = $this->store($proof, $file);
                    $storedPaths[] = $path;

                    $proof->files()->create([
                        'student_proof_path' => $path,
                        'sort_order' => $index,
                    ]);
                }

                return $proof;
            });
        } catch (\Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }

            throw $e;
        }
    }

    public function updateStudentUntil(PlayerStudentProof $proof, string $studentUntil): PlayerStudentProof
    {
        $this->assertPending($proof);
        $this->assertEditable($proof);

        $proof->student_until = $studentUntil;
        $proof->save();

        return $proof;
    }

    public function addFile(PlayerStudentProof $proof, UploadedFile $file): PlayerStudentProofFile
    {
        $this->assertPending($proof);
        $this->assertEditable($proof);

        return DB::transaction(function () use ($proof, $file): PlayerStudentProofFile {
            // Serialize concurrent appends on the parent row so the cap cannot be exceeded.
            PlayerStudentProof::query()->whereKey($proof->getKey())->lockForUpdate()->first();

            $existing = $proof->files()->get();

            if ($existing->count() >= PlayerStudentProof::MAX_FILES) {
                throw ValidationException::withMessages([
                    'file' => ['A student proof can hold at most ' . PlayerStudentProof::MAX_FILES . ' files. Remove one first.'],
                ]);
            }

            $path = $this->store($proof, $file);

            try {
                return $proof->files()->create([
                    'student_proof_path' => $path,
                    'sort_order' => $existing->isEmpty() ? 0 : ((int) $existing->max('sort_order')) + 1,
                ]);
            } catch (\Throwable $e) {
                Storage::disk($proof->files()->getRelated()->storageDisk())->delete($path);

                throw $e;
            }
        });
    }

    public function removeFile(PlayerStudentProof $proof, PlayerStudentProofFile $file): void
    {
        $this->assertPending($proof);
        $this->assertEditable($proof);

        if ((int) $file->student_proof_id !== (int) $proof->getKey()) {
            throw ValidationException::withMessages([
                'file' => ['The file does not belong to this student proof.'],
            ]);
        }

        $file->delete();
    }

    public function markReady(PlayerStudentProof $proof): PlayerStudentProof
    {
        $this->assertPending($proof);

        if (! $proof->files()->exists()) {
            throw ValidationException::withMessages([
                'files' => ['Add at least one proof file before marking the student proof ready for review.'],
            ]);
        }

        $proof->ready_for_review = true;
        $proof->submitted_at = time();
        $proof->save();

        return $proof;
    }

    public function reopenPending(PlayerStudentProof $proof): PlayerStudentProof
    {
        $this->assertPending($proof);

        $proof->ready_for_review = false;
        $proof->save();

        return $proof;
    }

    public function discardPending(PlayerStudentProof $proof): PlayerStudentProof
    {
        $this->assertPending($proof);

        return $this->close($proof, AssessmentReviewStatus::Discarded, null);
    }

    public function approve(PlayerStudentProof $proof, ?string $message = null, ?string $studentUntil = null): PlayerStudentProof
    {
        $this->assertPending($proof);

        if ($studentUntil !== null) {
            $proof->student_until = $studentUntil;
        }

        return $this->close($proof, AssessmentReviewStatus::Approved, $message);
    }

    public function reject(PlayerStudentProof $proof, ?string $message = null): PlayerStudentProof
    {
        $this->assertPending($proof);

        return $this->close($proof, AssessmentReviewStatus::Rejected, $message);
    }

    private function store(PlayerStudentProof $proof, UploadedFile $file): string
    {
        $stored = Storage::disk(config('filesystems.user_uploads_disk', 'public'))->putFile(
            'player-student-proofs/' . $proof->getKey() . '/student-proof',
            $file,
            ['visibility' => 'public']
        );

        if ($stored === false || $stored === null) {
            throw new \RuntimeException('Could not store the uploaded file.');
        }

        return $stored;
    }

    private function close(PlayerStudentProof $proof, AssessmentReviewStatus $status, ?string $message): PlayerStudentProof
    {
        $proof->review_status = $status;
        $proof->reviewed_at = time();
        if ($message !== null) {
            $proof->review_message = $message;
        }
        $proof->save();

        return $proof;
    }

    private function assertPending(PlayerStudentProof $proof): void
    {
        if ($proof->review_status !== AssessmentReviewStatus::Pending) {
            throw ValidationException::withMessages([
                'review_status' => ['This student proof has already been reviewed.'],
            ]);
        }
    }

    private function assertEditable(PlayerStudentProof $proof): void
    {
        if ($proof->ready_for_review) {
            throw ValidationException::withMessages([
                'ready_for_review' => ['The student proof is ready for review. Reopen it before making changes.'],
            ]);
        }
    }
}
