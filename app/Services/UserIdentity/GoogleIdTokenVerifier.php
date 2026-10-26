<?php

declare(strict_types=1);

namespace App\Services\UserIdentity;

use App\Exceptions\InvalidGoogleTokenException;
use Google\Client as GoogleClient;
use RuntimeException;

class GoogleIdTokenVerifier
{
    /**
     * @param  (callable(string): GoogleClient)|null  $clientFactory
     */
    public function __construct(private $clientFactory = null)
    {
    }

    /**
     * @return array{sub: string, email: ?string, name: ?string, picture: ?string}
     *
     * @throws InvalidGoogleTokenException
     * @throws RuntimeException
     */
    public function verifyForApp(string $idToken): array
    {
        return $this->verify(
            $idToken,
            $this->requireClientId(config('services.google.app_client_id'), 'GOOGLE_APP_CLIENT_ID')
        );
    }

    /**
     * @return array{sub: string, email: ?string, name: ?string, picture: ?string}
     *
     * @throws InvalidGoogleTokenException
     * @throws RuntimeException
     */
    public function verifyForManager(string $idToken): array
    {
        return $this->verify(
            $idToken,
            $this->requireClientId(config('services.google.manager_client_id'), 'GOOGLE_MANAGER_CLIENT_ID')
        );
    }

    /**
     * @return array{sub: string, email: ?string, name: ?string, picture: ?string}
     *
     * @throws InvalidGoogleTokenException
     */
    private function verify(string $idToken, string $audience): array
    {
        $payload = $this->makeClient($audience)->verifyIdToken($idToken);

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

    private function requireClientId(mixed $clientId, string $envName): string
    {
        $normalized = is_string($clientId) ? trim($clientId) : '';

        if ($normalized === '') {
            throw new RuntimeException("Google OAuth is not configured: {$envName} is required.");
        }

        return $normalized;
    }

    private function makeClient(string $clientId): GoogleClient
    {
        if ($this->clientFactory !== null) {
            return ($this->clientFactory)($clientId);
        }

        return new GoogleClient(['client_id' => $clientId]);
    }
}
