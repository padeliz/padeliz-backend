<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Enums\Benefit\BenefitEntitlementSourceType;
use App\Enums\Benefit\BenefitEntitlementStatus;
use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\BenefitEntitlementResource;
use App\Models\BenefitDefinition;
use App\Models\BenefitEntitlement;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Benefit Entitlements",
 *     description="(v1) Benefit entitlement grants and lifecycle"
 * )
 */
class BenefitEntitlementController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/benefit-entitlements",
     *     summary="List benefit entitlements",
     *     tags={"🐧 Manager: Benefit Entitlements"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $query = BenefitEntitlement::with(['benefitDefinition', 'player']);

        $this->applyAdvancedPagination(
            $query,
            $request,
            BenefitEntitlement::$searchableFields,
            BenefitEntitlement::$filterableFields,
            BenefitEntitlement::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('id_entitlement', 'desc');
        }

        $entitlements = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return BenefitEntitlementResource::collection($entitlements);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/benefit-entitlements/{benefitEntitlement}",
     *     summary="Get benefit entitlement details",
     *     tags={"🐧 Manager: Benefit Entitlements"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="benefitEntitlement", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show(BenefitEntitlement $benefitEntitlement)
    {
        $benefitEntitlement->load(['benefitDefinition', 'player']);

        return $this->resourceResponse(
            new BenefitEntitlementResource($benefitEntitlement),
            'Benefit entitlement retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/benefit-entitlements",
     *     summary="Grant a benefit entitlement",
     *     tags={"🐧 Manager: Benefit Entitlements"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Granted"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'benefit_definition_id' => 'required|integer|exists:benefit_definitions,id_benefit',
            'player_id' => 'required|integer|exists:players,id_player',
            'remaining_uses' => 'nullable|integer|min:0',
            'valid_from' => 'nullable|integer|min:0',
            'valid_until' => 'nullable|integer|min:0',
        ]);

        $benefit = BenefitDefinition::query()->findOrFail($validated['benefit_definition_id']);
        if (! $benefit->is_active) {
            return $this->validationErrorResponse([
                'benefit_definition_id' => ['Benefit definition must be active to grant entitlements.'],
            ]);
        }

        $entitlement = BenefitEntitlement::query()->create([
            'benefit_definition_id' => (int) $validated['benefit_definition_id'],
            'player_id' => (int) $validated['player_id'],
            'remaining_uses' => array_key_exists('remaining_uses', $validated)
                ? $validated['remaining_uses']
                : 1,
            'valid_from' => $validated['valid_from'] ?? time(),
            'valid_until' => $validated['valid_until'] ?? null,
            'source_type' => BenefitEntitlementSourceType::ManagerGrant,
            'source_id' => null,
            'status' => BenefitEntitlementStatus::Active,
        ]);

        $entitlement->load(['benefitDefinition', 'player']);

        return $this->resourceResponse(
            new BenefitEntitlementResource($entitlement),
            'Benefit entitlement granted successfully',
            201
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/benefit-entitlements/{benefitEntitlement}/revoke",
     *     summary="Revoke a benefit entitlement",
     *     tags={"🐧 Manager: Benefit Entitlements"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="benefitEntitlement", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Revoked")
     * )
     */
    public function revoke(BenefitEntitlement $benefitEntitlement)
    {
        if ($benefitEntitlement->status === BenefitEntitlementStatus::Revoked) {
            return $this->validationErrorResponse([
                'status' => ['Entitlement is already revoked.'],
            ]);
        }

        $benefitEntitlement->status = BenefitEntitlementStatus::Revoked;
        $benefitEntitlement->save();
        $benefitEntitlement->load(['benefitDefinition', 'player']);

        return $this->resourceResponse(
            new BenefitEntitlementResource($benefitEntitlement),
            'Benefit entitlement revoked'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/benefit-entitlements/{benefitEntitlement}/extend",
     *     summary="Extend entitlement valid_until",
     *     tags={"🐧 Manager: Benefit Entitlements"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="benefitEntitlement", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Extended")
     * )
     */
    public function extend(Request $request, BenefitEntitlement $benefitEntitlement)
    {
        $validated = $request->validate([
            'valid_until' => 'required|integer|min:0',
        ]);

        $validUntil = (int) $validated['valid_until'];
        if (
            $benefitEntitlement->valid_until !== null
            && $validUntil < (int) $benefitEntitlement->valid_until
        ) {
            return $this->validationErrorResponse([
                'valid_until' => ['New valid_until must be greater than or equal to the current value.'],
            ]);
        }

        $benefitEntitlement->valid_until = $validUntil;
        if (
            $benefitEntitlement->status === BenefitEntitlementStatus::Expired
            && ($benefitEntitlement->valid_from === null || (int) $benefitEntitlement->valid_from <= time())
            && $validUntil >= time()
        ) {
            $benefitEntitlement->status = BenefitEntitlementStatus::Active;
        }
        $benefitEntitlement->save();
        $benefitEntitlement->load(['benefitDefinition', 'player']);

        return $this->resourceResponse(
            new BenefitEntitlementResource($benefitEntitlement),
            'Benefit entitlement extended'
        );
    }
}
