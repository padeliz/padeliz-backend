<?php

declare(strict_types=1);

namespace App\Services\PlayerIdentity;

use App\Exceptions\InvalidGoogleTokenException;
use Google\Client as GoogleClient;

final class GoogleIdTokenVerifier
{
    public function __construct(private GoogleClient $googleClient)
    {
    }

    /**
     * @return array{sub: string, email: ?string, name: ?string, picture: ?string}
     *
     * @throws InvalidGoogleTokenException
     */
    public function verify(string $idToken): array
    {
        $payload = $this->googleClient->verifyIdToken($idToken);

        if (! $payload) {
            throw new InvalidGoogleTokenException();
        }

        return [
            'sub' => (string) $payload['sub'],
            'email' => isset($payload['email']) ? strtolower(trim((string) $payload['email'])) : null,
            'name' => isset($payload['name']) ? (string) $payload['name'] : null,
            'picture' => isset($payload['picture']) ? (string) $payload['picture'] : null,
        ];
    }
}
