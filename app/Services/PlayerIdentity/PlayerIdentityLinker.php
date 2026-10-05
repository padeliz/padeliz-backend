<?php

declare(strict_types=1);

namespace App\Services\PlayerIdentity;

use App\Models\Player;
use App\Models\PlayerEmail;
use App\Models\PlayerIdentity;
use Illuminate\Support\Facades\DB;

final class PlayerIdentityLinker
{
    /**
     * Link a provider subject to a player, optionally attaching/upserting an email.
     *
     * @throws IdentityConflictException
     * @throws ContactConflictException
     */
    public function link(
        Player $player,
        string $provider,
        string $subject,
        ?string $email = null,
        ?string $displayName = null,
        ?string $avatarUrl = null,
        bool $asLogin = false,
    ): PlayerIdentityLinkResult {
        $provider = strtolower($provider);
        $subject = (string) $subject;
        $email = $email !== null && $email !== ''
            ? strtolower(trim($email))
            : null;

        $existing = PlayerIdentity::query()
            ->where('provider', $provider)
            ->where('provider_subject', $subject)
            ->first();

        if ($existing) {
            if ((int) $existing->player_id !== (int) $player->id_player) {
                throw new IdentityConflictException(
                    $provider === PlayerIdentity::PROVIDER_GOOGLE
                        ? 'This Google account is already linked to another player.'
                        : 'This identity is already linked to another player.'
                );
            }

            if ($asLogin) {
                $existing->touchLastLogin();
            }

            return new PlayerIdentityLinkResult(
                PlayerIdentityLinkResult::ALREADY_LINKED,
                $existing->fresh(['email']) ?? $existing,
            );
        }

        $identity = DB::transaction(function () use (
            $player,
            $provider,
            $subject,
            $email,
            $displayName,
            $avatarUrl,
            $asLogin,
        ) {
            $emailRow = null;

            if ($email !== null) {
                $emailRow = PlayerEmail::query()->where('email', $email)->first();

                if ($emailRow && (int) $emailRow->player_id !== (int) $player->id_player) {
                    throw new ContactConflictException('Email is already used by another player.');
                }

                if ($emailRow) {
                    $emailRow->addPurpose('login');
                    $emailRow->verified_at = $emailRow->verified_at ?? time();
                    $emailRow->save();
                } else {
                    $hasPrimary = $player->emails()->where('is_primary', true)->exists();
                    $emailRow = PlayerEmail::create([
                        'player_id' => $player->id_player,
                        'email' => $email,
                        'purposes' => ['contact', 'login'],
                        'is_primary' => ! $hasPrimary,
                        'verified_at' => time(),
                    ]);
                }
            }

            $now = time();

            return PlayerIdentity::create([
                'player_id' => $player->id_player,
                'provider' => $provider,
                'provider_subject' => $subject,
                'email_id' => $emailRow?->id_player_email,
                'display_name' => $displayName,
                'avatar_url' => $avatarUrl,
                'linked_at' => $now,
                'last_login_at' => $asLogin ? $now : null,
            ]);
        });

        return new PlayerIdentityLinkResult(
            PlayerIdentityLinkResult::LINKED,
            $identity->fresh(['email']) ?? $identity,
        );
    }
}
