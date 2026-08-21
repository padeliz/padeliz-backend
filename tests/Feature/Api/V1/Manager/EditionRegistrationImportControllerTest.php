<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionRegistration;
use App\Models\Player;
use App\Models\PlayerEmail;
use App\Models\PlayerPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditionRegistrationImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Manager',
        ]);
        $this->edition = Edition::factory()->create([
            'entry_fee' => 55,
            'students_entry_fee' => 45,
            'finalized_at' => null,
        ]);
    }

    public function test_preview_requires_manager_auth(): void
    {
        $this->post($this->previewUrl(), [
            'file' => $this->csvUpload("Name,Email,Phone Number\nMara,mara@example.test,+40712\n"),
        ], ['Accept' => 'application/json'])->assertStatus(401);
    }

    public function test_preview_detects_playtomic_format(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postPreview(
            "Name,Email,Phone Number,Level\nMara Popescu,mara@example.test,+40 712 345 678,4.5\n"
        );

        $response->assertOk()
            ->assertJsonPath('data.format', 'playtomic')
            ->assertJsonPath('data.rows.0.first_name', 'Mara')
            ->assertJsonPath('data.rows.0.last_name', 'Popescu')
            ->assertJsonPath('data.rows.0.email', 'mara@example.test')
            ->assertJsonPath('data.rows.0.phone', '+40712345678')
            ->assertJsonPath('data.rows.0.already_registered', false)
            ->assertJsonPath('data.rows.0.mapping.status', 'unmapped');
    }

    public function test_preview_already_registered_true_for_null_division_or_division_registration(): void
    {
        Sanctum::actingAs($this->manager);

        $nullDivisionPlayer = Player::factory()->create([
            'first_name' => 'Mara',
            'last_name' => 'Popescu',
        ]);
        $this->setContacts($nullDivisionPlayer, 'mara@example.test', '+40700000001');
        EditionRegistration::factory()->withoutEditionDivision()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $nullDivisionPlayer->id_player,
            'is_student' => false,
            'payment' => 55,
        ]);

        $divisionPlayer = Player::factory()->create([
            'first_name' => 'Vlad',
            'last_name' => 'Mitu',
        ]);
        $this->setContacts($divisionPlayer, 'vlad@example.test', '+40700000002');
        $division = Division::factory()->create();
        $editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $division->id_division,
            'division_title' => $division->name,
        ]);
        EditionRegistration::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $divisionPlayer->id_player,
            'edition_division_id' => $editionDivision->id_edition_division,
            'is_student' => false,
            'payment' => 55,
        ]);

        $response = $this->postPreview(
            "Name,Email,Phone Number\n"
            . "Mara Popescu,mara@example.test,+40 700 000 099\n"
            . "Vlad Mitu,vlad@example.test,+40 700 000 098\n"
        );

        $response->assertOk();
        $this->assertSame('mapped', $response->json('data.rows.0.mapping.status'));
        $this->assertTrue($response->json('data.rows.0.already_registered'));
        $this->assertSame('mapped', $response->json('data.rows.1.mapping.status'));
        $this->assertTrue($response->json('data.rows.1.already_registered'));
    }

    public function test_preview_already_registered_false_when_not_registered(): void
    {
        Sanctum::actingAs($this->manager);
        $player = Player::factory()->create([
            'first_name' => 'Mara',
            'last_name' => 'Popescu',
        ]);
        $this->setContacts($player, 'mara@example.test', '+40700000001');

        $this->postPreview(
            "Name,Email,Phone Number\nMara Popescu,mara@example.test,+40 700 000 099\n"
        )->assertOk()
            ->assertJsonPath('data.rows.0.mapping.status', 'mapped')
            ->assertJsonPath('data.rows.0.already_registered', false);
    }

    public function test_preview_and_save_return_409_when_edition_finalized(): void
    {
        Sanctum::actingAs($this->manager);
        $this->edition->update(['finalized_at' => time()]);

        $this->postPreview("Name,Email,Phone Number\nMara,mara@example.test,+40712\n")
            ->assertStatus(409)
            ->assertJsonFragment(['Edition is finalized.']);

        $this->postJson($this->importUrl(), [
            'format' => 'playtomic',
            'rows' => [[
                'index' => 0,
                'first_name' => 'Mara',
                'email' => 'mara@example.test',
                'phone' => '',
                'player_id' => Player::factory()->create()->id_player,
                'status' => 'mapped',
            ]],
        ])->assertStatus(409)
            ->assertJsonFragment(['Edition is finalized.']);
    }

    public function test_save_creates_registration_with_entry_fee_defaults(): void
    {
        Sanctum::actingAs($this->manager);
        $player = Player::factory()->create();
        $playersBefore = Player::count();
        $regsBefore = EditionRegistration::count();

        $response = $this->postJson($this->importUrl(), [
            'format' => 'playtomic',
            'rows' => [[
                'index' => 0,
                'first_name' => 'Mara',
                'middle_name' => null,
                'last_name' => 'Popescu',
                'email' => 'mara@example.test',
                'phone' => '+40712345678',
                'player_id' => $player->id_player,
                'status' => 'mapped',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.registered', 1)
            ->assertJsonPath('data.skipped_already_registered', 0)
            ->assertJsonPath('data.skipped_unmapped', 0)
            ->assertJsonPath('data.skipped_conflict', 0)
            ->assertJsonPath('data.rows.0.action', 'registered')
            ->assertJsonPath('data.rows.0.player_id', $player->id_player);

        $this->assertSame($playersBefore, Player::count());
        $this->assertSame($regsBefore + 1, EditionRegistration::count());

        $registration = EditionRegistration::query()
            ->where('edition_id', $this->edition->id_edition)
            ->where('player_id', $player->id_player)
            ->first();
        $this->assertNotNull($registration);
        $this->assertNull($registration->edition_division_id);
        $this->assertSame(0, (int) $registration->discount);
        $this->assertSame(55, (int) $registration->payment);
        $this->assertFalse($registration->is_minor);
        $this->assertFalse($registration->is_student);
    }

    public function test_save_does_not_create_players(): void
    {
        Sanctum::actingAs($this->manager);
        $playersBefore = Player::count();

        $this->postJson($this->importUrl(), [
            'format' => 'playtomic',
            'rows' => [[
                'index' => 0,
                'first_name' => 'Brand',
                'last_name' => 'New',
                'email' => 'brand.new@example.test',
                'phone' => '',
                'player_id' => null,
                'status' => 'unmapped',
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.registered', 0)
            ->assertJsonPath('data.skipped_unmapped', 1);

        $this->assertSame($playersBefore, Player::count());
        $this->assertSame(0, EditionRegistration::count());
    }

    public function test_save_skips_unmapped_and_null_player_id(): void
    {
        Sanctum::actingAs($this->manager);
        $player = Player::factory()->create();

        $response = $this->postJson($this->importUrl(), [
            'format' => 'playtomic',
            'rows' => [
                [
                    'index' => 0,
                    'first_name' => 'A',
                    'email' => 'a@example.test',
                    'player_id' => null,
                    'status' => 'unmapped',
                ],
                [
                    'index' => 1,
                    'first_name' => 'B',
                    'email' => 'b@example.test',
                    'player_id' => null,
                    'status' => 'mapped',
                ],
                [
                    'index' => 2,
                    'first_name' => 'C',
                    'email' => 'c@example.test',
                    'player_id' => $player->id_player,
                    'status' => 'mapped',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.skipped_unmapped', 2)
            ->assertJsonPath('data.registered', 1)
            ->assertJsonPath('data.rows.0.action', 'skipped_unmapped')
            ->assertJsonPath('data.rows.1.action', 'skipped_unmapped')
            ->assertJsonPath('data.rows.2.action', 'registered');
    }

    public function test_save_skips_conflict_status(): void
    {
        Sanctum::actingAs($this->manager);
        $emailOwner = Player::factory()->create();
        $phoneOwner = Player::factory()->create();

        $response = $this->postJson($this->importUrl(), [
            'format' => 'playtomic',
            'rows' => [[
                'index' => 0,
                'first_name' => 'Conflict',
                'email' => 'a@example.test',
                'phone' => '+40700000051',
                'player_id' => $emailOwner->id_player,
                'status' => 'conflict',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.skipped_conflict', 1)
            ->assertJsonPath('data.registered', 0)
            ->assertJsonPath('data.rows.0.action', 'skipped_conflict');

        $this->assertSame(0, EditionRegistration::count());
        $this->assertTrue(Player::query()->whereKey($phoneOwner->id_player)->exists());
    }

    public function test_save_skips_already_registered_in_db(): void
    {
        Sanctum::actingAs($this->manager);
        $player = Player::factory()->create();
        EditionRegistration::factory()->withoutEditionDivision()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $player->id_player,
            'is_student' => false,
            'payment' => 55,
        ]);

        $this->postJson($this->importUrl(), [
            'format' => 'playtomic',
            'rows' => [[
                'index' => 0,
                'first_name' => 'Mara',
                'email' => 'mara@example.test',
                'player_id' => $player->id_player,
                'status' => 'mapped',
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.registered', 0)
            ->assertJsonPath('data.skipped_already_registered', 1)
            ->assertJsonPath('data.rows.0.action', 'skipped_already_registered');

        $this->assertSame(1, EditionRegistration::count());
    }

    public function test_save_duplicate_player_id_in_batch_registers_first_skips_second(): void
    {
        Sanctum::actingAs($this->manager);
        $player = Player::factory()->create();

        $response = $this->postJson($this->importUrl(), [
            'format' => 'playtomic',
            'rows' => [
                [
                    'index' => 0,
                    'first_name' => 'Mara',
                    'email' => 'mara@example.test',
                    'player_id' => $player->id_player,
                    'status' => 'mapped',
                ],
                [
                    'index' => 1,
                    'first_name' => 'Mara',
                    'email' => 'mara.alt@example.test',
                    'player_id' => $player->id_player,
                    'status' => 'mapped',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.registered', 1)
            ->assertJsonPath('data.skipped_already_registered', 1)
            ->assertJsonPath('data.rows.0.action', 'registered')
            ->assertJsonPath('data.rows.1.action', 'skipped_already_registered');

        $this->assertSame(1, EditionRegistration::count());
    }

    public function test_preview_rejects_oversize_too_many_rows_and_bad_mime(): void
    {
        Sanctum::actingAs($this->manager);

        $tooBig = UploadedFile::fake()->create('players.csv', 2049, 'text/csv');
        $this->post($this->previewUrl(), ['file' => $tooBig], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $lines = "Name,Email,Phone Number\n";
        for ($i = 0; $i < 501; $i++) {
            $lines .= "P{$i},p{$i}@example.test,+40700" . str_pad((string) $i, 6, '0', STR_PAD_LEFT) . "\n";
        }
        $this->postPreview($lines)->assertStatus(422);

        $pdf = UploadedFile::fake()->create('players.pdf', 10, 'application/pdf');
        $this->post($this->previewUrl(), ['file' => $pdf], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_preview_unknown_headers_lists_playtomic(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postPreview("Full Name,Mail,Mobile\nMara,mara@example.test,+40712\n")
            ->assertStatus(422)
            ->assertJsonFragment(['Unknown CSV format. Supported: Playtomic.']);
    }

    private function previewUrl(): string
    {
        return '/api/v1/manager/editions/' . $this->edition->id_edition . '/registrations/import/preview';
    }

    private function importUrl(): string
    {
        return '/api/v1/manager/editions/' . $this->edition->id_edition . '/registrations/import';
    }

    private function postPreview(string $csv)
    {
        return $this->post(
            $this->previewUrl(),
            ['file' => $this->csvUpload($csv)],
            ['Accept' => 'application/json']
        );
    }

    private function csvUpload(string $contents, string $name = 'players.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function setContacts(Player $player, string $email, string $phone): void
    {
        $player->emails()->delete();
        $player->phones()->delete();
        PlayerEmail::factory()->create([
            'player_id' => $player->id_player,
            'email' => $email,
            'purposes' => ['contact'],
            'is_primary' => true,
        ]);
        PlayerPhone::factory()->create([
            'player_id' => $player->id_player,
            'phone' => $phone,
            'purposes' => ['contact'],
            'is_primary' => true,
        ]);
    }
}
