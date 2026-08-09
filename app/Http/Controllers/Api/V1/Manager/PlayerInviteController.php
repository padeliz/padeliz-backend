<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Models\Player;
use App\Models\PlayerInvite;
use App\Models\PlayerInviteEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Player Invites",
 *     description="(v1) Manager player invite create/revoke"
 * )
 */
class PlayerInviteController extends V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/manager/player-invites",
     *     summary="Create player invite (revokes any active invite)",
     *     tags={"🐧 Manager: Player Invites"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"player_id"},
     *             @OA\Property(property="player_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Invite created; raw token returned once")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'player_id' => 'required|integer|exists:players,id_player',
        ]);

        $player = Player::query()->findOrFail($request->integer('player_id'));
        $rawToken = Str::random(64);
        $now = time();

        $invite = DB::transaction(function () use ($player, $rawToken, $now, $request) {
            $activeInvites = PlayerInvite::query()
                ->where('player_id', $player->id_player)
                ->active()
                ->get();

            foreach ($activeInvites as $activeInvite) {
                $activeInvite->revoked_at = $now;
                $activeInvite->save();

                PlayerInviteEvent::log(
                    event: PlayerInviteEvent::EVENT_REVOKED,
                    invite: $activeInvite,
                    request: $request,
                    resultCode: 'superseded',
                    actorPlayerId: $request->user()->id_player,
                );
            }

            $invite = PlayerInvite::create([
                'player_id' => $player->id_player,
                'token_hash' => PlayerInvite::hashToken($rawToken),
                'expires_at' => $now + (3 * 24 * 60 * 60),
                'created_by_player_id' => $request->user()->id_player,
            ]);

            PlayerInviteEvent::log(
                event: PlayerInviteEvent::EVENT_CREATED,
                invite: $invite,
                request: $request,
                actorPlayerId: $request->user()->id_player,
            );

            return $invite;
        });

        return $this->successResponse([
            'invite' => [
                'id_invite' => $invite->id_invite,
                'player_id' => $invite->player_id,
                'expires_at' => $invite->expires_at,
                'invite_token' => $rawToken,
            ],
        ], 'Invite created successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/player-invites/{playerInvite}/revoke",
     *     summary="Revoke player invite",
     *     tags={"🐧 Manager: Player Invites"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Invite revoked")
     * )
     * @OA\Delete(
     *     path="/api/v1/manager/player-invites/{playerInvite}",
     *     summary="Revoke player invite",
     *     tags={"🐧 Manager: Player Invites"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Invite revoked")
     * )
     */
    public function revoke(Request $request, PlayerInvite $playerInvite)
    {
        if ($playerInvite->revoked_at !== null) {
            return $this->errorResponse('Invite is already revoked.', 409);
        }

        if ($playerInvite->accepted_at !== null) {
            return $this->errorResponse('Invite was already accepted.', 409);
        }

        $playerInvite->revoked_at = time();
        $playerInvite->save();

        PlayerInviteEvent::log(
            event: PlayerInviteEvent::EVENT_REVOKED,
            invite: $playerInvite,
            request: $request,
            resultCode: 'revoked',
            actorPlayerId: $request->user()->id_player,
        );

        return $this->successResponse([
            'invite' => [
                'id_invite' => $playerInvite->id_invite,
                'player_id' => $playerInvite->player_id,
                'revoked_at' => $playerInvite->revoked_at,
            ],
        ], 'Invite revoked successfully');
    }
}
