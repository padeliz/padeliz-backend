<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Player;
use Illuminate\Http\Request;
use Google\Client as GoogleClient;
use App\Exceptions\InvalidGoogleTokenException;
use Illuminate\Support\Facades\App;
use App\Services\AuthLogger;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for authentication"
 * )
 */
class AuthController extends Controller
{
    protected $authLogger;

    public function __construct(AuthLogger $authLogger)
    {
        $this->authLogger = $authLogger;
    }

    /**
     * @OA\Post(
     *     path="/api/auth/google",
     *     summary="Authenticate user with Google",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token"},
     *             @OA\Property(property="token", type="string", description="Google OAuth token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful authentication"
     *     )
     * )
     */
    public function googleCallback(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string'
            ]);

            // Verify token with Google
            $client = new GoogleClient(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($request->token);

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
                    'name' => $googleUser->name,  // Use Google name as initial name
                    'google_id' => $googleUser->id,
                    'google_name' => $googleUser->name,
                    'google_avatar' => $googleUser->avatar
                ]
            );

            // Create Sanctum token
            $token = $player->createToken('google-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'player' => $player
            ]);
        } catch (\Exception $e) {
            // Log the error with context
            $this->authLogger->logError($e, [
                'token_length' => strlen($request->token ?? ''),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Logout current user",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/auth/me",
     *     summary="Get authenticated user details",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current user details",
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
     *     path="/api/dev/auth/login",
     *     summary="[DEV ONLY] Login without token",
     *     description="This endpoint is only available in non-production environments",
     *     tags={"[DEV ONLY]"},
     *     @OA\Response(
     *         response=200,
     *         description="Login successful"
     *     )
     * )
     */
    public function devLogin(Request $request)
    {
        if (App::environment() == 'production') {
            abort(404);
        }

        $player = Player::firstOrCreate(
            ['google_email' => 'dev@example.com'],
            [
                'name' => 'Dev User',
                'google_id' => 'dev_' . rand(1000, 9999),
                'google_name' => 'Dev User',
                'google_avatar' => 'https://via.placeholder.com/150'
            ]
        );

        $token = $player->createToken('dev-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'player' => $player
        ]);
    }
}
