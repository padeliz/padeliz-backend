<?php

declare(strict_types=1);

namespace App\Services\PlayerIdentity;

use App\Models\Player;
use App\Models\PlayerEmail;
use App\Models\PlayerIdentity;
use Illuminate\Support\Facades\DB;

final class PlayerIdentityUnlinker
{
    /**
     * @throws LastProviderIdentityException
     */
    public function unlink(Player $player, PlayerIdentity $identity): void
    {
        $providerCount = PlayerIdentity::query()
            ->where('player_id', $player->id_player)
            ->where('provider', $identity->provider)
            ->count();

        if ($providerCount <= 1) {
            throw new LastProviderIdentityException($identity->provider);
        }

        DB::transaction(function () use ($identity) {
            $emailId = $identity->email_id;
            $identity->delete();

            if ($emailId === null) {
                return;
            }

            $stillReferenced = PlayerIdentity::query()
                ->where('email_id', $emailId)
                ->exists();

            if ($stillReferenced) {
                return;
            }

            $email = PlayerEmail::query()->find($emailId);
            if (! $email) {
                return;
            }

            $purposes = array_values(array_filter(
                $email->purposes ?? [],
                static fn ($purpose) => $purpose !== 'login'
            ));
            $email->purposes = $purposes;
            $email->save();
        });
    }
}
