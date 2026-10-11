<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\MediaSource;
use App\Models\User;
use App\Models\UserEmail;
use App\Models\PlayerFriendsSource;
use App\Models\UserIdentity;
use App\Models\PlayerMediaSource;
use App\Models\UserPhone;
use App\Models\ReferralTouchpoint;
use App\Services\Discovery\PlayerDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MyDiscoveryControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/app/my/discovery';

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->player = User::factory()->playerOnly()->withContacts()->create();
        Sanctum::actingAs($this->player);
    }

    private function sourceId(string $code): int
    {
        return (int) MediaSource::query()->where('code', $code)->firstOrFail()->id_media_source;
    }

    public function test_catalog_lists_seeded_media_sources_in_order(): void
    {
        $response = $this->getJson(self::BASE . '/media-sources')->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains(MediaSource::CODE_FRIENDS, $codes);
        $this->assertContains('instagram', $codes);
        $this->assertSame(
            collect($response->json('data'))->sortBy('sort_order')->pluck('code')->values()->all(),
            $codes
        );
    }

    public function test_my_media_is_empty_by_default(): void
    {
        $this->getJson(self::BASE . '/media')
            ->assertOk()
            ->assertJsonPath('data.media_source_ids', []);
    }

    public function test_put_media_replaces_the_selection(): void
    {
        $instagram = $this->sourceId('instagram');
        $tiktok = $this->sourceId('tiktok');
        $friends = $this->sourceId(MediaSource::CODE_FRIENDS);

        $this->putJson(self::BASE . '/media', ['media_source_ids' => [$instagram, $tiktok]])
            ->assertOk()
            ->assertJsonCount(2, 'data.media_sources');

        $this->putJson(self::BASE . '/media', ['media_source_ids' => [$tiktok, $friends]])
            ->assertOk();

        $stored = PlayerMediaSource::query()
            ->where('player_user_id', $this->player->id_user)
            ->pluck('media_source_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        $this->assertSame(collect([$tiktok, $friends])->sort()->values()->all(), $stored);

        $this->getJson(self::BASE . '/media')
            ->assertOk()
            ->assertJsonCount(2, 'data.media_source_ids');
    }

    public function test_patch_media_works_like_put_and_empty_array_clears(): void
    {
        $instagram = $this->sourceId('instagram');

        $this->patchJson(self::BASE . '/media', ['media_source_ids' => [$instagram]])->assertOk();
        $this->assertSame(1, PlayerMediaSource::query()->where('player_user_id', $this->player->id_user)->count());

        $this->putJson(self::BASE . '/media', ['media_source_ids' => []])->assertOk();
        $this->assertSame(0, PlayerMediaSource::query()->where('player_user_id', $this->player->id_user)->count());
    }

    public function test_media_replace_does_not_touch_other_players(): void
    {
        $other = User::factory()->create();
        PlayerMediaSource::factory()->create([
            'player_user_id' => $other->id_user,
            'media_source_id' => $this->sourceId('instagram'),
        ]);

        $this->putJson(self::BASE . '/media', ['media_source_ids' => []])->assertOk();

        $this->assertSame(1, PlayerMediaSource::query()->where('player_user_id', $other->id_user)->count());
    }

    public function test_media_validation(): void
    {
        $instagram = $this->sourceId('instagram');

        $this->putJson(self::BASE . '/media', [])->assertStatus(422);
        $this->putJson(self::BASE . '/media', ['media_source_ids' => [999999]])->assertStatus(422);
        $this->putJson(self::BASE . '/media', ['media_source_ids' => [$instagram, $instagram]])->assertStatus(422);
        $this->putJson(self::BASE . '/media', ['media_source_ids' => 'instagram'])->assertStatus(422);
    }

    public function test_put_friends_replaces_the_list(): void
    {
        $known = User::factory()->create();

        $this->putJson(self::BASE . '/friends', [
            'friends' => [
                ['full_name' => 'Ana Popescu'],
                ['friend_player_user_id' => $known->id_user],
                ['email' => 'bob@example.test', 'phone' => '+40712000009'],
            ],
        ])->assertOk()
            ->assertJsonCount(3, 'data.friends')
            ->assertJsonPath('data.max_friends', 3)
            ->assertJsonPath('data.friends.1.friend_player_user_id', $known->id_user);

        $this->putJson(self::BASE . '/friends', ['friends' => [['phone' => '+40712000010']]])
            ->assertOk()
            ->assertJsonCount(1, 'data.friends');

        $this->assertSame(1, PlayerFriendsSource::query()->where('player_user_id', $this->player->id_user)->count());

        $this->getJson(self::BASE . '/friends')
            ->assertOk()
            ->assertJsonPath('data.friends.0.phone', '+40712000010');
    }

    public function test_friends_are_never_required_by_the_api(): void
    {
        PlayerFriendsSource::factory()->create(['player_user_id' => $this->player->id_user]);

        $this->putJson(self::BASE . '/friends', ['friends' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.friends');

        // Even with the Friends source selected, saving an empty friends list is allowed.
        $this->putJson(self::BASE . '/media', [
            'media_source_ids' => [$this->sourceId(MediaSource::CODE_FRIENDS)],
        ])->assertOk();
        $this->putJson(self::BASE . '/friends', ['friends' => []])->assertOk();
    }

    public function test_friends_max_three(): void
    {
        $friends = array_map(
            static fn (int $i): array => ['full_name' => "Friend {$i}"],
            range(1, 4)
        );

        $this->putJson(self::BASE . '/friends', ['friends' => $friends])->assertStatus(422);
        $this->assertSame(0, PlayerFriendsSource::query()->count());
    }

    public function test_each_friend_needs_at_least_one_identifier(): void
    {
        $this->putJson(self::BASE . '/friends', ['friends' => [['full_name' => 'Ok'], []]])
            ->assertStatus(422);

        $this->putJson(self::BASE . '/friends', ['friends' => [['full_name' => '   ', 'email' => '']]])
            ->assertStatus(422);
    }

    public function test_friend_fields_are_validated(): void
    {
        $this->putJson(self::BASE . '/friends', ['friends' => [['email' => 'not-an-email']]])->assertStatus(422);
        $this->putJson(self::BASE . '/friends', ['friends' => [['friend_player_user_id' => 999999]]])->assertStatus(422);
        $this->putJson(self::BASE . '/friends', [
            'friends' => [['friend_player_user_id' => $this->player->id_user]],
        ])->assertStatus(422);
        $this->putJson(self::BASE . '/friends', ['friends' => [['full_name' => str_repeat('a', 121)]]])->assertStatus(422);
        $this->putJson(self::BASE . '/friends', [])->assertStatus(422);
    }

    public function test_failed_friends_replace_keeps_previous_friends(): void
    {
        PlayerFriendsSource::factory()->create(['player_user_id' => $this->player->id_user, 'full_name' => 'Keep Me']);

        $this->putJson(self::BASE . '/friends', ['friends' => [[]]])->assertStatus(422);

        $this->assertSame(
            ['Keep Me'],
            PlayerFriendsSource::query()->where('player_user_id', $this->player->id_user)->pluck('full_name')->all()
        );
    }

    public function test_summary_reports_missing_pieces(): void
    {
        $this->getJson(self::BASE)
            ->assertOk()
            ->assertJsonPath('data.needs_media', true)
            ->assertJsonPath('data.needs_friends', false)
            ->assertJsonPath('data.max_friends', 3);

        $this->putJson(self::BASE . '/media', [
            'media_source_ids' => [$this->sourceId(MediaSource::CODE_FRIENDS)],
        ])->assertOk();

        $this->getJson(self::BASE)
            ->assertOk()
            ->assertJsonPath('data.needs_media', false)
            ->assertJsonPath('data.friends_selected', true)
            ->assertJsonPath('data.needs_friends', true);

        $this->putJson(self::BASE . '/friends', ['friends' => [['full_name' => 'Ana']]])->assertOk();

        $this->getJson(self::BASE)
            ->assertOk()
            ->assertJsonPath('data.needs_friends', false);
    }

    public function test_suggest_friends_returns_distinct_referrers(): void
    {
        // Explicit empty middle_name: factory `optional(0.2)->firstName()` can otherwise leave a middle name.
        $referrerA = User::factory()->create(['first_name' => 'Alice', 'middle_name' => '', 'last_name' => 'Referrer']);
        $referrerB = User::factory()->create(['first_name' => 'Bob', 'middle_name' => '', 'last_name' => 'Referrer']);

        $this->touch($referrerA, time() - 300);
        $this->touch($referrerA, time() - 200);
        $this->touch($referrerB, time() - 100);
        // A touchpoint for somebody else must not leak.
        ReferralTouchpoint::factory()->create(['referrer_player_user_id' => User::factory()->create()->id_user]);

        $response = $this->getJson(self::BASE . '/suggest-friends')->assertOk();

        $this->assertSame(
            [(int) $referrerB->id_user, (int) $referrerA->id_user],
            collect($response->json('data'))->pluck('friend_player_user_id')->all()
        );
        $this->assertSame('Bob Referrer', $response->json('data.0.full_name'));
    }

    public function test_suggest_friends_is_empty_without_touchpoints(): void
    {
        $this->getJson(self::BASE . '/suggest-friends')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_friend_accepts_phone_as_the_only_identifier(): void
    {
        $this->putJson(self::BASE . '/friends', ['friends' => [['phone' => '+40712000011']]])
            ->assertOk()
            ->assertJsonPath('data.friends.0.phone', '+40712000011')
            ->assertJsonPath('data.friends.0.full_name', null)
            ->assertJsonPath('data.friends.0.email', null);

        $this->assertSame(
            '+40712000011',
            PlayerFriendsSource::query()->where('player_user_id', $this->player->id_user)->value('phone')
        );

        $this->putJson(self::BASE . '/friends', ['friends' => [['phone' => str_repeat('1', 31)]]])
            ->assertStatus(422);
        $this->putJson(self::BASE . '/friends', ['friends' => [['phone' => '   ']]])
            ->assertStatus(422);
    }

    public function test_search_players_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(self::BASE . '/search-players?q=ab')->assertUnauthorized();
    }

    public function test_search_players_enforces_min_length(): void
    {
        User::factory()->create(['first_name' => 'Zed', 'last_name' => 'Zulu']);

        $this->getJson(self::BASE . '/search-players')->assertStatus(422);
        $this->getJson(self::BASE . '/search-players?q=')->assertStatus(422);
        $this->getJson(self::BASE . '/search-players?q=z')->assertStatus(422);
        $this->getJson(self::BASE . '/search-players?q=%20z%20')->assertStatus(422);

        $this->getJson(self::BASE . '/search-players?q=ze')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_search_players_caps_results_at_twenty(): void
    {
        foreach (range(1, 25) as $i) {
            User::factory()->create(['first_name' => 'Capped', 'middle_name' => null, 'last_name' => "Player{$i}"]);
        }

        $this->getJson(self::BASE . '/search-players?q=Capped')
            ->assertOk()
            ->assertJsonCount(PlayerDiscoveryService::SEARCH_LIMIT, 'data');
    }

    public function test_search_players_excludes_self(): void
    {
        $this->player->update(['first_name' => 'Selfish', 'middle_name' => null, 'last_name' => 'Searcher']);
        $other = User::factory()->create(['first_name' => 'Selfish', 'middle_name' => null, 'last_name' => 'Other']);

        $ids = collect($this->getJson(self::BASE . '/search-players?q=Selfish')->assertOk()->json('data'))
            ->pluck('id_user')
            ->all();

        $this->assertSame([(int) $other->id_user], $ids);

        // Searching my own email or phone does not return me either.
        $ownEmail = (string) $this->player->emails()->value('email');
        $this->getJson(self::BASE . '/search-players?q=' . urlencode($ownEmail))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_search_players_matches_by_name(): void
    {
        $match = User::factory()->create(['first_name' => 'Ioana', 'middle_name' => 'Maria', 'last_name' => 'Vasilescu']);
        User::factory()->create(['first_name' => 'Radu', 'middle_name' => null, 'last_name' => 'Ionescu']);

        foreach (['vasil', 'ioana maria', 'Maria Vasilescu', 'IOANA'] as $query) {
            $ids = collect($this->getJson(self::BASE . '/search-players?q=' . urlencode($query))->assertOk()->json('data'))
                ->pluck('id_user')
                ->all();
            $this->assertSame([(int) $match->id_user], $ids, "query: {$query}");
        }
    }

    public function test_search_players_matches_by_any_email(): void
    {
        $match = User::factory()->withPrimaryEmail()->create(['first_name' => 'Mail', 'middle_name' => null, 'last_name' => 'Match']);
        UserEmail::factory()->create([
            'user_id' => $match->id_user,
            'email' => 'secondary.box@unique-domain.test',
            'purposes' => ['contact'],
            'is_primary' => false,
        ]);
        User::factory()->create(['first_name' => 'Mail', 'middle_name' => null, 'last_name' => 'Miss']);

        $ids = collect($this->getJson(self::BASE . '/search-players?q=unique-domain')->assertOk()->json('data'))
            ->pluck('id_user')
            ->all();

        $this->assertSame([(int) $match->id_user], $ids);
    }

    public function test_search_players_matches_by_any_phone_ignoring_separators(): void
    {
        $match = User::factory()->create(['first_name' => 'Phone', 'middle_name' => null, 'last_name' => 'Match']);
        UserPhone::factory()->create([
            'user_id' => $match->id_user,
            'phone' => '+40 712-345-678',
            'is_primary' => true,
        ]);
        $other = User::factory()->create(['first_name' => 'Phone', 'middle_name' => null, 'last_name' => 'Miss']);
        UserPhone::factory()->create(['user_id' => $other->id_user, 'phone' => '+40799000111']);

        foreach (['712-345', '712345', '+40 712'] as $query) {
            $ids = collect($this->getJson(self::BASE . '/search-players?q=' . urlencode($query))->assertOk()->json('data'))
                ->pluck('id_user')
                ->all();
            $this->assertSame([(int) $match->id_user], $ids, "query: {$query}");
        }
    }

    public function test_search_players_treats_like_wildcards_literally(): void
    {
        User::factory()->create(['first_name' => 'Wild', 'middle_name' => null, 'last_name' => 'Card']);

        $this->getJson(self::BASE . '/search-players?q=%25%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::BASE . '/search-players?q=__')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_search_players_payload_masks_contacts(): void
    {
        $target = User::factory()->create(['first_name' => 'Payload', 'middle_name' => null, 'last_name' => 'Person']);
        UserEmail::factory()->create(['user_id' => $target->id_user, 'email' => 'payload.person@example.test']);
        UserPhone::factory()->create(['user_id' => $target->id_user, 'phone' => '+40712000009']);

        $response = $this->getJson(self::BASE . '/search-players?q=Payload')->assertOk();

        $this->assertSame(
            [
                'id_user' => (int) $target->id_user,
                'name' => 'Payload Person',
                'email_masked' => 'p***@example.test',
                'phone_masked' => '+********009',
                'avatar_url' => null,
            ],
            $response->json('data.0')
        );

        $raw = $response->getContent();
        $this->assertStringNotContainsString('payload.person@example.test', (string) $raw);
        $this->assertStringNotContainsString('+40712000009', (string) $raw);
    }

    public function test_search_players_masked_contacts_are_null_when_absent(): void
    {
        $target = User::factory()->create(['first_name' => 'Nocontact', 'middle_name' => null, 'last_name' => 'Person']);

        $this->getJson(self::BASE . '/search-players?q=Nocontact')
            ->assertOk()
            ->assertJsonPath('data.0.id_user', $target->id_user)
            ->assertJsonPath('data.0.email_masked', null)
            ->assertJsonPath('data.0.phone_masked', null);
    }

    public function test_search_players_avatar_prefers_primary_google_then_latest(): void
    {
        $withPrimary = User::factory()->create(['first_name' => 'Avatar', 'middle_name' => null, 'last_name' => 'Primary']);
        UserIdentity::factory()->create([
            'user_id' => $withPrimary->id_user,
            'avatar_url' => 'https://img.test/old-primary.jpg',
            'is_avatar_primary' => true,
            'linked_at' => 100,
        ]);
        UserIdentity::factory()->create([
            'user_id' => $withPrimary->id_user,
            'avatar_url' => 'https://img.test/newer.jpg',
            'is_avatar_primary' => false,
            'linked_at' => 200,
        ]);

        $withLatest = User::factory()->create(['first_name' => 'Avatar', 'middle_name' => null, 'last_name' => 'Latest']);
        UserIdentity::factory()->create([
            'user_id' => $withLatest->id_user,
            'avatar_url' => 'https://img.test/older.jpg',
            'is_avatar_primary' => false,
            'linked_at' => 100,
        ]);
        UserIdentity::factory()->create([
            'user_id' => $withLatest->id_user,
            'avatar_url' => 'https://img.test/latest.jpg',
            'is_avatar_primary' => false,
            'linked_at' => 200,
        ]);
        // The model auto-flags the first avatar identity as primary; clear it to hit the "latest" fallback.
        UserIdentity::query()->where('user_id', $withLatest->id_user)->update(['is_avatar_primary' => false]);

        $withoutGoogle = User::factory()->create(['first_name' => 'Avatar', 'middle_name' => null, 'last_name' => 'None']);

        $data = collect($this->getJson(self::BASE . '/search-players?q=Avatar')->assertOk()->json('data'))
            ->keyBy('id_user');

        $this->assertSame('https://img.test/old-primary.jpg', $data[$withPrimary->id_user]['avatar_url']);
        $this->assertSame('https://img.test/latest.jpg', $data[$withLatest->id_user]['avatar_url']);
        $this->assertNull($data[$withoutGoogle->id_user]['avatar_url']);
    }

    public function test_search_players_does_not_change_suggest_friends(): void
    {
        $referrer = User::factory()->create(['first_name' => 'Sug', 'middle_name' => null, 'last_name' => 'Gest']);
        $this->touch($referrer, time() - 10);

        $this->getJson(self::BASE . '/search-players?q=Sug')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::BASE . '/suggest-friends')
            ->assertOk()
            ->assertJsonPath('data.0.friend_player_user_id', $referrer->id_user);
    }

    private function touch(User $referrer, int $touchedAt): void
    {
        $touchpoint = ReferralTouchpoint::factory()->create([
            'referred_player_user_id' => $this->player->id_user,
            'referrer_player_user_id' => $referrer->id_user,
            'touched_at' => $touchedAt,
        ]);

        $this->assertSame((int) $referrer->id_user, (int) $touchpoint->referrer_player_user_id);
    }
}
