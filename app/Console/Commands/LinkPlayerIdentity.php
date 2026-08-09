<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Player;
use App\Models\PlayerEmail;
use App\Models\PlayerIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LinkPlayerIdentity extends Command
{
    protected $signature = 'players:link-identity
        {player_id : Player ID}
        {provider : Identity provider (google|apple|sms|dev)}
        {subject : Provider subject (e.g. Google sub)}
        {--email= : Optional email to attach/create on the player}';

    protected $description = 'Link an SSO identity to a player (ops bootstrap)';

    public function handle(): int
    {
        $playerId = (int) $this->argument('player_id');
        $provider = strtolower((string) $this->argument('provider'));
        $subject = (string) $this->argument('subject');
        $email = $this->option('email');
        $email = is_string($email) && $email !== '' ? strtolower(trim($email)) : null;

        $allowedProviders = [
            PlayerIdentity::PROVIDER_GOOGLE,
            PlayerIdentity::PROVIDER_APPLE,
            PlayerIdentity::PROVIDER_SMS,
            PlayerIdentity::PROVIDER_DEV,
        ];

        if (! in_array($provider, $allowedProviders, true)) {
            $this->error('Invalid provider. Allowed: ' . implode(', ', $allowedProviders));

            return self::FAILURE;
        }

        $player = Player::query()->find($playerId);
        if (! $player) {
            $this->error("Player {$playerId} not found.");

            return self::FAILURE;
        }

        $existing = PlayerIdentity::query()
            ->where('provider', $provider)
            ->where('provider_subject', $subject)
            ->first();

        if ($existing) {
            if ((int) $existing->player_id === $playerId) {
                $this->info("Identity already linked to player {$playerId} (id_identity={$existing->id_identity}).");

                return self::SUCCESS;
            }

            $this->error("Identity already linked to another player (player_id={$existing->player_id}).");

            return self::FAILURE;
        }

        try {
            $identity = DB::transaction(function () use ($player, $provider, $subject, $email) {
                $emailId = null;

                if ($email) {
                    $emailRow = PlayerEmail::query()->where('email', $email)->first();

                    if ($emailRow && (int) $emailRow->player_id !== (int) $player->id_player) {
                        throw new \RuntimeException("Email {$email} belongs to another player.");
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

                    $emailId = $emailRow->id_player_email;
                }

                return PlayerIdentity::create([
                    'player_id' => $player->id_player,
                    'provider' => $provider,
                    'provider_subject' => $subject,
                    'email_id' => $emailId,
                    'linked_at' => time(),
                ]);
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Linked {$provider}/{$subject} to player {$playerId} (id_identity={$identity->id_identity}).");

        return self::SUCCESS;
    }
}
