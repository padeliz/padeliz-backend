<?php

declare(strict_types=1);

namespace App\Services\PlayerIdentity;

use App\Models\Player;
use App\Models\PlayerIdentity;
use Illuminate\Support\Collection;

/**
 * The App avatar comes from Google only: the identity flagged `is_avatar_primary`, else the
 * most recently linked Google identity. There is deliberately no PDF / uploaded-image fallback
 * (those belong to posters and the Manager).
 */
final class PlayerAvatarResolver
{
    public function resolveForApp(Player $player): ?string
    {
        return $this->resolveIdentity($player)?->avatar_url;
    }

    public function resolveIdentity(Player $player): ?PlayerIdentity
    {
        /** @var Collection<int, PlayerIdentity> $candidates */
        $candidates = $this->googleIdentitiesWithAvatar($player);

        if ($candidates->isEmpty()) {
            return null;
        }

        $primary = $candidates->firstWhere('is_avatar_primary', true);
        if ($primary !== null) {
            return $primary;
        }

        return $candidates
            ->sortBy([
                ['linked_at', 'desc'],
                ['id_identity', 'desc'],
            ])
            ->first();
    }

    /**
     * Marks one Google identity as the avatar owner and clears the flag on the player's others.
     */
    public function setPrimary(Player $player, PlayerIdentity $identity): PlayerIdentity
    {
        PlayerIdentity::query()
            ->where('player_id', $player->id_player)
            ->whereKeyNot($identity->getKey())
            ->where('is_avatar_primary', true)
            ->update(['is_avatar_primary' => false]);

        if (! $identity->is_avatar_primary) {
            $identity->is_avatar_primary = true;
            $identity->save();
        }

        $player->unsetRelation('identities');

        return $identity;
    }

    /**
     * @return Collection<int, PlayerIdentity>
     */
    private function googleIdentitiesWithAvatar(Player $player): Collection
    {
        $player->loadMissing('identities');

        return $player->identities
            ->filter(static fn (PlayerIdentity $identity): bool => $identity->provider === PlayerIdentity::PROVIDER_GOOGLE
                && $identity->avatar_url !== null
                && $identity->avatar_url !== '')
            ->values();
    }
}
