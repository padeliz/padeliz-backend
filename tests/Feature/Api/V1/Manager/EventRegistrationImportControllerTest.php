<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventRegistrationImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private PadelEvent $event;

    private EventDivision $importDivision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create([
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Manager',
        ]);
        $this->event = PadelEvent::factory()->create([
            'finalized_at' => null,
        ]);
        $division = Division::factory()->create();
        $this->importDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'division_title' => $division->name,
            'entry_fee' => 77,
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

    public function test_preview_already_registered_true_for_existing_event_registrations(): void
    {
        Sanctum::actingAs($this->manager);

        $importDivisionPlayer = User::factory()->create([
            'first_name' => 'Mara',
            'last_name' => 'Popescu',
        ]);
        $this->setContacts($importDivisionPlayer, 'mara@example.test', '+40700000001');
        EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $importDivisionPlayer->id_user,
            'event_division_id' => $this->importDivision->id_event_division,
            'is_student' => false,
            'payment' => 77,
        ]);

        $divisionPlayer = User::factory()->create([
            'first_name' => 'Vlad',
            'last_name' => 'Mitu',
        ]);
        $this->setContacts($divisionPlayer, 'vlad@example.test', '+40700000002');
        $division = Division::factory()->create();
        $eventDivision = EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'division_title' => $division->name,
        ]);
        EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $divisionPlayer->id_user,
            'event_division_id' => $eventDivision->id_event_division,
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
        $player = User::factory()->create([
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
        $this->event->update(['finalized_at' => time()]);

        $this->postPreview("Name,Email,Phone Number\nMara,mara@example.test,+40712\n")
            ->assertStatus(409)
            ->assertJsonFragment(['PadelEvent is finalized.']);

        $this->postJson($this->importUrl(), $this->importPayload([[
            'index' => 0,
            'first_name' => 'Mara',
            'email' => 'mara@example.test',
            'phone' => '',
            'player_user_id' => User::factory()->create()->id_user,
            'status' => 'mapped',
        ]]))->assertStatus(409)
            ->assertJsonFragment(['PadelEvent is finalized.']);
    }

    public function test_save_requires_edition_division_and_uses_division_entry_fee(): void
    {
        Sanctum::actingAs($this->manager);
        $player = User::factory()->create();
        $playersBefore = User::count();
        $regsBefore = EventRegistration::count();

        $response = $this->postJson($this->importUrl(), $this->importPayload([[
            'index' => 0,
            'first_name' => 'Mara',
            'middle_name' => null,
            'last_name' => 'Popescu',
            'email' => 'mara@example.test',
            'phone' => '+40712345678',
            'player_user_id' => $player->id_user,
            'status' => 'mapped',
        ]]));

        $response->assertCreated()
            ->assertJsonPath('data.registered', 1)
            ->assertJsonPath('data.skipped_already_registered', 0)
            ->assertJsonPath('data.skipped_unmapped', 0)
            ->assertJsonPath('data.skipped_conflict', 0)
            ->assertJsonPath('data.rows.0.action', 'registered')
            ->assertJsonPath('data.rows.0.player_user_id', $player->id_user);

        $this->assertSame($playersBefore, User::count());
        $this->assertSame($regsBefore + 1, EventRegistration::count());

        $registration = EventRegistration::query()
            ->where('event_id', $this->event->id_event)
            ->where('player_user_id', $player->id_user)
            ->first();
        $this->assertNotNull($registration);
        $this->assertSame(
            (int) $this->importDivision->id_event_division,
            (int) $registration->event_division_id
        );
        $this->assertSame(77, (int) $registration->payment);
        $this->assertFalse($registration->is_minor);
        $this->assertFalse($registration->is_student);
        $this->assertSame('paid', $registration->payment_status->value);
        $this->assertSame('active', $registration->lifecycle_status->value);
        $this->assertNotNull($registration->paid_at);
    }

    public function test_save_rejects_missing_event_division_id(): void
    {
        Sanctum::actingAs($this->manager);
        $player = User::factory()->create();

        $this->postJson($this->importUrl(), [
            'format' => 'playtomic',
            'rows' => [[
                'index' => 0,
                'first_name' => 'Mara',
                'email' => 'mara@example.test',
                'player_user_id' => $player->id_user,
                'status' => 'mapped',
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_division_id']);

        $this->assertSame(0, EventRegistration::count());
    }

    public function test_save_does_not_create_players(): void
    {
        Sanctum::actingAs($this->manager);
        $playersBefore = User::count();

        $this->postJson($this->importUrl(), $this->importPayload([[
            'index' => 0,
            'first_name' => 'Brand',
            'last_name' => 'New',
            'email' => 'brand.new@example.test',
            'phone' => '',
            'player_user_id' => null,
            'status' => 'unmapped',
        ]]))->assertCreated()
            ->assertJsonPath('data.registered', 0)
            ->assertJsonPath('data.skipped_unmapped', 1);

        $this->assertSame($playersBefore, User::count());
        $this->assertSame(0, EventRegistration::count());
    }

    public function test_save_skips_unmapped_and_null_player_id(): void
    {
        Sanctum::actingAs($this->manager);
        $player = User::factory()->create();

        $response = $this->postJson($this->importUrl(), $this->importPayload([
            [
                'index' => 0,
                'first_name' => 'A',
                'email' => 'a@example.test',
                'player_user_id' => null,
                'status' => 'unmapped',
            ],
            [
                'index' => 1,
                'first_name' => 'B',
                'email' => 'b@example.test',
                'player_user_id' => null,
                'status' => 'mapped',
            ],
            [
                'index' => 2,
                'first_name' => 'C',
                'email' => 'c@example.test',
                'player_user_id' => $player->id_user,
                'status' => 'mapped',
            ],
        ]));

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
        $emailOwner = User::factory()->create();
        $phoneOwner = User::factory()->create();

        $response = $this->postJson($this->importUrl(), $this->importPayload([[
            'index' => 0,
            'first_name' => 'Conflict',
            'email' => 'a@example.test',
            'phone' => '+40700000051',
            'player_user_id' => $emailOwner->id_user,
            'status' => 'conflict',
        ]]));

        $response->assertCreated()
            ->assertJsonPath('data.skipped_conflict', 1)
            ->assertJsonPath('data.registered', 0)
            ->assertJsonPath('data.rows.0.action', 'skipped_conflict');

        $this->assertSame(0, EventRegistration::count());
        $this->assertTrue(User::query()->whereKey($phoneOwner->id_user)->exists());
    }

    public function test_save_skips_already_registered_in_db(): void
    {
        Sanctum::actingAs($this->manager);
        $player = User::factory()->create();
        EventRegistration::factory()->create([
            'event_id' => $this->event->id_event,
            'player_user_id' => $player->id_user,
            'event_division_id' => $this->importDivision->id_event_division,
            'is_student' => false,
            'payment' => 77,
        ]);

        $this->postJson($this->importUrl(), $this->importPayload([[
            'index' => 0,
            'first_name' => 'Mara',
            'email' => 'mara@example.test',
            'player_user_id' => $player->id_user,
            'status' => 'mapped',
        ]]))->assertCreated()
            ->assertJsonPath('data.registered', 0)
            ->assertJsonPath('data.skipped_already_registered', 1)
            ->assertJsonPath('data.rows.0.action', 'skipped_already_registered');

        $this->assertSame(1, EventRegistration::count());
    }

    public function test_save_duplicate_player_id_in_batch_registers_first_skips_second(): void
    {
        Sanctum::actingAs($this->manager);
        $player = User::factory()->create();

        $response = $this->postJson($this->importUrl(), $this->importPayload([
            [
                'index' => 0,
                'first_name' => 'Mara',
                'email' => 'mara@example.test',
                'player_user_id' => $player->id_user,
                'status' => 'mapped',
            ],
            [
                'index' => 1,
                'first_name' => 'Mara',
                'email' => 'mara.alt@example.test',
                'player_user_id' => $player->id_user,
                'status' => 'mapped',
            ],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.registered', 1)
            ->assertJsonPath('data.skipped_already_registered', 1)
            ->assertJsonPath('data.rows.0.action', 'registered')
            ->assertJsonPath('data.rows.1.action', 'skipped_already_registered');

        $this->assertSame(1, EventRegistration::count());
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
        return '/api/v1/manager/events/' . $this->event->id_event . '/registrations/import/preview';
    }

    private function importUrl(): string
    {
        return '/api/v1/manager/events/' . $this->event->id_event . '/registrations/import';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function importPayload(array $rows, ?int $eventDivisionId = null): array
    {
        return [
            'format' => 'playtomic',
            'event_division_id' => $eventDivisionId ?? (int) $this->importDivision->id_event_division,
            'rows' => $rows,
        ];
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

    private function setContacts(User $player, string $email, string $phone): void
    {
        UserEmail::factory()->create([
            'user_id' => $player->id_user,
            'email' => $email,
            'purposes' => ['contact'],
            'is_primary' => true,
        ]);
        UserPhone::factory()->create([
            'user_id' => $player->id_user,
            'phone' => $phone,
            'purposes' => ['contact'],
            'is_primary' => true,
        ]);
    }
}
