<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\PadelEvent;
use App\Models\Player;
use App\Models\PlayerCustomFieldValue;
use App\Models\PlayerCustomFieldValueImport;
use App\Models\PlayerEmail;
use App\Models\PlayerPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PlayerCustomFieldValueImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create();
    }

    public function test_preview_requires_manager_auth(): void
    {
        $this->post('/api/v1/manager/players/custom-field-values/import/preview', [
            'file' => $this->fixtureUpload(),
        ], ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_preview_fixture_detects_format_and_maps_player(): void
    {
        Sanctum::actingAs($this->manager);

        $player = Player::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Demo',
        ]);
        $this->setContacts($player, 'ana.demo@example.test', '+40712000001');

        $importsBefore = PlayerCustomFieldValueImport::count();
        $valuesBefore = PlayerCustomFieldValue::count();

        $response = $this->post(
            '/api/v1/manager/players/custom-field-values/import/preview',
            ['file' => $this->fixtureUpload()],
            ['Accept' => 'application/json']
        );

        $response->assertOk()
            ->assertJsonPath('data.format', 'google_form_minitour_welcome');

        $rows = $response->json('data.rows');
        $this->assertCount(2, $rows);
        $this->assertSame('mapped', $rows[0]['mapping']['status']);
        $this->assertSame($player->id_player, $rows[0]['mapping']['player']['id_player']);
        $this->assertSame('1998', $rows[0]['answers']['year_of_birth']);
        $this->assertSame('female', $rows[0]['answers']['gender']);
        $this->assertArrayNotHasKey('padel_matches_played', $rows[0]['answers']);
        $this->assertTrue($rows[0]['included']);

        $this->assertSame($importsBefore, PlayerCustomFieldValueImport::count());
        $this->assertSame($valuesBefore, PlayerCustomFieldValue::count());
    }

    public function test_preview_unknown_headers_returns_422(): void
    {
        Sanctum::actingAs($this->manager);

        $this->post(
            '/api/v1/manager/players/custom-field-values/import/preview',
            ['file' => $this->csvUpload("Name,Email\nAna,a@example.test\n")],
            ['Accept' => 'application/json']
        )->assertStatus(422);
    }

    public function test_save_writes_values_and_history_without_creating_players(): void
    {
        Sanctum::actingAs($this->manager);

        $player = Player::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Demo',
        ]);
        $this->setContacts($player, 'ana.demo@example.test', '+40712000001');
        $playersBefore = Player::count();
        $emailsBefore = PlayerEmail::count();

        $response = $this->postJson('/api/v1/manager/players/custom-field-values/import', [
            'format' => 'google_form_minitour_welcome',
            'filename' => 'sample.csv',
            'rows' => [[
                'index' => 0,
                'full_name' => 'Ana Demo',
                'email' => 'ana.demo@example.test',
                'phone' => '+40712000001',
                'player_id' => $player->id_player,
                'answers' => [
                    'year_of_birth' => '1998',
                    'gender' => 'Female',
                    'playtomic_id' => '1234567',
                    'rankedin_id' => '',
                ],
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.included_count', 1)
            ->assertJsonPath('data.values_new_count', 3)
            ->assertJsonPath('data.values_replaced_count', 0)
            ->assertJsonPath('data.rows.0.action', 'imported');

        $this->assertSame($playersBefore, Player::count());
        $this->assertSame($emailsBefore, PlayerEmail::count());

        $value = PlayerCustomFieldValue::query()->where('player_id', $player->id_player)->firstOrFail();
        $this->assertSame('csv', $value->submitted_via);
        $this->assertNotNull($value->created_via_import_id);

        $this->assertSame(
            1998,
            $this->currentValue($player, 'year_of_birth')->value_integer
        );
        $this->assertSame(
            'female',
            $this->currentValue($player, 'gender')->selectedOptions()->firstOrFail()->option->code
        );
    }

    public function test_save_with_edition_replaces_on_reimport(): void
    {
        Sanctum::actingAs($this->manager);

        $player = Player::factory()->create();
        $event = PadelEvent::factory()->create();

        $payload = [
            'format' => 'google_form_minitour_welcome',
            'filename' => 'sample.csv',
            'event_id' => $event->id_event,
            'rows' => [[
                'index' => 0,
                'full_name' => 'Ana Demo',
                'email' => '',
                'phone' => '',
                'player_id' => $player->id_player,
                'answers' => ['playtomic_id' => '5'],
            ]],
        ];

        $this->postJson('/api/v1/manager/players/custom-field-values/import', $payload)
            ->assertCreated()
            ->assertJsonPath('data.values_new_count', 1)
            ->assertJsonPath('data.values_replaced_count', 0);

        $payload['rows'][0]['answers']['playtomic_id'] = '9';
        $this->postJson('/api/v1/manager/players/custom-field-values/import', $payload)
            ->assertCreated()
            ->assertJsonPath('data.values_new_count', 0)
            ->assertJsonPath('data.values_replaced_count', 1);

        // Each import is its own submission: both rows stay validated (null manager); the newest wins.
        $this->assertSame(2, PlayerCustomFieldValue::query()->count());
        $this->assertSame(
            2,
            PlayerCustomFieldValue::query()
                ->where('review_status', 'validated')
                ->whereNull('reviewed_by_manager_id')
                ->whereNotNull('reviewed_at')
                ->count()
        );
        $this->assertSame('9', $this->currentValue($player, 'playtomic_id')->value_text);
    }

    public function test_save_included_row_without_player_id_is_422(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/players/custom-field-values/import', [
            'format' => 'google_form_minitour_welcome',
            'filename' => 'sample.csv',
            'rows' => [[
                'index' => 0,
                'full_name' => 'Ana Demo',
                'email' => 'ana.demo@example.test',
                'phone' => '',
                'answers' => ['playtomic_id' => '1'],
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, PlayerCustomFieldValueImport::count());
    }

    public function test_history_endpoints_are_get_only(): void
    {
        Sanctum::actingAs($this->manager);

        $player = Player::factory()->create();
        $import = $this->postJson('/api/v1/manager/players/custom-field-values/import', [
            'format' => 'google_form_minitour_welcome',
            'filename' => 'hist.csv',
            'rows' => [[
                'index' => 0,
                'full_name' => 'Ana',
                'email' => '',
                'phone' => '',
                'player_id' => $player->id_player,
                'answers' => ['year_of_birth' => '1995'],
            ]],
        ])->assertCreated()->json('data');

        $this->getJson('/api/v1/manager/player-custom-field-value-imports')
            ->assertOk()
            ->assertJsonPath('data.0.id_player_custom_field_value_import', $import['id_player_custom_field_value_import']);

        $this->getJson('/api/v1/manager/player-custom-field-value-imports/' . $import['id_player_custom_field_value_import'])
            ->assertOk()
            ->assertJsonPath('data.original_filename', 'hist.csv');

        $this->getJson(
            '/api/v1/manager/player-custom-field-value-import-rows?filters[player_custom_field_value_import_id]='
            . $import['id_player_custom_field_value_import']
        )->assertOk();

        $this->postJson('/api/v1/manager/player-custom-field-value-imports', [])->assertStatus(405);
    }

    public function test_preview_rejects_the_old_format_headers(): void
    {
        Sanctum::actingAs($this->manager);

        $legacy = 'Timestamp,Username,Salutare 👋 care este numele tău complet?,Câte meciuri de padel ai mai jucat?,Ai jucat tenis vreodată?,Ai luat lecții de padel sau tenis?,"În caz că ai participat la turnee mari, în ce divizie te încadrezi?",Ce vârstă ai?,La ce număr de telefon dăm de tine?,Ajută-ne cu linkul profilului tău de Playtomic,Folosim informațiile în a echilibra diviziile cât mai bine,Poza este strict pentru afișul cu meciurile
'
            . '2026-01-10 09:15:00,ana.demo@example.test,Ana Demo,12,Da,Da,Div 4 High,28,+40 712 000 001,https://playtomic.io/user/demo-ana,Da,Da
';

        $this->post(
            '/api/v1/manager/players/custom-field-values/import/preview',
            ['file' => $this->csvUpload($legacy)],
            ['Accept' => 'application/json']
        )->assertStatus(422);
    }

    public function test_save_writes_racket_experience_json(): void
    {
        Sanctum::actingAs($this->manager);

        $player = Player::factory()->create();
        $experience = '[{"sport":"padel","level":"amateur","matches_played":12}]';

        $this->postJson('/api/v1/manager/players/custom-field-values/import', [
            'format' => 'google_form_minitour_welcome',
            'filename' => 'json.csv',
            'rows' => [[
                'index' => 0,
                'full_name' => 'Ana',
                'email' => '',
                'phone' => '',
                'player_id' => $player->id_player,
                'answers' => ['racket_experience' => $experience],
            ]],
        ])->assertCreated()->assertJsonPath('data.values_new_count', 1);

        $this->assertEquals(
            [['sport' => 'padel', 'level' => 'amateur', 'matches_played' => 12]],
            $this->currentValue($player, 'racket_experience')->value_json
        );
    }

    public function test_save_rejects_unknown_gender_option(): void
    {
        Sanctum::actingAs($this->manager);

        $player = Player::factory()->create();

        $this->postJson('/api/v1/manager/players/custom-field-values/import', [
            'format' => 'google_form_minitour_welcome',
            'filename' => 'bad.csv',
            'rows' => [[
                'index' => 0,
                'full_name' => 'Ana',
                'email' => '',
                'phone' => '',
                'player_id' => $player->id_player,
                'answers' => ['gender' => 'robot'],
            ]],
        ])->assertStatus(422);

        $this->assertSame(0, PlayerCustomFieldValueImport::count());
        $this->assertSame(0, PlayerCustomFieldValue::count());
    }

    private function currentValue(Player $player, string $code): PlayerCustomFieldValue
    {
        return PlayerCustomFieldValue::query()
            ->where('player_id', $player->id_player)
            ->whereHas('field', fn ($query) => $query->where('code', $code))
            ->latestValidatedFirst()
            ->firstOrFail();
    }

    private function fixtureUpload(): UploadedFile
    {
        $path = base_path('tests/Fixtures/player-custom-field-value-import/google_form_minitour_welcome_sample.csv');

        return UploadedFile::fake()->createWithContent('minitour.csv', (string) file_get_contents($path));
    }

    private function csvUpload(string $contents, string $name = 'minitour.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function setContacts(Player $player, string $email, string $phone): void
    {
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
