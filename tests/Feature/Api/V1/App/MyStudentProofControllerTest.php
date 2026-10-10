<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Enums\PlayerLevel\AssessmentReviewStatus;
use App\Models\Player;
use App\Models\PlayerStudentProof;
use App\Models\PlayerStudentProofFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Helpers\FileUploadTestHelper;
use Tests\TestCase;

final class MyStudentProofControllerTest extends TestCase
{
    use RefreshDatabase;
    use FileUploadTestHelper;

    private const BASE = '/api/v1/app/my/student-proofs';

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $this->player = Player::factory()->create();
        Sanctum::actingAs($this->player);
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('filesystems.user_uploads_disk', 'public'));
    }

    private function until(int $days = 90): string
    {
        return Carbon::now()->addDays($days)->format('Y-m-d');
    }

    /**
     * @param  null|list<UploadedFile>  $files
     */
    private function create(string|false|null $until = null, ?array $files = null)
    {
        $payload = ['files' => $files ?? [$this->createFakeImage('card.png')]];
        if ($until !== false) {
            $payload['student_until'] = $until ?? $this->until();
        }

        return $this->post(self::BASE, $payload, ['Accept' => 'application/json']);
    }

    /**
     * @return list<UploadedFile>
     */
    private function images(int $count): array
    {
        $files = [];
        for ($i = 1; $i <= $count; $i++) {
            $files[] = $this->createFakeImage("card-{$i}.png");
        }

        return $files;
    }

    private function appendFile(?UploadedFile $file = null)
    {
        return $this->post(
            self::BASE . '/pending/files',
            ['file' => $file ?? $this->createFakeImage('extra.png')],
            ['Accept' => 'application/json']
        );
    }

    private function pending(): PlayerStudentProof
    {
        return PlayerStudentProof::query()
            ->where('player_id', $this->player->id_player)
            ->where('review_status', AssessmentReviewStatus::Pending->value)
            ->firstOrFail();
    }

    public function test_index_is_empty_by_default(): void
    {
        $this->getJson(self::BASE)
            ->assertOk()
            ->assertJsonPath('data.has_active', false)
            ->assertJsonPath('data.pending', null)
            ->assertJsonCount(0, 'data.items');
        $this->getJson(self::BASE . '/pending')->assertNotFound();
    }

    public function test_create_with_one_two_and_three_files_succeeds(): void
    {
        foreach ([1, 2, 3] as $count) {
            $until = $this->until();

            $response = $this->create($until, $this->images($count))
                ->assertCreated()
                ->assertJsonPath('data.review_status', 'pending')
                ->assertJsonPath('data.ready_for_review', false)
                ->assertJsonPath('data.student_until', $until)
                ->assertJsonCount($count, 'data.files');

            $proof = $this->pending();
            $this->assertSame($count, $proof->files()->count());
            foreach ($proof->files as $index => $file) {
                $this->assertSame($index, $file->sort_order);
                $this->disk()->assertExists($file->student_proof_path);
            }
            foreach ($response->json('data.files') as $payload) {
                $this->assertNotEmpty($payload['url']);
                $this->assertArrayHasKey('id_player_student_proof_file', $payload);
            }

            $this->postJson(self::BASE . '/discard')->assertOk();
        }
    }

    public function test_created_pending_is_exposed_by_pending_and_index(): void
    {
        $this->create(null, $this->images(2))->assertCreated();

        $this->getJson(self::BASE . '/pending')->assertOk()->assertJsonCount(2, 'data.files');
        $this->getJson(self::BASE)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonCount(2, 'data.pending.files')
            ->assertJsonCount(2, 'data.items.0.files');
    }

    public function test_create_requires_student_until_and_one_to_three_files(): void
    {
        // Missing date
        $this->create(false, $this->images(1))->assertStatus(422)->assertJsonValidationErrors('student_until');
        // Missing files entirely
        $this->post(self::BASE, ['student_until' => $this->until()], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('files');
        // Empty files array
        $this->postJson(self::BASE, ['student_until' => $this->until(), 'files' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('files');
        // More than three files
        $this->create(null, $this->images(4))->assertStatus(422)->assertJsonValidationErrors('files');
        // The legacy single `file` key is no longer a valid way to create
        $this->post(
            self::BASE,
            ['student_until' => $this->until(), 'file' => $this->createFakeImage()],
            ['Accept' => 'application/json']
        )->assertStatus(422);

        $this->assertSame(0, PlayerStudentProof::query()->count());
        $this->assertSame(0, PlayerStudentProofFile::query()->count());
    }

    public function test_create_file_and_date_validation(): void
    {
        $this->create(Carbon::now()->subDay()->format('Y-m-d'))->assertStatus(422);
        $this->create('01/02/2030')->assertStatus(422);
        $this->create($this->until(), [$this->createFakeTextFile('notes.txt')])->assertStatus(422);
        $this->create($this->until(), [$this->createFakeImage(), $this->createFakeTextFile('notes.txt')])->assertStatus(422);

        $this->assertSame(0, PlayerStudentProof::query()->count());
        $this->assertSame(0, PlayerStudentProofFile::query()->count());
    }

    public function test_pdf_upload_is_accepted(): void
    {
        $pdf = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');

        $this->create($this->until(), [$pdf])->assertCreated();
    }

    public function test_second_create_while_pending_exists_is_422_and_changes_nothing(): void
    {
        $this->create(null, $this->images(1))->assertCreated();
        $proof = $this->pending();
        $untilBefore = (string) $proof->student_until;

        $this->create($this->until(200), $this->images(2))->assertStatus(422);

        $this->assertSame(1, PlayerStudentProof::query()->count());
        $this->assertSame(1, $proof->files()->count());
        $this->assertSame($untilBefore, (string) $proof->fresh()->student_until);
    }

    public function test_second_create_is_422_even_when_pending_is_ready_for_review(): void
    {
        $this->create()->assertCreated();
        $this->postJson(self::BASE . '/pending/ready')->assertOk();

        $this->create()->assertStatus(422);
        $this->assertSame(1, PlayerStudentProof::query()->count());
    }

    public function test_patch_pending_updates_student_until_only_and_keeps_files(): void
    {
        $this->create($this->until(30), $this->images(2))->assertCreated();
        $proof = $this->pending();
        $pathsBefore = $proof->files->pluck('student_proof_path')->all();
        $idsBefore = $proof->files->pluck('id_player_student_proof_file')->all();

        $newUntil = $this->until(120);
        $this->patchJson(self::BASE . '/pending', ['student_until' => $newUntil])
            ->assertOk()
            ->assertJsonPath('data.student_until', $newUntil)
            ->assertJsonPath('data.id_player_student_proof', $proof->id_player_student_proof)
            ->assertJsonCount(2, 'data.files');

        $proof->refresh();
        $this->assertSame($newUntil, (string) $proof->student_until);
        $this->assertSame($idsBefore, $proof->files->pluck('id_player_student_proof_file')->all());
        $this->assertSame($pathsBefore, $proof->files->pluck('student_proof_path')->all());
        foreach ($pathsBefore as $path) {
            $this->disk()->assertExists($path);
        }
    }

    public function test_patch_pending_ignores_file_uploads_and_validates_date(): void
    {
        $this->create(null, $this->images(1))->assertCreated();
        $proof = $this->pending();

        // A file sent to PATCH must not replace or add anything.
        $this->patchJson(self::BASE . '/pending', [
            'student_until' => $this->until(60),
            'files' => $this->images(2),
            'file' => $this->createFakeImage(),
        ])->assertOk();
        $this->assertSame(1, $proof->files()->count());

        $this->patchJson(self::BASE . '/pending', [])->assertStatus(422)->assertJsonValidationErrors('student_until');
        $this->patchJson(self::BASE . '/pending', ['student_until' => Carbon::now()->subDay()->format('Y-m-d')])
            ->assertStatus(422);
        $this->patchJson(self::BASE . '/pending', ['student_until' => '01/02/2030'])->assertStatus(422);
    }

    public function test_patch_and_file_endpoints_return_404_without_pending(): void
    {
        $this->patchJson(self::BASE . '/pending', ['student_until' => $this->until()])->assertNotFound();
        $this->appendFile()->assertNotFound();
        $this->deleteJson(self::BASE . '/pending/files/1')->assertNotFound();
    }

    public function test_append_file_until_max_three_then_fourth_is_422(): void
    {
        $this->create()->assertCreated();
        $proof = $this->pending();

        $this->appendFile()->assertCreated()->assertJsonCount(2, 'data.files');
        $this->appendFile()->assertCreated()->assertJsonCount(3, 'data.files');
        $this->assertSame(3, $proof->files()->count());

        $this->appendFile()->assertStatus(422);
        $this->assertSame(3, $proof->files()->count());
        $this->assertSame([0, 1, 2], $proof->files->pluck('sort_order')->all());
    }

    public function test_append_file_validation(): void
    {
        $this->create()->assertCreated();

        $this->post(self::BASE . '/pending/files', [], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
        $this->appendFile($this->createFakeTextFile('notes.txt'))->assertStatus(422);

        $this->assertSame(1, $this->pending()->files()->count());
    }

    public function test_delete_file_removes_row_and_disk_file_and_allows_reappend(): void
    {
        $this->create(null, $this->images(3))->assertCreated();
        $proof = $this->pending();
        $target = $proof->files->get(1);
        $path = $target->student_proof_path;
        $this->disk()->assertExists($path);

        $this->deleteJson(self::BASE . '/pending/files/' . $target->id_player_student_proof_file)
            ->assertOk()
            ->assertJsonCount(2, 'data.files');

        $this->assertDatabaseMissing('player_student_proof_files', [
            'id_player_student_proof_file' => $target->id_player_student_proof_file,
        ]);
        $this->disk()->assertMissing($path);
        $this->assertSame(2, $proof->files()->count());

        // Max cap frees up: a new file can be appended again, but only up to three.
        $this->appendFile()->assertCreated()->assertJsonCount(3, 'data.files');
        $this->appendFile()->assertStatus(422);
    }

    public function test_delete_file_can_remove_the_last_file_and_reappend(): void
    {
        $this->create()->assertCreated();
        $file = $this->pending()->files->first();

        $this->deleteJson(self::BASE . '/pending/files/' . $file->id_player_student_proof_file)
            ->assertOk()
            ->assertJsonCount(0, 'data.files');

        $this->appendFile()->assertCreated()->assertJsonCount(1, 'data.files');
    }

    public function test_delete_unknown_or_foreign_file_is_404(): void
    {
        $this->create()->assertCreated();

        $foreign = PlayerStudentProofFile::factory()->create([
            'student_proof_id' => PlayerStudentProof::factory()->create()->id_player_student_proof,
        ]);

        $this->deleteJson(self::BASE . '/pending/files/999999')->assertNotFound();
        $this->deleteJson(self::BASE . '/pending/files/' . $foreign->id_player_student_proof_file)->assertNotFound();
        $this->assertDatabaseHas('player_student_proof_files', [
            'id_player_student_proof_file' => $foreign->id_player_student_proof_file,
        ]);
    }

    public function test_mark_ready_with_zero_files_is_422(): void
    {
        $proof = PlayerStudentProof::factory()->create(['player_id' => $this->player->id_player]);
        $this->assertSame(0, $proof->files()->count());

        $this->postJson(self::BASE . '/pending/ready')->assertStatus(422);
        $this->assertFalse($proof->fresh()->ready_for_review);
        $this->assertNull($proof->fresh()->submitted_at);
    }

    public function test_mark_ready_after_deleting_every_file_is_422(): void
    {
        $this->create()->assertCreated();
        $file = $this->pending()->files->first();
        $this->deleteJson(self::BASE . '/pending/files/' . $file->id_player_student_proof_file)->assertOk();

        $this->postJson(self::BASE . '/pending/ready')->assertStatus(422);
    }

    public function test_mark_ready_with_at_least_one_file_succeeds(): void
    {
        $this->create(null, $this->images(2))->assertCreated();

        $this->postJson(self::BASE . '/pending/ready')
            ->assertOk()
            ->assertJsonPath('data.ready_for_review', true)
            ->assertJsonCount(2, 'data.files');
        $this->assertNotNull($this->pending()->submitted_at);
    }

    public function test_patch_append_and_delete_are_locked_while_ready_and_reopen_unlocks(): void
    {
        $this->create(null, $this->images(2))->assertCreated();
        $proof = $this->pending();
        $file = $proof->files->first();
        $until = (string) $proof->student_until;
        $this->postJson(self::BASE . '/pending/ready')->assertOk();

        $this->patchJson(self::BASE . '/pending', ['student_until' => $this->until(200)])->assertStatus(422);
        $this->appendFile()->assertStatus(422);
        $this->deleteJson(self::BASE . '/pending/files/' . $file->id_player_student_proof_file)->assertStatus(422);

        $this->assertSame(2, $proof->files()->count());
        $this->assertSame($until, (string) $proof->fresh()->student_until);
        $this->disk()->assertExists($file->student_proof_path);

        $this->postJson(self::BASE . '/pending/reopen')
            ->assertOk()
            ->assertJsonPath('data.ready_for_review', false);

        $this->patchJson(self::BASE . '/pending', ['student_until' => $this->until(200)])->assertOk();
        $this->appendFile()->assertCreated();
        $this->deleteJson(self::BASE . '/pending/files/' . $file->id_player_student_proof_file)->assertOk();
        $this->assertSame(2, $proof->files()->count());
    }

    public function test_ready_and_reopen_return_404_without_pending(): void
    {
        $this->postJson(self::BASE . '/pending/ready')->assertNotFound();
        $this->postJson(self::BASE . '/pending/reopen')->assertNotFound();
    }

    public function test_discard_closes_pending_and_allows_a_new_create(): void
    {
        $this->postJson(self::BASE . '/discard')->assertOk();

        $this->create()->assertCreated();
        $this->postJson(self::BASE . '/discard')->assertOk();

        $this->getJson(self::BASE . '/pending')->assertNotFound();
        $this->assertSame(
            AssessmentReviewStatus::Discarded,
            PlayerStudentProof::query()->firstOrFail()->review_status
        );

        $this->create(null, $this->images(2))->assertCreated();
        $this->assertSame(2, PlayerStudentProof::query()->count());
    }

    public function test_discard_works_while_ready_for_review(): void
    {
        $this->create()->assertCreated();
        $this->postJson(self::BASE . '/pending/ready')->assertOk();

        $this->postJson(self::BASE . '/discard')->assertOk();
        $this->getJson(self::BASE . '/pending')->assertNotFound();
        $this->create()->assertCreated();
    }

    public function test_has_active_reflects_approved_unexpired_proofs(): void
    {
        PlayerStudentProof::factory()->expired()->withFiles()->create(['player_id' => $this->player->id_player]);
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('data.has_active', false);

        PlayerStudentProof::factory()->approved()->withFiles(2)->create(['player_id' => $this->player->id_player]);
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('data.has_active', true);
        $this->assertTrue(PlayerStudentProof::hasActiveFor($this->player));
    }

    public function test_has_active_for_is_decided_by_the_parent_regardless_of_file_count(): void
    {
        $withFiles = Player::factory()->create();
        $withoutFiles = Player::factory()->create();
        $expired = Player::factory()->create();
        $pendingOnly = Player::factory()->create();

        PlayerStudentProof::factory()->approved()->withFiles(3)->create(['player_id' => $withFiles->id_player]);
        PlayerStudentProof::factory()->approved()->create(['player_id' => $withoutFiles->id_player]);
        PlayerStudentProof::factory()->expired()->withFiles(2)->create(['player_id' => $expired->id_player]);
        PlayerStudentProof::factory()->withFiles(1)->create(['player_id' => $pendingOnly->id_player]);

        $this->assertTrue(PlayerStudentProof::hasActiveFor($withFiles));
        $this->assertTrue(PlayerStudentProof::hasActiveFor($withoutFiles));
        $this->assertFalse(PlayerStudentProof::hasActiveFor($expired));
        $this->assertFalse(PlayerStudentProof::hasActiveFor($pendingOnly));
    }

    public function test_other_players_proofs_are_not_visible_or_editable(): void
    {
        $other = PlayerStudentProof::factory()->withFiles(2)->create();
        $otherFile = $other->files->first();

        $this->getJson(self::BASE)->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson(self::BASE . '/pending')->assertNotFound();
        $this->patchJson(self::BASE . '/pending', ['student_until' => $this->until()])->assertNotFound();
        $this->deleteJson(self::BASE . '/pending/files/' . $otherFile->id_player_student_proof_file)->assertNotFound();
        $this->assertSame(2, $other->files()->count());
    }
}
