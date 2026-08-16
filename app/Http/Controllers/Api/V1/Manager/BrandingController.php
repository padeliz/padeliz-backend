<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Branding",
 *     description="(v1) Organizer / partner branding options for posters"
 * )
 */
class BrandingController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/branding",
     *     summary="List organizer and partner branding options",
     *     tags={"🐧 Manager: Branding"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Branding options retrieved"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Access denied")
     * )
     */
    public function index()
    {
        return $this->successResponse([
            'organizers' => $this->mapGroup('organizers'),
            'partners' => $this->mapGroup('partners'),
        ], 'Branding options retrieved successfully');
    }

    /**
     * @return list<array{id: int, name: string, image: string}>
     */
    private function mapGroup(string $group): array
    {
        $items = config("padeliz_branding.{$group}", []);
        $mapped = [];

        foreach ($items as $id => $item) {
            $relative = (string) ($item['image'] ?? '');
            $mapped[] = [
                'id' => (int) $id,
                'name' => (string) ($item['name'] ?? ''),
                'image' => $relative === ''
                    ? ''
                    : url('media/' . $relative),
            ];
        }

        return $mapped;
    }
}
