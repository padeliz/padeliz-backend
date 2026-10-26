<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserIdentity;
use App\Services\AuthLogger;
use App\Services\UserIdentity\GoogleIdTokenVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Auth",
 *     description="(v1) Manager authentication and session management"
 * )
 */
class ManagerAuthController extends V1BaseController
{
    protected AuthLogger $authLogger;

    public function __construct(AuthLogger $authLogger)
    {
        $this->authLogger = $authLogger;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/auth/google",
     *     summary="Authenticate manager with Google",
     *     tags={"🐧 Manager: Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id_token"},
     *             @OA\Property(property="id_token", type="string", description="Google OAuth ID token")
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
     *                         @OA\Property(property="user", ref="#/components/schemas/User"),
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
    public function googleCallback(Request $request, GoogleIdTokenVerifier $tokenVerifier)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            $claims = $tokenVerifier->verifyForManager($request->string('id_token')->toString());

            $identity = UserIdentity::query()
                ->where('provider', UserIdentity::PROVIDER_GOOGLE)
                ->where('provider_subject', $claims['sub'])
                ->first();

            if (! $identity) {
                return $this->errorResponse('User not found. Please register first.', 404);
            }

            $player = $identity->user;

            if (! $player->hasUserType('manager')) {
                return $this->errorResponse('User does not have manager privileges.', 403);
            }

            $identity->touchLastLogin();

            $token = $player->createToken('manager-google-token')->plainTextToken;

            return $this->successResponse([
                'token' => $token,
                'user' => new UserResource($player->loadMissing(['emails', 'phones', 'identities'])),
                'user_type' => 'manager',
            ], 'Manager authenticated successfully');
        } catch (\Exception $e) {
            $this->authLogger->logError($e, [
                'token_length' => strlen($request->id_token ?? ''),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'auth_type' => 'manager_google',
            ]);

            return $this->errorResponse('Manager authentication failed', 401);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/auth/logout",
     *     summary="Logout manager",
     *     tags={"🐧 Manager: Auth"},
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
     *     tags={"🐧 Manager: Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current manager profile",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/User")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function me(Request $request)
    {
        return $this->resourceResponse(
            new UserResource($request->user()->loadMissing(['emails', 'phones', 'identities'])),
            'Manager profile retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/auth/dev-login",
     *     summary="Developer login for manager (DEV ONLY)",
     *     tags={"🐧 Manager: Auth"},
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
     *                         @OA\Property(property="user", ref="#/components/schemas/User"),
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
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($request->string('email')->toString()));

        $player = User::query()
            ->whereHas('emails', fn ($q) => $q->where('email', $email))
            ->first();

        if (! $player) {
            $player = DB::transaction(function () use ($email) {
                $created = User::create([
                    'first_name' => 'Dev',
                    'last_name' => 'Manager',
                    'user_types' => ['player', 'manager'],
                ]);

                $emailRow = UserEmail::create([
                    'user_id' => $created->id_user,
                    'email' => $email,
                    'purposes' => ['contact', 'login'],
                    'is_primary' => true,
                    'verified_at' => time(),
                ]);

                UserIdentity::create([
                    'user_id' => $created->id_user,
                    'provider' => UserIdentity::PROVIDER_DEV,
                    'provider_subject' => 'dev:' . $email,
                    'email_id' => $emailRow->id_user_email,
                    'display_name' => 'Dev Manager',
                    'linked_at' => time(),
                    'last_login_at' => time(),
                ]);

                return $created;
            });
        }

        if (! $player->hasUserType('manager')) {
            $player->addUserType('manager');
        }

        $token = $player->createToken('manager-dev-token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($player->fresh()->loadMissing(['emails', 'phones', 'identities'])),
            'user_type' => 'manager',
        ], 'Manager logged in successfully');
    }
}
