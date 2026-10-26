<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Exceptions\InvalidGoogleTokenException;
use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\UserIdentityResource;
use App\Models\UserIdentity;
use App\Services\UserIdentity\ContactConflictException;
use App\Services\UserIdentity\GoogleIdTokenVerifier;
use App\Services\UserIdentity\IdentityConflictException;
use App\Services\UserIdentity\LastProviderIdentityException;
use App\Services\UserIdentity\UserAvatarResolver;
use App\Services\UserIdentity\UserIdentityLinker;
use App\Services\UserIdentity\UserIdentityUnlinker;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐥 App: Auth",
 *     description="(v1) Player authentication and session management"
 * )
 */
class PlayerIdentityController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/app/auth/identities",
     *     summary="List linked identities for the current player",
     *     tags={"🐥 App: Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Identities listed"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $identities = $request->user()
            ->identities()
            ->with('email')
            ->orderBy('linked_at')
            ->orderBy('id_user_identity')
            ->get();

        return $this->successResponse(
            UserIdentityResource::collection($identities)->resolve(),
            'Identities retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/auth/identities/{provider}",
     *     summary="Link an identity provider while authenticated",
     *     tags={"🐥 App: Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="provider",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", enum={"google"})
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id_token"},
     *             @OA\Property(property="id_token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Identity linked or already linked"),
     *     @OA\Response(response=401, description="Invalid Google token"),
     *     @OA\Response(response=409, description="Identity or contact conflict"),
     *     @OA\Response(response=422, description="Validation or unsupported provider")
     * )
     */
    public function store(
        Request $request,
        string $provider,
        GoogleIdTokenVerifier $tokenVerifier,
        UserIdentityLinker $linker,
    ) {
        $provider = strtolower($provider);

        if ($provider !== UserIdentity::PROVIDER_GOOGLE) {
            return $this->errorResponse('Unsupported provider. Only google is available in this pass.', 422);
        }

        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            $claims = $tokenVerifier->verifyForApp($request->string('id_token')->toString());
        } catch (InvalidGoogleTokenException) {
            return $this->errorResponse('Player authentication failed', 401);
        }

        $player = $request->user();

        try {
            $result = $linker->link(
                player: $player,
                provider: UserIdentity::PROVIDER_GOOGLE,
                subject: $claims['sub'],
                email: $claims['email'],
                displayName: $claims['name'],
                avatarUrl: $claims['picture'],
                asLogin: true,
            );
        } catch (IdentityConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409, [
                'code' => 'identity_conflict',
            ]);
        } catch (ContactConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409, [
                'code' => 'contact_conflict',
            ]);
        }

        $identity = $result->identity->loadMissing('email');

        return $this->successResponse([
            'identity' => (new UserIdentityResource($identity))->resolve(),
            'result_code' => $result->wasAlreadyLinked() ? 'already_linked' : 'linked',
        ], $result->wasAlreadyLinked()
            ? 'Identity already linked'
            : 'Identity linked successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/app/auth/identities/{playerIdentity}",
     *     summary="Unlink an identity from the current player",
     *     tags={"🐥 App: Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="playerIdentity",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Identity unlinked"),
     *     @OA\Response(response=403, description="Identity belongs to another player"),
     *     @OA\Response(response=422, description="Cannot unlink last identity of provider")
     * )
     */
    public function destroy(
        Request $request,
        UserIdentity $playerIdentity,
        UserIdentityUnlinker $unlinker,
    ) {
        $player = $request->user();

        if ((int) $playerIdentity->user_id !== (int) $player->id_user) {
            return $this->forbiddenResponse('You cannot unlink this identity.');
        }

        try {
            $unlinker->unlink($player, $playerIdentity);
        } catch (LastProviderIdentityException $e) {
            return $this->errorResponse($e->getMessage(), 422, [
                'code' => 'last_provider_identity',
            ]);
        }

        return $this->successResponse(null, 'Identity unlinked successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/auth/identities/{playerIdentity}/avatar-primary",
     *     summary="Use a Google identity as my App avatar",
     *     description="Marks the Google identity as the primary avatar and clears the flag on my other identities.",
     *     tags={"🐥 App: Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerIdentity", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Primary avatar identity updated"),
     *     @OA\Response(response=403, description="Identity belongs to another player"),
     *     @OA\Response(response=422, description="Identity is not a Google identity with an avatar")
     * )
     */
    public function setAvatarPrimary(
        Request $request,
        UserIdentity $playerIdentity,
        UserAvatarResolver $avatars,
    ) {
        $player = $request->user();

        if ((int) $playerIdentity->user_id !== (int) $player->id_user) {
            return $this->forbiddenResponse('You cannot change this identity.');
        }

        if (
            $playerIdentity->provider !== UserIdentity::PROVIDER_GOOGLE
            || $playerIdentity->avatar_url === null
            || $playerIdentity->avatar_url === ''
        ) {
            return $this->errorResponse('Only a Google identity with an avatar can be the primary avatar.', 422, [
                'code' => 'avatar_primary_unsupported',
            ]);
        }

        $identity = $avatars->setPrimary($player, $playerIdentity)->loadMissing('email');

        return $this->successResponse(
            (new UserIdentityResource($identity))->resolve(),
            'Primary avatar identity updated successfully'
        );
    }
}
