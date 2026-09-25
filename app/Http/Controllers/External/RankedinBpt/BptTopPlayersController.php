<?php

declare(strict_types=1);

namespace App\Http\Controllers\External\RankedinBpt;

use App\Http\Controllers\Controller;
use App\Integrations\RankedinBpt\Contracts\BptTopPlayersReportBuilder;
use App\Services\Cache\PadelizCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * @OA\Tag(
 *     name="📡 External: RankedIn BPT",
 *     description="Live RankedIn BPT aggregation (no database). Finished events, class mapping, ranking points, and top players per canonical division."
 * )
 *
 * @OA\Schema(
 *     schema="BptRankedinTopPlayersEvent",
 *     @OA\Property(property="eventId", type="integer", example=11345),
 *     @OA\Property(property="eventName", type="string", example="BPT Open"),
 *     @OA\Property(property="startDate", type="string", example="2024-06-01"),
 *     @OA\Property(property="endDate", type="string", example="2024-06-03"),
 *     @OA\Property(property="participantsCount", type="integer", example=120)
 * )
 *
 * @OA\Schema(
 *     schema="BptRankedinTopPlayersEventSummary",
 *     @OA\Property(property="eventId", type="integer"),
 *     @OA\Property(property="eventName", type="string"),
 *     @OA\Property(property="startDate", type="string"),
 *     @OA\Property(property="endDate", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="BptRankedinTopPlayersTeammateSummary",
 *     @OA\Property(property="rankedinId", type="string", example="R-partner-slug"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="bptTotalRankingPoints", type="number", format="float"),
 *     @OA\Property(property="divisionRankingNumber", type="integer", nullable=true, description="1-based rank in this division among all players with ≥1 participation (full standings, not only the returned top N)")
 * )
 *
 * @OA\Schema(
 *     schema="BptRankedinTopPlayersDivisionParticipation",
 *     @OA\Property(property="eventName", type="string"),
 *     @OA\Property(property="eventDivision", type="string", description="Tournament class display name"),
 *     @OA\Property(property="totalTeams", type="integer"),
 *     @OA\Property(property="gotIntoPlayoffs", type="boolean"),
 *     @OA\Property(property="earnedPoints", type="number", format="float"),
 *     @OA\Property(property="teamMate", ref="#/components/schemas/BptRankedinTopPlayersTeammateSummary")
 * )
 *
 * @OA\Schema(
 *     schema="BptRankedinTopPlayersPlayerRow",
 *     @OA\Property(property="rankedinId", type="string", example="R-player-slug"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="bptTotalRankingPoints", type="number", format="float"),
 *     @OA\Property(property="divisionRankingNumber", type="integer", description="1-based rank within this division for the returned page (top take)"),
 *     @OA\Property(
 *         property="eventsOccured",
 *         type="array",
 *         description="Distinct tournaments the player played (for this division slice)",
 *         @OA\Items(ref="#/components/schemas/BptRankedinTopPlayersEventSummary")
 *     ),
 *     @OA\Property(
 *         property="divisionsParticipatedInto",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/BptRankedinTopPlayersDivisionParticipation")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BptRankedinTopPlayersDivisionBlock",
 *     @OA\Property(property="divisionName", type="string", example="Division 1 Men"),
 *     @OA\Property(
 *         property="players",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/BptRankedinTopPlayersPlayerRow")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BptRankedinTopPlayersReport",
 *     @OA\Property(
 *         property="events",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/BptRankedinTopPlayersEvent")
 *     ),
 *     @OA\Property(
 *         property="divisionsRanking",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/BptRankedinTopPlayersDivisionBlock")
 *     ),
 *     @OA\Property(
 *         property="debug",
 *         type="object",
 *         nullable=true,
 *         description="Present only when query debug=1",
 *         @OA\Property(property="classMappings", type="array", @OA\Items(type="object")),
 *         @OA\Property(property="errors", type="array", @OA\Items(type="object")),
 *         @OA\Property(property="rankedinHttpCalls", type="integer", example=37)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BptRankedinTopPlayersError",
 *     @OA\Property(property="message", type="string", example="RankedIn BPT aggregation failed"),
 *     @OA\Property(property="error", type="string", description="Exception or upstream error message")
 * )
 */
final class BptTopPlayersController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/external/bpt-rankedin/top-players",
     *     operationId="externalBptRankedinTopPlayers",
     *     summary="BPT top players by division (live RankedIn)",
     *     description="Aggregates finished organisation events from RankedIn, maps classes to seven BPT divisions, attaches latest BPT ranking points, and returns the top N players per division sorted by total ranking points. Players are keyed by RankedIn slug (rankedinId) only. No caching or database writes.",
     *     tags={"📡 External: RankedIn BPT"},
     *     security={},
     *     @OA\Parameter(
     *         name="take",
     *         in="query",
     *         required=false,
     *         description="Max players returned per division (clamped to server max, default from config)",
     *         @OA\Schema(type="integer", minimum=1, example=20)
     *     ),
     *     @OA\Parameter(
     *         name="debug",
     *         in="query",
     *         required=false,
     *         description="When set to 1, response includes a debug object (class mapping diagnostics, HTTP call counts, errors)",
     *         @OA\Schema(type="string", enum={"1"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Aggregated report",
     *         @OA\JsonContent(ref="#/components/schemas/BptRankedinTopPlayersReport")
     *     ),
     *     @OA\Response(
     *         response=502,
     *         description="Aggregation failed (RankedIn unreachable or unexpected failure)",
     *         @OA\JsonContent(ref="#/components/schemas/BptRankedinTopPlayersError")
     *     )
     * )
     */
    public function __invoke(Request $request, BptTopPlayersReportBuilder $builder): JsonResponse
    {
        /** @var array<string, mixed> $cfg */
        $cfg = config('rankedin_bpt');
        $defaultTake = (int) $cfg['default_take'];
        $maxTake = (int) $cfg['max_take'];
        $take = (int) $request->query('take', $defaultTake);
        $take = min($maxTake, max(1, $take));
        $debug = $request->query('debug') === '1';

        try {
            if ($debug) {
                return response()->json($builder->build($take, true));
            }

            $policy = config('cache_policies.bpt_top_players');
            $payload = Cache::lock('bpt_top_players:' . $take, 120)->block(120, function () use ($builder, $take, $policy) {
                return app(PadelizCache::class)->remember(
                    'bpt:top-players:' . $take,
                    (int) $policy['ttl'],
                    $policy['tags'],
                    fn () => $builder->build($take, false),
                );
            });

            return response()->json($payload);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'RankedIn BPT aggregation failed',
                'error' => $e->getMessage(),
            ], 502);
        }
    }
}
