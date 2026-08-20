<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Player;
use App\Models\PlayerEmail;
use App\Models\PlayerImport;
use App\Models\PlayerImportRow;
use App\Models\PlayerPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create([
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Manager',
        ]);
    }

    public function test_preview_requires_manager_auth(): void
    {
        $this->post('/api/v1/manager/players/import/preview', [
            'file' => $this->csvUpload("Name,Email,Phone Number\nMara,mara@example.test,+40712\n"),
        ], ['Accept' => 'application/json'])->assertStatus(401);
    }

    public function test_preview_playtomic_fixture_splits_and_does_not_write(): void
    {
        Sanctum::actingAs($this->manager);
        $playersBefore = Player::count();
        $importsBefore = PlayerImport::count();

        $csv = "\xEF\xBB\xBFName,Email,Phone Number,Level\n"
            . "Mara Popescu,MARA@Example.test,+40 712 345 678,4.5\n"
            . "\"Popescu, Vlad\",vlad@example.test,+40 722 111 222,3\n"
            . "*fictiv* Guest,fictiv@example.test,+40 700 000 001,1\n"
            . ",empty.name@example.test,+40 700 000 002,1\n";

        $response = $this->postPreview($csv);

        $response->assertOk()
            ->assertJsonPath('data.format', 'playtomic');

        $rows = $response->json('data.rows');
        $this->assertCount(4, $rows);
        $this->assertSame('Mara', $rows[0]['first_name']);
        $this->assertSame('Popescu', $rows[0]['last_name']);
        $this->assertSame('mara@example.test', $rows[0]['email']);
        $this->assertSame('+40712345678', $rows[0]['phone']);
        $this->assertArrayNotHasKey('name', $rows[0]);

        $this->assertSame('Popescu,', $rows[1]['first_name']);
        $this->assertSame('Vlad', $rows[1]['last_name']);

        $this->assertSame('*fictiv*', $rows[2]['first_name']);
        $this->assertSame('Guest', $rows[2]['last_name']);

        $this->assertSame('', $rows[3]['first_name']);
        $this->assertSame('empty.name@example.test', $rows[3]['email']);

        $this->assertSame($playersBefore, Player::count());
        $this->assertSame($importsBefore, PlayerImport::count());
    }

    public function test_preview_nested_player_has_picker_fields(): void
    {
        Sanctum::actingAs($this->manager);
        $existing = Player::factory()->create([
            'first_name' => 'Mara',
            'middle_name' => null,
            'last_name' => 'Popescu',
        ]);
        $this->setContacts($existing, 'mara@example.test', '+40700000001');

        $response = $this->postPreview(
            "Name,Email,Phone Number\nMara Popescu,mara@example.test,+40 700 000 099\n"
        );

        $response->assertOk();
        $player = $response->json('data.rows.0.mapping.player');
        $this->assertSame($existing->id_player, $player['id_player']);
        $this->assertSame('Mara Popescu', $player['name']);
        $this->assertArrayHasKey('pdf_avatar_url', $player);
        $this->assertArrayHasKey('emails', $player);
        $this->assertArrayHasKey('phones', $player);
        $this->assertSame('mapped', $response->json('data.rows.0.mapping.status'));
        $this->assertSame('email', $response->json('data.rows.0.mapping.matched_by'));
    }

    public function test_preview_unknown_headers_lists_playtomic(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postPreview("Full Name,Mail,Mobile\nMara,mara@example.test,+40712\n")
            ->assertStatus(422)
            ->assertJsonFragment(['Unknown CSV format. Supported: Playtomic.']);
    }

    public function test_preview_rejects_oversize_too_many_rows_and_bad_mime(): void
    {
        Sanctum::actingAs($this->manager);

        $tooBig = UploadedFile::fake()->create('players.csv', 2049, 'text/csv');
        $this->post('/api/v1/manager/players/import/preview', ['file' => $tooBig], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $lines = "Name,Email,Phone Number\n";
        for ($i = 0; $i < 501; $i++) {
            $lines .= "P{$i},p{$i}@example.test,+40700" . str_pad((string) $i, 6, '0', STR_PAD_LEFT) . "\n";
        }
        $this->postPreview($lines)->assertStatus(422);

        $pdf = UploadedFile::fake()->create('players.pdf', 10, 'application/pdf');
        $this->post('/api/v1/manager/players/import/preview', ['file' => $pdf], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_save_creates_with_email_or_phone_and_sets_provenance(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'club.csv',
            'rows' => [
                [
                    'index' => 0,
                    'first_name' => 'Mara',
                    'middle_name' => null,
                    'last_name' => 'Popescu',
                    'email' => 'mara.new@example.test',
                    'phone' => '',
                    'player_id' => null,
                ],
                [
                    'index' => 1,
                    'first_name' => 'Vlad',
                    'middle_name' => null,
                    'last_name' => 'Mitu',
                    'email' => '',
                    'phone' => '+40 733 111 222',
                    'player_id' => null,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.format', 'playtomic')
            ->assertJsonPath('data.original_filename', 'club.csv')
            ->assertJsonPath('data.imported_by_player_id', $this->manager->id_player)
            ->assertJsonPath('data.included_count', 2)
            ->assertJsonPath('data.created_count', 2)
            ->assertJsonPath('data.attached_count', 0);

        $this->assertNotSame('devLogin', $response->json('data.format'));
        $this->assertSame('created', $response->json('data.rows.0.action'));
        $this->assertNotNull($response->json('data.rows.0.player_id'));

        $created = Player::find($response->json('data.rows.0.player_id'));
        $this->assertSame(['player'], $created->user_types);
        $this->assertSame('csv', $created->created_via);
        $this->assertSame('playtomic', $created->created_via_format);
        $this->assertSame($response->json('data.id_player_import'), $created->created_via_import_id);

        $phonePlayer = Player::find($response->json('data.rows.1.player_id'));
        $this->assertSame('+40733111222', $phonePlayer->phones()->first()->phone);
        $this->assertSame(['contact'], $phonePlayer->phones()->first()->purposes);
        $this->assertTrue($phonePlayer->phones()->first()->is_primary);
        $this->assertSame(['contact'], $created->emails()->first()->purposes);
    }

    public function test_save_unmapped_requires_first_name_and_a_contact(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'x.csv',
            'rows' => [[
                'index' => 0,
                'first_name' => '',
                'email' => 'a@example.test',
                'phone' => '',
                'player_id' => null,
            ]],
        ]);
        $response->assertStatus(422);
        $this->assertSame(
            ['First name is required to create a player.'],
            $response->json('errors')['rows.0.first_name']
        );

        $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'x.csv',
            'rows' => [[
                'index' => 0,
                'first_name' => 'Mara',
                'email' => '',
                'phone' => '',
                'player_id' => null,
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, PlayerImport::count());
    }

    public function test_save_mapped_attaches_missing_contact_without_overwriting(): void
    {
        Sanctum::actingAs($this->manager);
        $existing = Player::factory()->create([
            'first_name' => 'Mara',
            'last_name' => 'Popescu',
            'skill_division' => 4.5,
            'created_via' => 'manager',
        ]);
        $this->setContacts($existing, 'mara@example.test', '+40 712 000 000');
        $originalPhone = $existing->phones()->first()->phone;

        $response = $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'x.csv',
            'rows' => [[
                'index' => 0,
                'first_name' => 'Ignored',
                'last_name' => 'Name',
                'email' => 'mara@example.test',
                'phone' => '+40 799 111 222',
                'player_id' => $existing->id_player,
            ]],
        ]);

        $response->assertCreated()->assertJsonPath('data.rows.0.action', 'attached');
        $existing->refresh();
        $this->assertSame('Mara', $existing->first_name);
        $this->assertSame(4.5, (float) $existing->skill_division);
        $this->assertSame('manager', $existing->created_via);
        $this->assertNull($existing->created_via_format);
        $this->assertNull($existing->created_via_import_id);
        $this->assertTrue($existing->phones()->where('phone', $originalPhone)->exists());
        $this->assertTrue($existing->phones()->where('phone', '+40799111222')->exists());
        $newPhone = $existing->phones()->where('phone', '+40799111222')->first();
        $this->assertSame(['contact'], $newPhone->purposes);
        $this->assertFalse($newPhone->is_primary);
    }

    public function test_save_mapped_empty_contacts_is_attached_noop(): void
    {
        Sanctum::actingAs($this->manager);
        $existing = Player::factory()->create();

        $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'x.csv',
            'rows' => [[
                'index' => 0,
                'first_name' => '',
                'email' => '',
                'phone' => '',
                'player_id' => $existing->id_player,
            ]],
        ])->assertCreated()->assertJsonPath('data.rows.0.action', 'attached');
    }

    public function test_save_contact_owned_by_another_player_rolls_back(): void
    {
        Sanctum::actingAs($this->manager);
        $owner = Player::factory()->create();
        $mapped = Player::factory()->create();
        $this->setContacts($owner, 'taken@example.test', '+40700000040');

        $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'x.csv',
            'rows' => [[
                'index' => 0,
                'first_name' => 'Mara',
                'email' => 'taken@example.test',
                'phone' => '',
                'player_id' => $mapped->id_player,
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, PlayerImport::count());
        $this->assertFalse($mapped->fresh()->emails()->where('email', 'taken@example.test')->exists());
    }

    public function test_save_conflict_null_player_id_is_422_create(): void
    {
        Sanctum::actingAs($this->manager);
        $emailOwner = Player::factory()->create();
        $phoneOwner = Player::factory()->create();
        $this->setContacts($emailOwner, 'a@example.test', '+40700000050');
        $this->setContacts($phoneOwner, 'b@example.test', '+40700000051');

        $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'x.csv',
            'rows' => [[
                'index' => 0,
                'first_name' => 'New',
                'email' => 'a@example.test',
                'phone' => '+40700000051',
                'player_id' => null,
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, PlayerImport::count());
    }

    public function test_save_conflict_with_both_contacts_and_player_id_is_422_attach(): void
    {
        Sanctum::actingAs($this->manager);
        $emailOwner = Player::factory()->create();
        $phoneOwner = Player::factory()->create();
        $this->setContacts($emailOwner, 'a@example.test', '+40700000060');
        $this->setContacts($phoneOwner, 'b@example.test', '+40700000061');

        $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'x.csv',
            'rows' => [[
                'index' => 0,
                'first_name' => 'New',
                'email' => 'a@example.test',
                'phone' => '+40700000061',
                'player_id' => $emailOwner->id_player,
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, PlayerImport::count());
    }

    public function test_save_conflict_after_clearing_other_contact_attaches(): void
    {
        Sanctum::actingAs($this->manager);
        $emailOwner = Player::factory()->create();
        $phoneOwner = Player::factory()->create();
        $this->setContacts($emailOwner, 'a@example.test', '+40700000070');
        $this->setContacts($phoneOwner, 'b@example.test', '+40700000071');

        $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'x.csv',
            'rows' => [[
                'index' => 0,
                'first_name' => 'New',
                'email' => 'a@example.test',
                'phone' => '',
                'player_id' => $emailOwner->id_player,
            ]],
        ])->assertCreated()->assertJsonPath('data.rows.0.action', 'attached');
    }

    public function test_save_intra_batch_duplicate_and_zero_rows_and_unknown_format(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'x.csv',
            'rows' => [
                ['index' => 0, 'first_name' => 'A', 'email' => 'dup@example.test', 'phone' => '', 'player_id' => null],
                ['index' => 1, 'first_name' => 'B', 'email' => 'dup@example.test', 'phone' => '', 'player_id' => null],
            ],
        ])->assertStatus(422);
        $this->assertSame(0, PlayerImport::count());

        $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'x.csv',
            'rows' => [],
        ])->assertStatus(422);

        $this->postJson('/api/v1/manager/players/import', [
            'format' => 'unknown',
            'filename' => 'x.csv',
            'rows' => [['index' => 0, 'first_name' => 'A', 'email' => 'z@example.test', 'player_id' => null]],
        ])->assertStatus(422);
    }

    public function test_save_one_bad_row_rolls_back_players_and_batch(): void
    {
        Sanctum::actingAs($this->manager);
        $owner = Player::factory()->create();
        $this->setContacts($owner, 'taken@example.test', '+40700000080');
        $playersBefore = Player::count();

        $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'x.csv',
            'rows' => [
                ['index' => 0, 'first_name' => 'Ok', 'email' => 'ok@example.test', 'player_id' => null],
                ['index' => 1, 'first_name' => 'Bad', 'email' => 'taken@example.test', 'player_id' => null],
            ],
        ])->assertStatus(422);

        $this->assertSame($playersBefore, Player::count());
        $this->assertSame(0, PlayerImport::count());
        $this->assertSame(0, PlayerImportRow::count());
    }

    public function test_save_does_not_remap_unique_clash_is_422(): void
    {
        Sanctum::actingAs($this->manager);
        $existing = Player::factory()->create();
        $this->setContacts($existing, 'taken@example.test', '+40700000090');

        $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'x.csv',
            'rows' => [[
                'index' => 0,
                'first_name' => 'New',
                'email' => 'taken@example.test',
                'player_id' => null,
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, PlayerImport::count());
    }

    public function test_save_idempotent_attach(): void
    {
        Sanctum::actingAs($this->manager);
        $existing = Player::factory()->create();
        $this->setContacts($existing, 'mara@example.test', '+40700111111');
        $emailCount = $existing->emails()->count();

        $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'x.csv',
            'rows' => [[
                'index' => 0,
                'first_name' => 'Mara',
                'email' => 'MARA@example.test',
                'phone' => '+40 700 111 111',
                'player_id' => $existing->id_player,
            ]],
        ])->assertCreated()->assertJsonPath('data.rows.0.action', 'attached');

        $this->assertSame($emailCount, $existing->fresh()->emails()->count());
        $this->assertSame('+40700111111', $existing->phones()->first()->phone);
    }

    public function test_history_is_get_only_and_metadata_exists(): void
    {
        Sanctum::actingAs($this->manager);
        $import = $this->postJson('/api/v1/manager/players/import', [
            'format' => 'playtomic',
            'filename' => 'hist.csv',
            'rows' => [[
                'index' => 0,
                'first_name' => 'Mara',
                'email' => 'hist@example.test',
                'player_id' => null,
            ]],
        ])->assertCreated()->json('data');

        $this->getJson('/api/v1/manager/player-imports')
            ->assertOk()
            ->assertJsonPath('data.0.id_player_import', $import['id_player_import']);

        $this->getJson('/api/v1/manager/player-imports/' . $import['id_player_import'])
            ->assertOk()
            ->assertJsonPath('data.original_filename', 'hist.csv')
            ->assertJsonPath('data.rows.0.action', 'created');

        $this->getJson('/api/v1/manager/player-import-rows?filters[player_import_id]=' . $import['id_player_import'])
            ->assertOk()
            ->assertJsonPath('data.0.player_import_id', $import['id_player_import']);

        $this->postJson('/api/v1/manager/player-imports', [])->assertStatus(405);
        $this->putJson('/api/v1/manager/player-imports/' . $import['id_player_import'], [])->assertStatus(405);
        $this->deleteJson('/api/v1/manager/player-imports/' . $import['id_player_import'])->assertStatus(405);
        $this->postJson('/api/v1/manager/player-import-rows', [])->assertStatus(405);

        $meta = $this->getJson('/api/v1/manager/metadata/PlayerImport')->assertOk();
        $this->assertSame('PlayerImport', $meta->json('data.schema_name'));

        $rowMeta = $this->getJson('/api/v1/manager/metadata/PlayerImportRow')->assertOk();
        $this->assertSame('foreign_key', $rowMeta->json('data.filterable_fields.player_import_id.type'));
        $this->assertSame('PlayerImport', $rowMeta->json('data.filterable_fields.player_import_id.related_schema_name'));

        $playerMeta = $this->getJson('/api/v1/manager/metadata/Player')->assertOk();
        $this->assertTrue($playerMeta->json('data.swagger_schema.properties.created_via.readOnly'));
        $this->assertTrue($playerMeta->json('data.swagger_schema.properties.created_via_format.readOnly'));
        $this->assertTrue($playerMeta->json('data.swagger_schema.properties.created_via_import_id.readOnly'));
    }

    public function test_get_players_import_is_not_player_show(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/manager/players/import');
        $this->assertContains($response->status(), [404, 405]);
        $this->assertNull($response->json('data.id_player'));
    }

    private function postPreview(string $csv)
    {
        return $this->post(
            '/api/v1/manager/players/import/preview',
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
