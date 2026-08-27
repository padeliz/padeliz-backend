<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Enums\Challenge\ChallengeSessionType;
use App\Http\Controllers\Api\V1\Manager\Concerns\HandlesChallengeState;
use App\Http\Controllers\Controller;
use App\Http\Resources\Challenge\ChallengeSessionResource;
use App\Models\Challenge\ChallengeSession;
use App\Services\Challenge\ChallengePersistenceService;
use App\Services\Challenge\ChallengeSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChallengeController extends Controller
{
    use HandlesChallengeState;

    public function __construct(
        private readonly ChallengeSessionService $sessionService,
        private readonly ChallengePersistenceService $persistenceService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->sessionService->paginate(
            array_filter([
                'type' => $validated['type'] ?? null,
                'status' => $validated['status'] ?? null,
            ]),
            (int) ($validated['per_page'] ?? 15),
        );

        return ChallengeSessionResource::collection($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
        ]);

        $type = ChallengeSessionType::tryFrom($validated['type']);
        if ($type === null) {
            return response()->json(['message' => 'Invalid challenge type.'], 422);
        }

        $response = $this->challengeResponse(function () use ($type) {
            $session = $this->sessionService->create($type);

            return response()->json([
                'data' => new ChallengeSessionResource($session),
            ], 201);
        });

        return $response instanceof JsonResponse ? $response : response()->json($response);
    }

    public function show(ChallengeSession $session): JsonResponse
    {
        return response()->json([
            'data' => new ChallengeSessionResource($session),
        ]);
    }

    public function state(ChallengeSession $session): JsonResponse
    {
        return response()->json([
            'data' => [
                'session' => (new ChallengeSessionResource($session))->resolve(),
                'state_json' => $session->state_json ?? [],
                'state_sequence' => (int) $session->state_sequence,
            ],
        ]);
    }

    public function sync(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'id_challenge_session' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'in:king_of_the_court,smash_contest'],
            'status' => ['required', 'string', 'in:active,completed'],
            'title' => ['required', 'string', 'max:80'],
            'bottom_text' => ['required', 'string', 'max:80'],
            'started_at' => ['required', 'integer'],
            'completed_at' => ['present', 'nullable', 'integer'],
            'inserted_at' => ['required', 'integer'],
            'state_json' => ['required', 'array'],
            'state_sequence' => ['required', 'integer', 'min:0'],
        ]);

        $response = $this->challengeResponse(function () use ($id, $validated) {
            $result = $this->persistenceService->sync($id, $validated);

            return response()->json([
                'data' => new ChallengeSessionResource($result['session']),
            ]);
        });

        return $response instanceof JsonResponse ? $response : response()->json($response);
    }
}
