<?php

declare(strict_types=1);

namespace App\Services\Discovery;

use App\Models\MediaSource;
use App\Models\User;
use App\Models\PlayerFriendsSource;
use App\Models\PlayerMediaSource;
use App\Models\ReferralTouchpoint;
use App\Services\UserIdentity\UserAvatarResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Discovery answers "how did you hear about us": a set of media sources and, when the Friends
 * source is picked, up to three friends. Both are full-replace writes.
 *
 * The API never requires friends: the "Friends selected => at least one friend" rule is a
 * Discovery UX concern only, so `needs_friends` is reported but not enforced on write.
 */
final class PlayerDiscoveryService
{
    public const MAX_FRIENDS = 3;

    public const SEARCH_MIN_LENGTH = 2;

    public const SEARCH_LIMIT = 20;

    public function __construct(
        private readonly UserAvatarResolver $avatars,
    ) {
    }

    /**
     * Find other players by name, email or phone so the player can pick a friend.
     * Contacts are only returned masked; the searcher is never part of the results.
     *
     * @return Collection<int, array{id_user: int, name: string, email_masked: ?string, phone_masked: ?string, avatar_url: ?string}>
     */
    public function searchPlayers(User $searcher, string $query, int $limit = self::SEARCH_LIMIT): Collection
    {
        $term = trim($query);
        if (mb_strlen($term) < self::SEARCH_MIN_LENGTH) {
            return collect();
        }

        $like = '%' . $this->escapeLike($term) . '%';
        // Phones are stored in free form ("+40 712-000-001"); match them ignoring separators too.
        $phoneDigits = preg_replace('/[\s\-().]+/', '', $term) ?? $term;
        $phoneLike = '%' . $this->escapeLike($phoneDigits) . '%';

        $players = User::query()
            ->with(['emails', 'phones', 'identities'])
            ->where('id_user', '!=', $searcher->id_user)
            ->where(function ($query) use ($like, $phoneLike, $phoneDigits): void {
                $query
                    ->whereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?", [$like])
                    ->orWhereExists(function ($sub) use ($like): void {
                        $sub->selectRaw('1')
                            ->from('user_emails')
                            ->whereColumn('user_emails.user_id', 'users.id_user')
                            ->where('user_emails.email', 'like', $like);
                    })
                    ->orWhereExists(function ($sub) use ($like, $phoneLike, $phoneDigits): void {
                        $sub->selectRaw('1')
                            ->from('user_phones')
                            ->whereColumn('user_phones.user_id', 'users.id_user')
                            ->where(function ($phone) use ($like, $phoneLike, $phoneDigits): void {
                                $phone->where('user_phones.phone', 'like', $like);
                                if ($phoneDigits !== '') {
                                    // Raw SQL is not auto-prefixed, so wrap the column through the grammar.
                                    $column = DB::connection()->getQueryGrammar()->wrap('user_phones.phone');
                                    $phone->orWhereRaw(
                                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '-', ''), '(', ''), ')', ''), '.', '') LIKE ?",
                                        [$phoneLike]
                                    );
                                }
                            });
                    });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('id_user')
            ->limit(max(1, min($limit, self::SEARCH_LIMIT)))
            ->get();

        return $players
            ->map(fn (User $player): array => [
                'id_user' => (int) $player->id_user,
                'name' => (string) $player->name,
                'email_masked' => $this->maskEmail($this->primaryContact($player->emails, 'email')),
                'phone_masked' => $this->maskPhone($this->primaryContact($player->phones, 'phone')),
                'avatar_url' => $this->avatars->resolveForApp($player),
            ])
            ->values();
    }

    /**
     * @return Collection<int, MediaSource>
     */
    public function catalog(): Collection
    {
        return MediaSource::query()
            ->orderBy('sort_order')
            ->orderBy('id_media_source')
            ->get();
    }

    /**
     * @return Collection<int, MediaSource>
     */
    public function selectedMedia(User $player): Collection
    {
        return MediaSource::query()
            ->whereIn('id_media_source', function ($query) use ($player): void {
                $query->select('media_source_id')
                    ->from('player_media_sources')
                    ->where('player_user_id', $player->id_user);
            })
            ->orderBy('sort_order')
            ->orderBy('id_media_source')
            ->get();
    }

    /**
     * @param  list<int>  $mediaSourceIds
     * @return Collection<int, MediaSource>
     */
    public function replaceMedia(User $player, array $mediaSourceIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $mediaSourceIds)));

        DB::transaction(function () use ($player, $ids): void {
            PlayerMediaSource::query()
                ->where('player_user_id', $player->id_user)
                ->when($ids !== [], fn ($q) => $q->whereNotIn('media_source_id', $ids))
                ->delete();

            $existing = PlayerMediaSource::query()
                ->where('player_user_id', $player->id_user)
                ->pluck('media_source_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            foreach (array_diff($ids, $existing) as $id) {
                PlayerMediaSource::query()->create([
                    'player_user_id' => $player->id_user,
                    'media_source_id' => $id,
                ]);
            }
        });

        return $this->selectedMedia($player);
    }

    /**
     * @return Collection<int, PlayerFriendsSource>
     */
    public function friends(User $player): Collection
    {
        return PlayerFriendsSource::query()
            ->with('friendPlayer')
            ->where('player_user_id', $player->id_user)
            ->orderBy('id_player_friends_source')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $friends
     * @return Collection<int, PlayerFriendsSource>
     */
    public function replaceFriends(User $player, array $friends): Collection
    {
        DB::transaction(function () use ($player, $friends): void {
            PlayerFriendsSource::query()->where('player_user_id', $player->id_user)->delete();

            foreach ($friends as $friend) {
                PlayerFriendsSource::query()->create([
                    'player_user_id' => $player->id_user,
                    'friend_player_user_id' => $this->blankToNull($friend['friend_player_user_id'] ?? null),
                    'full_name' => $this->blankToNull($friend['full_name'] ?? null),
                    'email' => $this->blankToNull($friend['email'] ?? null),
                    'phone' => $this->blankToNull($friend['phone'] ?? null),
                ]);
            }
        });

        return $this->friends($player);
    }

    /**
     * True when Friends is one of the selected sources (the UX then asks for friends).
     */
    public function friendsSelected(User $player): bool
    {
        return PlayerMediaSource::query()
            ->where('player_user_id', $player->id_user)
            ->whereIn('media_source_id', function ($query): void {
                $query->select('id_media_source')
                    ->from('media_sources')
                    ->where('code', MediaSource::CODE_FRIENDS);
            })
            ->exists();
    }

    /**
     * Distinct players who referred this player, most recent touch first.
     *
     * @return Collection<int, array{friend_player_user_id: int, full_name: string, touched_at: int}>
     */
    public function suggestReferrers(User $player, int $limit = 10): Collection
    {
        $touchpoints = ReferralTouchpoint::query()
            ->with('referrerPlayer')
            ->where('referred_player_user_id', $player->id_user)
            ->where('referrer_player_user_id', '!=', $player->id_user)
            ->orderByDesc('touched_at')
            ->orderByDesc('id_touchpoint')
            ->get();

        return $touchpoints
            ->unique('referrer_player_user_id')
            ->filter(static fn (ReferralTouchpoint $touchpoint): bool => $touchpoint->referrerPlayer !== null)
            ->take($limit)
            ->map(static fn (ReferralTouchpoint $touchpoint): array => [
                'friend_player_user_id' => (int) $touchpoint->referrer_player_user_id,
                'full_name' => (string) $touchpoint->referrerPlayer->name,
                'touched_at' => (int) $touchpoint->touched_at,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, \Illuminate\Database\Eloquent\Model>  $contacts
     */
    private function primaryContact(Collection $contacts, string $attribute): ?string
    {
        $contact = $contacts->firstWhere('is_primary', true) ?? $contacts->first();
        $value = $contact?->{$attribute};

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** `ana.pop@example.com` => `a***@example.com`. */
    private function maskEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $at = strrpos($email, '@');
        if ($at === false || $at === 0) {
            return '***';
        }

        return mb_substr($email, 0, 1) . '***' . substr($email, $at);
    }

    /** `+40712000009` => `+********009` (only the last 3 digits stay visible). */
    private function maskPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '***';
        }

        $visible = strlen($digits) > 3 ? substr($digits, -3) : '';
        $prefix = str_starts_with($phone, '+') ? '+' : '';

        return $prefix . str_repeat('*', max(strlen($digits) - strlen($visible), 0)) . $visible;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    private function blankToNull(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }
}
