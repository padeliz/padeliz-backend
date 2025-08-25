<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Models\PlayerEligibility;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Manager: Eligibilities",
 *     description="(v1) Eligibility management and operations"
 * )
 */
class EligibilityController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/eligibility",
     *     summary="Get all eligibility records",
     *     tags={"Manager: Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all eligibility records"
     *     )
     * )
     */
    public function index()
    {
        $eligibilities = PlayerEligibility::with(['player'])->get();
        return response()->json($eligibilities);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/eligibility/{eligibility}",
     *     summary="Update eligibility status",
     *     tags={"Manager: Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="eligibility",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"approved", "rejected", "pending"}),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Eligibility updated successfully"
     *     )
     * )
     */
    public function update(Request $request, PlayerEligibility $eligibility)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,pending',
            'notes' => 'nullable|string'
        ]);

        $eligibility->update($request->all());

        return response()->json($eligibility);
    }
}
