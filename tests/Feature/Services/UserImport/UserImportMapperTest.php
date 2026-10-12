<?php

declare(strict_types=1);

namespace Tests\Feature\Services\UserImport;

use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserPhone;
use App\Services\UserImport\UserImportMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserImportMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_email_is_case_insensitive_and_beats_name(): void
    {
        $player = $this->playerNamed('Mara', 'Popescu');
        $this->setContacts($player, 'Mara@Example.test', '+40 700 111 111');
        $nameTwin = $this->playerNamed('Mara', 'Popescu');
        $this->setContacts($nameTwin, 'twin@example.test', '+40 700 111 112');

        $mapped = (new UserImportMapper())->mapRows([
            $this->row(0, 'Mara', 'Popescu', 'mara@example.test', '+40700999999'),
        ])[0];

        $this->assertSame('mapped', $mapped['mapping']['status']);
        $this->assertSame('email', $mapped['mapping']['matched_by']);
        $this->assertSame($player->id_user, $mapped['mapping']['player']->id_user);
        $this->assertSame($nameTwin->id_user, $mapped['mapping']['name_matches'][0]->id_user);
    }

    public function test_unique_phone_matches_normalized_form_of_raw_db_spelling(): void
    {
        $player = $this->playerNamed('Vlad', 'Mitu');
        $this->setContacts($player, 'vlad@example.test', '+40 722-111-222');

        $mapped = (new UserImportMapper())->mapRows([
            $this->row(0, 'Someone', 'Else', 'other@example.test', '+40722111222'),
        ])[0];

        $this->assertSame('mapped', $mapped['mapping']['status']);
        $this->assertSame('phone', $mapped['mapping']['matched_by']);
        $this->assertSame($player->id_user, $mapped['mapping']['player']->id_user);
    }

    public function test_two_owners_of_one_normalized_phone_do_not_auto_map(): void
    {
        $a = $this->playerNamed('A', 'One');
        $b = $this->playerNamed('B', 'Two');
        $this->setContacts($a, 'a@example.test', '+40 712 345 678');
        $this->setContacts($b, 'b@example.test', '+40712345678');

        $mapped = (new UserImportMapper())->mapRows([
            $this->row(0, 'C', 'Three', 'c@example.test', '+40712345678'),
        ])[0];

        $this->assertSame('unmapped', $mapped['mapping']['status']);
        $this->assertNull($mapped['mapping']['player']);
        $this->assertNull($mapped['mapping']['matched_by']);
    }

    public function test_name_never_auto_maps_but_fills_name_matches(): void
    {
        $player = $this->playerNamed('Mara', 'Popescu');
        $this->setContacts($player, 'mara@example.test', '+40 700 000 010');

        $mapped = (new UserImportMapper())->mapRows([
            $this->row(0, 'Mara', 'Popescu', 'new@example.test', '+40700000011'),
        ])[0];

        $this->assertSame('unmapped', $mapped['mapping']['status']);
        $this->assertNull($mapped['mapping']['player']);
        $this->assertCount(1, $mapped['mapping']['name_matches']);
        $this->assertSame($player->id_user, $mapped['mapping']['name_matches'][0]->id_user);
    }

    public function test_email_and_phone_to_two_players_is_conflict(): void
    {
        $emailOwner = $this->playerNamed('Email', 'Owner');
        $phoneOwner = $this->playerNamed('Phone', 'Owner');
        $this->setContacts($emailOwner, 'shared@example.test', '+40 700 000 020');
        $this->setContacts($phoneOwner, 'phone@example.test', '+40 700 000 021');

        $mapped = (new UserImportMapper())->mapRows([
            $this->row(0, 'X', 'Y', 'shared@example.test', '+40700000021'),
        ])[0];

        $this->assertSame('conflict', $mapped['mapping']['status']);
        $this->assertNull($mapped['mapping']['player']);
        $this->assertNull($mapped['mapping']['matched_by']);
        $candidateIds = array_map(static fn (User $p) => $p->id_user, $mapped['mapping']['candidates']);
        $this->assertEqualsCanonicalizing(
            [$emailOwner->id_user, $phoneOwner->id_user],
            $candidateIds
        );
    }

    public function test_same_player_on_email_and_phone_is_mapped_by_email(): void
    {
        $player = $this->playerNamed('Same', 'Person');
        $this->setContacts($player, 'same@example.test', '+40 700 000 030');

        $mapped = (new UserImportMapper())->mapRows([
            $this->row(0, 'Same', 'Person', 'same@example.test', '+40700000030'),
        ])[0];

        $this->assertSame('mapped', $mapped['mapping']['status']);
        $this->assertSame('email', $mapped['mapping']['matched_by']);
        $this->assertSame($player->id_user, $mapped['mapping']['player']->id_user);
        $this->assertSame([], $mapped['mapping']['candidates']);
    }

    public function test_name_match_is_accent_insensitive_for_csv_parts(): void
    {
        $player = $this->playerNamed('Ștefan', 'Popescu');
        $this->setContacts($player, 'stefan@example.test', '+40 700 000 099');

        $mapped = (new UserImportMapper())->mapRows([
            $this->row(0, 'Stefan', 'Popescu', 'new@example.test', '+40700000098'),
        ])[0];

        $this->assertSame('unmapped', $mapped['mapping']['status']);
        $this->assertCount(1, $mapped['mapping']['name_matches']);
        $this->assertSame($player->id_user, $mapped['mapping']['name_matches'][0]->id_user);
    }

    /**
     * @return array{index: int, first_name: string, middle_name: ?string, last_name: ?string, email: string, phone: string}
     */
    private function row(int $index, string $first, string $last, string $email, string $phone): array
    {
        return [
            'index' => $index,
            'first_name' => $first,
            'middle_name' => null,
            'last_name' => $last,
            'email' => $email,
            'phone' => $phone,
        ];
    }

    private function playerNamed(string $first, string $last): User
    {
        return User::factory()->create([
            'first_name' => $first,
            'middle_name' => null,
            'last_name' => $last,
        ]);
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
