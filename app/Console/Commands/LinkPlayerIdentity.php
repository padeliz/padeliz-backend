<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserIdentity;
use App\Services\UserIdentity\ContactConflictException;
use App\Services\UserIdentity\IdentityConflictException;
use App\Services\UserIdentity\UserIdentityLinker;
use Illuminate\Console\Command;

class LinkPlayerIdentity extends Command
{
    protected $signature = 'players:link-identity
        {player_user_id : Player ID}
        {provider : Identity provider (google|apple|sms|dev)}
        {subject : Provider subject (e.g. Google sub)}
        {--email= : Optional email to attach/create on the player}';

    protected $description = 'Link an SSO identity to a player (ops bootstrap)';

    public function handle(UserIdentityLinker $linker): int
    {
        $playerId = (int) $this->argument('player_user_id');
        $provider = strtolower((string) $this->argument('provider'));
        $subject = (string) $this->argument('subject');
        $email = $this->option('email');
        $email = is_string($email) && $email !== '' ? strtolower(trim($email)) : null;

        $allowedProviders = [
            UserIdentity::PROVIDER_GOOGLE,
            UserIdentity::PROVIDER_APPLE,
            UserIdentity::PROVIDER_SMS,
            UserIdentity::PROVIDER_DEV,
        ];

        if (! in_array($provider, $allowedProviders, true)) {
            $this->error('Invalid provider. Allowed: ' . implode(', ', $allowedProviders));

            return self::FAILURE;
        }

        $player = User::query()->find($playerId);
        if (! $player) {
            $this->error("Player {$playerId} not found.");

            return self::FAILURE;
        }

        try {
            $result = $linker->link(
                player: $player,
                provider: $provider,
                subject: $subject,
                email: $email,
                asLogin: false,
            );
        } catch (IdentityConflictException|ContactConflictException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result->wasAlreadyLinked()) {
            $this->info(
                "Identity already linked to player {$playerId} (id_user_identity={$result->identity->id_user_identity})."
            );

            return self::SUCCESS;
        }

        $this->info(
            "Linked {$provider}/{$subject} to player {$playerId} (id_user_identity={$result->identity->id_user_identity})."
        );

        return self::SUCCESS;
    }
}
