<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Models\Player;
use App\Http\Resources\PlayerResource;
use Illuminate\Http\Request;
use Google\Client as GoogleClient;
use App\Exceptions\InvalidGoogleTokenException;
use App\Services\AuthLogger;

/**
 * @OA\Tag(
 *     name="Manager: Auth",
 *     description="(v1) Manager authentication and session management"
 * )
 */
class ManagerAuthController extends V1BaseController
{
    protected $authLogger;

    public function __construct(AuthLogger $authLogger)
    {
        $this->authLogger = $authLogger;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/auth/google",
     *     summary="Authenticate manager with Google",
     *     tags={"Manager: Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token"},
     *             @OA\Property(property="token", type="string", description="Google OAuth token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful manager authentication",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="object",
     *                         @OA\Property(property="token", type="string"),
     *                         @OA\Property(property="player", ref="#/components/schemas/Player"),
     *                         @OA\Property(property="user_type", type="string", example="manager")
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

            // Ensure player has manager user type
            if (!$player->hasUserType('manager')) {
                $player->addUserType('manager');
            }

            // Create Sanctum token
            $token = $player->createToken('manager-google-token')->plainTextToken;

            return $this->successResponse([
                'token' => $token,
                'player' => new PlayerResource($player),
                'user_type' => 'manager'
            ], 'Manager authenticated successfully');
        } catch (\Exception $e) {
            // Log the error with context
            $this->authLogger->logError($e, [
                'token_length' => strlen($request->token ?? ''),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'auth_type' => 'manager_google'
            ]);

            return $this->errorResponse('Manager authentication failed', 401);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/auth/logout",
     *     summary="Logout manager",
     *     tags={"Manager: Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Manager logged out successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Manager logged out successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/auth/me",
     *     summary="Get current manager profile",
     *     tags={"Manager: Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current manager profile",
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
            'Manager profile retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/auth/dev-login",
     *     summary="Developer login for manager (DEV ONLY)",
     *     tags={"Manager: Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Manager logged in successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="object",
     *                         @OA\Property(property="token", type="string"),
     *                         @OA\Property(property="player", ref="#/components/schemas/Player"),
     *                         @OA\Property(property="user_type", type="string", example="manager")
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
                'name' => 'Dev Manager',
                'google_email' => $request->email,
                'google_id' => 'dev_' . time(),
                'google_name' => 'Dev Manager',
                'google_avatar' => 'https://example.com/dev-manager-avatar.jpg'
            ]);
        }

        // Ensure player has manager user type
        if (!$player->hasUserType('manager')) {
            $player->addUserType('manager');
        }

        $token = $player->createToken('manager-dev-token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'player' => new PlayerResource($player),
            'user_type' => 'manager'
        ], 'Manager logged in successfully');
    }
}
