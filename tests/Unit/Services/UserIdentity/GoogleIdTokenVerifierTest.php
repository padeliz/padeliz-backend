<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserIdentity;

use App\Exceptions\InvalidGoogleTokenException;
use App\Services\UserIdentity\GoogleIdTokenVerifier;
use Google\Client as GoogleClient;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class GoogleIdTokenVerifierTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_verify_for_app_throws_when_app_client_id_missing(): void
    {
        config(['services.google.app_client_id' => '']);

        $verifier = new GoogleIdTokenVerifier();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GOOGLE_APP_CLIENT_ID');

        $verifier->verifyForApp('any.token');
    }

    public function test_verify_for_manager_throws_when_manager_client_id_missing(): void
    {
        config(['services.google.manager_client_id' => null]);

        $verifier = new GoogleIdTokenVerifier();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GOOGLE_MANAGER_CLIENT_ID');

        $verifier->verifyForManager('any.token');
    }

    public function test_verify_for_app_uses_app_audience_and_returns_claims(): void
    {
        $appClientId = 'app-audience.apps.googleusercontent.com';
        config([
            'services.google.app_client_id' => $appClientId,
            'services.google.manager_client_id' => 'manager-audience.apps.googleusercontent.com',
        ]);

        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('verifyIdToken')
            ->once()
            ->with('good.token')
            ->andReturn([
                'sub' => 'sub-1',
                'email' => '  Player@Example.COM ',
                'name' => 'Player One',
                'picture' => 'https://example.com/p.png',
            ]);

        $seenAudience = null;
        $verifier = new GoogleIdTokenVerifier(function (string $clientId) use ($googleClient, &$seenAudience) {
            $seenAudience = $clientId;

            return $googleClient;
        });

        $claims = $verifier->verifyForApp('good.token');

        $this->assertSame($appClientId, $seenAudience);
        $this->assertSame([
            'sub' => 'sub-1',
            'email' => 'player@example.com',
            'name' => 'Player One',
            'picture' => 'https://example.com/p.png',
        ], $claims);
    }

    public function test_verify_for_manager_uses_manager_audience(): void
    {
        $managerClientId = 'manager-audience.apps.googleusercontent.com';
        config([
            'services.google.app_client_id' => 'app-audience.apps.googleusercontent.com',
            'services.google.manager_client_id' => $managerClientId,
        ]);

        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('verifyIdToken')
            ->once()
            ->with('mgr.token')
            ->andReturn([
                'sub' => 'mgr-1',
                'email' => 'mgr@example.com',
            ]);

        $seenAudience = null;
        $verifier = new GoogleIdTokenVerifier(function (string $clientId) use ($googleClient, &$seenAudience) {
            $seenAudience = $clientId;

            return $googleClient;
        });

        $claims = $verifier->verifyForManager('mgr.token');

        $this->assertSame($managerClientId, $seenAudience);
        $this->assertSame('mgr-1', $claims['sub']);
        $this->assertSame('mgr@example.com', $claims['email']);
        $this->assertNull($claims['name']);
        $this->assertNull($claims['picture']);
    }

    public function test_verify_for_app_throws_when_token_invalid(): void
    {
        config(['services.google.app_client_id' => 'app-audience.apps.googleusercontent.com']);

        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('verifyIdToken')->once()->andReturn(false);

        $verifier = new GoogleIdTokenVerifier(fn () => $googleClient);

        $this->expectException(InvalidGoogleTokenException::class);

        $verifier->verifyForApp('bad.token');
    }
}
