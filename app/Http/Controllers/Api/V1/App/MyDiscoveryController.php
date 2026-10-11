<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\MediaSourceResource;
use App\Http\Resources\PlayerFriendsSourceResource;
use App\Models\User;
use App\Services\Discovery\PlayerDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="🐥 App: My Discovery",
 *     description="(v1) How the player found us: media sources and friends. Friends are never required by the API; the Discovery UX asks for them only when the Friends source is selected."
 * )
 */
class MyDiscoveryController extends V1BaseController
{
    public function __construct(
        private readonly PlayerDiscoveryService $discovery,
    ) {
        parent::__construct();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/discovery",
     *     summary="Get my discovery summary (media, friends and what is still missing)",
     *     tags={"🐥 App: My Discovery"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Discovery summary")
     * )
     */
    public function summary(Request $request): JsonResponse
    {
        /** @var User $player */
        $player = $request->user();

        $media = $this->discovery->selectedMedia($player);
        $friends = $this->discovery->friends($player);
        $friendsSelected = $this->discovery->friendsSelected($player);

        return $this->successResponse([
            'media_sources' => MediaSourceResource::collection($media)->resolve(),
            'media_source_ids' => $media->pluck('id_media_source')->map(fn ($id): int => (int) $id)->values()->all(),
            'friends' => PlayerFriendsSourceResource::collection($friends)->resolve(),
            'max_friends' => PlayerDiscoveryService::MAX_FRIENDS,
            'friends_selected' => $friendsSelected,
            'needs_media' => $media->isEmpty(),
            'needs_friends' => $friendsSelected && $friends->isEmpty(),
        ], 'Discovery retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/discovery/media-sources",
     *     summary="List the media source catalog",
     *     tags={"🐥 App: My Discovery"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Media sources")
     * )
     */
    public function catalog(): JsonResponse
    {
        return $this->successResponse(
            MediaSourceResource::collection($this->discovery->catalog())->resolve(),
            'Media sources retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/discovery/media",
     *     summary="Get my selected media sources",
     *     tags={"🐥 App: My Discovery"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Selected media sources")
     * )
     */
    public function showMedia(Request $request): JsonResponse
    {
        return $this->mediaResponse($this->discovery->selectedMedia($request->user()), 'Media selection retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/v1/app/my/discovery/media",
     *     summary="Replace my selected media sources",
     *     description="Full replace; an empty array clears the selection.",
     *     tags={"🐥 App: My Discovery"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"media_source_ids"},
     *             @OA\Property(property="media_source_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Media selection replaced"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function replaceMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'media_source_ids' => ['present', 'array'],
            'media_source_ids.*' => ['integer', 'distinct', 'exists:media_sources,id_media_source'],
        ]);

        $media = $this->discovery->replaceMedia($request->user(), $validated['media_source_ids']);

        return $this->mediaResponse($media, 'Media selection saved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/discovery/friends",
     *     summary="Get my friends sources",
     *     tags={"🐥 App: My Discovery"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Friends sources")
     * )
     */
    public function showFriends(Request $request): JsonResponse
    {
        return $this->friendsResponse($this->discovery->friends($request->user()), 'Friends retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/v1/app/my/discovery/friends",
     *     summary="Replace my friends sources (max 3)",
     *     description="Full replace; each friend needs at least one of friend_player_user_id, full_name, email or phone. An empty array clears the list.",
     *     tags={"🐥 App: My Discovery"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"friends"},
     *             @OA\Property(
     *                 property="friends",
     *                 type="array",
     *                 maxItems=3,
     *                 @OA\Items(
     *                     @OA\Property(property="friend_player_user_id", type="integer", nullable=true),
     *                     @OA\Property(property="full_name", type="string", maxLength=120, nullable=true),
     *                     @OA\Property(property="email", type="string", maxLength=255, nullable=true),
     *                     @OA\Property(property="phone", type="string", maxLength=30, nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Friends replaced"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function replaceFriends(Request $request): JsonResponse
    {
        /** @var User $player */
        $player = $request->user();

        $validator = Validator::make($request->all(), [
            'friends' => ['present', 'array', 'max:' . PlayerDiscoveryService::MAX_FRIENDS],
            'friends.*' => ['array'],
            'friends.*.friend_player_user_id' => ['nullable', 'integer', 'exists:users,id_user', 'not_in:' . $player->id_user],
            'friends.*.full_name' => ['nullable', 'string', 'max:120'],
            'friends.*.email' => ['nullable', 'string', 'email', 'max:255'],
            'friends.*.phone' => ['nullable', 'string', 'max:30'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            foreach ((array) $request->input('friends', []) as $index => $friend) {
                if (! is_array($friend)) {
                    continue;
                }

                $hasIdentifier = false;
                foreach (['friend_player_user_id', 'full_name', 'email', 'phone'] as $key) {
                    $value = $friend[$key] ?? null;
                    if ($value !== null && trim((string) $value) !== '') {
                        $hasIdentifier = true;

                        break;
                    }
                }

                if (! $hasIdentifier) {
                    $validator->errors()->add(
                        "friends.{$index}",
                        'Each friend needs at least one of friend_player_user_id, full_name, email or phone.'
                    );
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated()['friends'];
        ksort($validated);

        $friends = $this->discovery->replaceFriends($player, array_values($validated));

        return $this->friendsResponse($friends, 'Friends saved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/discovery/suggest-friends",
     *     summary="Suggest friends from players who referred me",
     *     tags={"🐥 App: My Discovery"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Suggested friends (referrers from referral touchpoints)")
     * )
     */
    public function suggestFriends(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->discovery->suggestReferrers($request->user())->all(),
            'Friend suggestions retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/discovery/search-players",
     *     summary="Search players to pick as a friend (name, email or phone)",
     *     description="Min 2 characters, max 20 results, never includes me. Contacts are masked; avatar is the App (Google) avatar.",
     *     tags={"🐥 App: My Discovery"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string", minLength=2)),
     *     @OA\Response(
     *         response=200,
     *         description="Matching players",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id_user", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="email_masked", type="string", nullable=true),
     *                     @OA\Property(property="phone_masked", type="string", nullable=true),
     *                     @OA\Property(property="avatar_url", type="string", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Query too short", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function searchPlayers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:' . PlayerDiscoveryService::SEARCH_MIN_LENGTH, 'max:100'],
        ]);

        return $this->successResponse(
            $this->discovery->searchPlayers($request->user(), $validated['q'])->all(),
            'Players retrieved successfully'
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\MediaSource>  $media
     */
    private function mediaResponse($media, string $message): JsonResponse
    {
        return $this->successResponse([
            'media_source_ids' => $media->pluck('id_media_source')->map(fn ($id): int => (int) $id)->values()->all(),
            'media_sources' => MediaSourceResource::collection($media)->resolve(),
        ], $message);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\PlayerFriendsSource>  $friends
     */
    private function friendsResponse($friends, string $message): JsonResponse
    {
        return $this->successResponse([
            'max_friends' => PlayerDiscoveryService::MAX_FRIENDS,
            'friends' => PlayerFriendsSourceResource::collection($friends)->resolve(),
        ], $message);
    }
}
