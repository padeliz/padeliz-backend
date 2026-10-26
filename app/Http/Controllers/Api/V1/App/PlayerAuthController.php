<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\AppUserResource;
use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserIdentity;
use App\Models\PlayerInvite;
use App\Models\PlayerInviteEvent;
use App\Services\AuthLogger;
use App\Services\UserIdentity\ContactConflictException;
use App\Services\UserIdentity\GoogleIdTokenVerifier;
use App\Services\UserIdentity\IdentityConflictException;
use App\Services\UserIdentity\UserIdentityLinker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="🐥 App: Auth",
 *     description="(v1) Player authentication and session management"
 * )
 */
class PlayerAuthController extends V1BaseController
{
    protected AuthLogger $authLogger;

    public function __construct(AuthLogger $authLogger)
    {
        $this->authLogger = $authLogger;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/auth/invite/redeem",
     *     summary="Redeem player invite and link Google identity",
     *     tags={"🐥 App: Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"invite_token", "provider", "id_token"},
     *             @OA\Property(property="invite_token", type="string"),
     *             @OA\Property(property="provider", type="string", enum={"google"}),
     *             @OA\Property(property="id_token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Invite redeemed / login success"),
     *     @OA\Response(response=401, description="Invalid Google token"),
     *     @OA\Response(response=404, description="Invite not found or inactive"),
     *     @OA\Response(response=409, description="Contact or identity conflict"),
     *     @OA\Response(response=422, description="Validation or unsupported provider")
     * )
     */
    public function redeemInvite(
        Request $request,
        GoogleIdTokenVerifier $tokenVerifier,
        UserIdentityLinker $identityLinker,
    ) {
        $request->validate([
            'invite_token' => 'required|string',
            'provider' => 'required|string',
            'id_token' => 'required|string',
        ]);

        $provider = strtolower($request->string('provider')->toString());

        if ($provider !== UserIdentity::PROVIDER_GOOGLE) {
            PlayerInviteEvent::log(
                event: PlayerInviteEvent::EVENT_REDEEM_FAILED,
                request: $request,
                resultCode: 'unsupported_provider',
                provider: $provider,
            );

            return $this->errorResponse('Unsupported provider. Only google is available in this pass.', 422);
        }

        $invite = PlayerInvite::query()
            ->where('token_hash', PlayerInvite::hashToken($request->string('invite_token')->toString()))
            ->first();

        if (! $invite || ! $invite->isActive()) {
            PlayerInviteEvent::log(
                event: PlayerInviteEvent::EVENT_REDEEM_FAILED,
                invite: $invite,
                request: $request,
                resultCode: 'invite_inactive',
                provider: $provider,
                playerId: $invite?->player_user_id,
            );

            return $this->errorResponse('Invite not found or no longer active.', 404);
        }

        PlayerInviteEvent::log(
            event: PlayerInviteEvent::EVENT_REDEEM_STARTED,
            invite: $invite,
            request: $request,
            provider: $provider,
            actorPlayerUserId: (int) $invite->player_user_id,
        );

        try {
            $claims = $tokenVerifier->verifyForApp($request->string('id_token')->toString());
            $subject = $claims['sub'];
            $email = $claims['email'];
            $displayName = $claims['name'];
            $avatarUrl = $claims['picture'];
            $player = $invite->player;

            try {
                $linkResult = $identityLinker->link(
                    player: $player,
                    provider: UserIdentity::PROVIDER_GOOGLE,
                    subject: $subject,
                    email: $email,
                    displayName: $displayName,
                    avatarUrl: $avatarUrl,
                    asLogin: true,
                );
            } catch (IdentityConflictException $e) {
                PlayerInviteEvent::log(
                    event: PlayerInviteEvent::EVENT_REDEEM_FAILED,
                    invite: $invite,
                    request: $request,
                    resultCode: 'identity_conflict',
                    provider: $provider,
                    providerSubject: $subject,
                    contactKind: $email ? 'email' : null,
                    contactValue: $email,
                    actorPlayerUserId: (int) $invite->player_user_id,
                );

                return $this->errorResponse($e->getMessage(), 409);
            } catch (ContactConflictException $e) {
                PlayerInviteEvent::log(
                    event: PlayerInviteEvent::EVENT_REDEEM_FAILED,
                    invite: $invite,
                    request: $request,
                    resultCode: 'contact_conflict',
                    provider: $provider,
                    providerSubject: $subject,
                    contactKind: 'email',
                    contactValue: $email,
                    actorPlayerUserId: (int) $invite->player_user_id,
                );

                return $this->errorResponse($e->getMessage(), 409);
            }

            if ($linkResult->wasAlreadyLinked()) {
                $linkedPlayer = $linkResult->identity->user;

                if (! $linkedPlayer->hasUserType('player')) {
                    PlayerInviteEvent::log(
                        event: PlayerInviteEvent::EVENT_REDEEM_FAILED,
                        invite: $invite,
                        request: $request,
                        resultCode: 'missing_player_privileges',
                        provider: $provider,
                        providerSubject: $subject,
                        actorPlayerUserId: (int) $linkedPlayer->id_user,
                    );

                    return $this->errorResponse('User does not have player privileges.', 403);
                }

                $token = $linkedPlayer->createToken('player-google-token')->plainTextToken;

                PlayerInviteEvent::log(
                    event: PlayerInviteEvent::EVENT_REDEEM_SUCCEEDED,
                    invite: $invite,
                    request: $request,
                    resultCode: 'already_linked_login',
                    provider: $provider,
                    providerSubject: $subject,
                    contactKind: $email ? 'email' : null,
                    contactValue: $email,
                    actorPlayerUserId: (int) $linkedPlayer->id_user,
                );

                return $this->successResponse([
                    'token' => $token,
                    'user' => new AppUserResource($linkedPlayer->loadMissing(['identities.email'])),
                    'user_type' => 'player',
                ], 'Player authenticated successfully');
            }

            $invite->accepted_at = time();
            $invite->save();

            if (! $player->hasUserType('player')) {
                $player->addUserType('player');
            }

            $token = $player->createToken('player-google-token')->plainTextToken;

            PlayerInviteEvent::log(
                event: PlayerInviteEvent::EVENT_REDEEM_SUCCEEDED,
                invite: $invite->fresh(),
                request: $request,
                resultCode: 'linked',
                provider: $provider,
                providerSubject: $subject,
                contactKind: $email ? 'email' : null,
                contactValue: $email,
                actorPlayerUserId: (int) $player->id_user,
            );

            return $this->successResponse([
                'token' => $token,
                'user' => new AppUserResource($player->fresh()->loadMissing(['identities.email'])),
                'user_type' => 'player',
            ], 'Invite redeemed successfully');
        } catch (\Exception $e) {
            PlayerInviteEvent::log(
                event: PlayerInviteEvent::EVENT_REDEEM_FAILED,
                invite: $invite,
                request: $request,
                resultCode: 'auth_error',
                provider: $provider,
                actorPlayerUserId: (int) $invite->player_user_id,
            );

            $this->authLogger->logError($e, [
                'token_length' => strlen($request->id_token ?? ''),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'auth_type' => 'player_invite_redeem',
                'invite_id' => $invite->id_invite,
            ]);

            return $this->errorResponse('Player authentication failed', 401);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/auth/google",
     *     summary="Authenticate player with Google",
     *     tags={"🐥 App: Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id_token"},
     *             @OA\Property(property="id_token", type="string", description="Google OAuth ID token")
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
     *                         @OA\Property(property="user", ref="#/components/schemas/User"),
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
    public function googleCallback(
        Request $request,
        GoogleIdTokenVerifier $tokenVerifier,
        UserIdentityLinker $identityLinker,
    ) {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            $claims = $tokenVerifier->verifyForApp($request->string('id_token')->toString());

            $identity = UserIdentity::query()
                ->where('provider', UserIdentity::PROVIDER_GOOGLE)
                ->where('provider_subject', $claims['sub'])
                ->first();

            if (! $identity) {
                return $this->errorResponse('User not found. Please register first.', 404);
            }

            $player = $identity->user;

            if (! $player->hasUserType('player')) {
                return $this->errorResponse('User does not have player privileges.', 403);
            }

            $identityLinker->refreshAvatarUrl($identity, $claims['picture'] ?? null);
            $identity->touchLastLogin();

            $token = $player->createToken('player-google-token')->plainTextToken;

            return $this->successResponse([
                'token' => $token,
                'user' => new AppUserResource($player->loadMissing(['identities.email'])),
                'user_type' => 'player',
            ], 'Player authenticated successfully');
        } catch (\Exception $e) {
            $this->authLogger->logError($e, [
                'token_length' => strlen($request->id_token ?? ''),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'auth_type' => 'player_google',
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
     *                     @OA\Property(property="data", ref="#/components/schemas/User")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function me(Request $request)
    {
        $player = $request->user()->loadMissing(['identities.email']);

        return $this->resourceResponse(
            new AppUserResource($player),
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
     *                         @OA\Property(property="user", ref="#/components/schemas/User"),
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
                    'last_name' => 'Player',
                    'user_types' => ['player'],
                    'created_via' => 'app',
                    'created_via_format' => 'devLogin',
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
                    'display_name' => 'Dev Player',
                    'linked_at' => time(),
                    'last_login_at' => time(),
                ]);

                return $created;
            });
        }

        if (! $player->hasUserType('player')) {
            $player->addUserType('player');
        }

        $token = $player->createToken('player-dev-token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => new AppUserResource($player->fresh()->loadMissing(['identities.email'])),
            'user_type' => 'player',
        ], 'Player logged in successfully');
    }
}
