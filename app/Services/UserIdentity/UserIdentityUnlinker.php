<?php

declare(strict_types=1);

namespace App\Services\UserIdentity;

use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\DB;

final class UserIdentityUnlinker
{
    /**
     * @throws LastProviderIdentityException
     */
    public function unlink(User $player, UserIdentity $identity): void
    {
        $providerCount = UserIdentity::query()
            ->where('user_id', $player->id_user)
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

            $stillReferenced = UserIdentity::query()
                ->where('email_id', $emailId)
                ->exists();

            if ($stillReferenced) {
                return;
            }

            $email = UserEmail::query()->find($emailId);
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
