<?php

declare(strict_types=1);

namespace App\Services\UserIdentity;

use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Collection;

/**
 * The App avatar comes from Google only: the identity flagged `is_avatar_primary`, else the
 * most recently linked Google identity. There is deliberately no PDF / uploaded-image fallback
 * (those belong to posters and the Manager).
 */
final class UserAvatarResolver
{
    public function resolveForApp(User $player): ?string
    {
        return $this->resolveIdentity($player)?->avatar_url;
    }

    public function resolveIdentity(User $player): ?UserIdentity
    {
        /** @var Collection<int, UserIdentity> $candidates */
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
                ['id_user_identity', 'desc'],
            ])
            ->first();
    }

    /**
     * Marks one Google identity as the avatar owner and clears the flag on the player's others.
     */
    public function setPrimary(User $player, UserIdentity $identity): UserIdentity
    {
        UserIdentity::query()
            ->where('user_id', $player->id_user)
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
     * @return Collection<int, UserIdentity>
     */
    private function googleIdentitiesWithAvatar(User $player): Collection
    {
        $player->loadMissing('identities');

        return $player->identities
            ->filter(static fn (UserIdentity $identity): bool => $identity->provider === UserIdentity::PROVIDER_GOOGLE
                && $identity->avatar_url !== null
                && $identity->avatar_url !== '')
            ->values();
    }
}
