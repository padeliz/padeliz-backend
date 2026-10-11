<?php

declare(strict_types=1);

namespace App\Services\UserIdentity;

use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\DB;

final class UserIdentityLinker
{
    /**
     * Link a provider subject to a player, optionally attaching/upserting an email.
     *
     * @throws IdentityConflictException
     * @throws ContactConflictException
     */
    public function link(
        User $player,
        string $provider,
        string $subject,
        ?string $email = null,
        ?string $displayName = null,
        ?string $avatarUrl = null,
        bool $asLogin = false,
    ): UserIdentityLinkResult {
        $provider = strtolower($provider);
        $subject = (string) $subject;
        $email = $email !== null && $email !== ''
            ? strtolower(trim($email))
            : null;

        $existing = UserIdentity::query()
            ->where('provider', $provider)
            ->where('provider_subject', $subject)
            ->first();

        if ($existing) {
            if ((int) $existing->user_id !== (int) $player->id_user) {
                throw new IdentityConflictException(
                    $provider === UserIdentity::PROVIDER_GOOGLE
                        ? 'This Google account is already linked to another player.'
                        : 'This identity is already linked to another player.'
                );
            }

            $this->refreshAvatarUrl($existing, $avatarUrl);

            if ($asLogin) {
                $existing->touchLastLogin();
            }

            return new UserIdentityLinkResult(
                UserIdentityLinkResult::ALREADY_LINKED,
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
                $emailRow = UserEmail::query()->where('email', $email)->first();

                if ($emailRow && (int) $emailRow->user_id !== (int) $player->id_user) {
                    throw new ContactConflictException('Email is already used by another player.');
                }

                if ($emailRow) {
                    $emailRow->addPurpose('login');
                    $emailRow->verified_at = $emailRow->verified_at ?? time();
                    $emailRow->save();
                } else {
                    $hasPrimary = $player->emails()->where('is_primary', true)->exists();
                    $emailRow = UserEmail::create([
                        'user_id' => $player->id_user,
                        'email' => $email,
                        'purposes' => ['contact', 'login'],
                        'is_primary' => ! $hasPrimary,
                        'verified_at' => time(),
                    ]);
                }
            }

            $now = time();

            return UserIdentity::create([
                'user_id' => $player->id_user,
                'provider' => $provider,
                'provider_subject' => $subject,
                'email_id' => $emailRow?->id_user_email,
                'display_name' => $displayName,
                'avatar_url' => $avatarUrl,
                'linked_at' => $now,
                'last_login_at' => $asLogin ? $now : null,
            ]);
        });

        return new UserIdentityLinkResult(
            UserIdentityLinkResult::LINKED,
            $identity->fresh(['email']) ?? $identity,
        );
    }

    /**
     * Keeps the stored provider avatar in sync with the latest claims (e.g. on re-login).
     * A missing/empty claim never clears an existing avatar.
     */
    public function refreshAvatarUrl(UserIdentity $identity, ?string $avatarUrl): void
    {
        $avatarUrl = $avatarUrl !== null ? trim($avatarUrl) : null;

        if ($avatarUrl === null || $avatarUrl === '' || strlen($avatarUrl) > 512) {
            return;
        }

        if ($identity->avatar_url === $avatarUrl) {
            return;
        }

        $identity->avatar_url = $avatarUrl;
        $identity->save();
    }
}
