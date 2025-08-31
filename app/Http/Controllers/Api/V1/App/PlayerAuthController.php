<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Models\Player;
use App\Http\Resources\PlayerResource;
use Illuminate\Http\Request;
use Google\Client as GoogleClient;
use App\Exceptions\InvalidGoogleTokenException;
use App\Services\AuthLogger;

/**
 * @OA\Tag(
 *     name="🐥 App: Auth",
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
     *     path="/api/v1/app/auth/google",
     *     summary="Authenticate player with Google",
     *     tags={"🐥 App: Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token"},
     *             @OA\Property(property="token", type="string", description="Google OAuth token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful player authentication",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="object",
     *                         @OA\Property(property="token", type="string"),
     *                         @OA\Property(property="player", ref="#/components/schemas/Player"),
     *                         @OA\Property(property="user_type", type="string", example="player")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication failed",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
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
                'name' => $payload['name'] ?? null,
                'email' => $payload['email'],
                'avatar' => $payload['picture'] ?? null
            ];

            // Find existing player
            $player = Player::where('google_email', $googleUser->email)->first();

            if (!$player) {
                return $this->errorResponse('User not found. Please register first.', 404);
            }

            // Ensure player has player user type
            if (!$player->hasUserType('player')) {
                return $this->errorResponse('User does not have player privileges.', 403);
            }

            // Create Sanctum token
            $token = $player->createToken('player-google-token')->plainTextToken;

            return $this->successResponse([
                'token' => $token,
                'player' => new PlayerResource($player),
                'user_type' => 'player'
            ], 'Player authenticated successfully');
        } catch (\Exception $e) {
            // Log the error with context
            $this->authLogger->logError($e, [
                'token_length' => strlen($request->token ?? ''),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'auth_type' => 'player_google'
            ]);

            return $this->errorResponse('Player authentication failed', 401);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/auth/logout",
     *     summary="Logout player",
     *     tags={"🐥 App: Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Player logged out successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Player logged out successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/auth/me",
     *     summary="Get current player profile",
     *     tags={"🐥 App: Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current player profile",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Player")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function me(Request $request)
    {
        return $this->resourceResponse(
            new PlayerResource($request->user()),
            'Player profile retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/auth/dev-login",
     *     summary="Developer login for player (DEV ONLY)",
     *     tags={"🐥 App: Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player logged in successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="object",
     *                         @OA\Property(property="token", type="string"),
     *                         @OA\Property(property="player", ref="#/components/schemas/Player"),
     *                         @OA\Property(property="user_type", type="string", example="player")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Dev login not available in production",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function devLogin(Request $request)
    {
        if (app()->environment() === 'production') {
            return $this->errorResponse('Dev login not available in production', 403);
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

        return $this->successResponse([
            'token' => $token,
            'player' => new PlayerResource($player),
            'user_type' => 'player'
        ], 'Player logged in successfully');
    }
}
