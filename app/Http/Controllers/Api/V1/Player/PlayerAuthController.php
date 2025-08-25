<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Models\Player;
use Illuminate\Http\Request;
use Google\Client as GoogleClient;
use App\Exceptions\InvalidGoogleTokenException;
use App\Services\AuthLogger;

/**
 * @OA\Tag(
 *     name="Player: Auth",
 *     description="(v1) Player authentication and session management"
 * )
 */
class PlayerAuthController extends V1BaseController
{
    protected $authLogger;

    public function __construct(AuthLogger $authLogger)
    {
        $this->authLogger = $authLogger;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/player/auth/google",
     *     summary="Authenticate player with Google",
     *     tags={"Player: Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token"},
     *             @OA\Property(property="token", type="string", description="Google OAuth token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful player authentication"
     *     )
     * )
     */
    public function googleCallback(Request $request, \Google\Client $googleClient)
    {
        // Validate request first
        $request->validate([
            'token' => 'required|string'
        ]);

        try {
            // Verify token with Google
            $payload = $googleClient->verifyIdToken($request->token);

            if (!$payload) {
                throw new InvalidGoogleTokenException();
            }

            $googleUser = (object)[
                'id' => $payload['sub'],
                'name' => $payload['name'],
                'email' => $payload['email'],
                'avatar' => $payload['picture']
            ];

            // Find or create player
            $player = Player::firstOrCreate(
                ['google_email' => $googleUser->email],
                [
                    'name' => $googleUser->name,
                    'google_id' => $googleUser->id,
                    'google_name' => $googleUser->name,
                    'google_avatar' => $googleUser->avatar
                ]
            );

            // Ensure player has player user type
            if (!$player->hasUserType('player')) {
                $player->addUserType('player');
            }

            // Create Sanctum token
            $token = $player->createToken('player-google-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'player' => $player,
                'user_type' => 'player'
            ]);
        } catch (\Exception $e) {
            // Log the error with context
            $this->authLogger->logError($e, [
                'token_length' => strlen($request->token ?? ''),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'auth_type' => 'player_google'
            ]);

            return response()->json([
                'message' => 'Player authentication failed',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/player/auth/logout",
     *     summary="Logout player",
     *     tags={"Player: Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Player logged out successfully"
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Player logged out successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/player/auth/me",
     *     summary="Get current player profile",
     *     tags={"Player: Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current player profile",
     *         @OA\JsonContent(ref="#/components/schemas/Player")
     *     )
     * )
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * @OA\Post(
     *     path="/api/v1/player/auth/dev-login",
     *     summary="Developer login for player (DEV ONLY)",
     *     tags={"Player: Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player logged in successfully"
     *     )
     * )
     */
    public function devLogin(Request $request)
    {
        if (app()->environment() === 'production') {
            return response()->json(['message' => 'Dev login not available in production'], 403);
        }

        $request->validate([
            'email' => 'required|email'
        ]);

        $player = Player::where('google_email', $request->email)->first();

        if (!$player) {
            $player = Player::create([
                'name' => 'Dev Player',
                'google_email' => $request->email,
                'google_id' => 'dev_' . time(),
                'google_name' => 'Dev Player',
                'google_avatar' => 'https://example.com/dev-avatar.jpg'
            ]);
        }

        // Ensure player has player user type
        if (!$player->hasUserType('player')) {
            $player->addUserType('player');
        }

        $token = $player->createToken('player-dev-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'player' => $player,
            'user_type' => 'player'
        ]);
    }
}
