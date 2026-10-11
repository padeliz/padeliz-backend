<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $coverCollection = $this->relationLoaded('coverImages')
            ? $this->coverImages
            : $this->coverImages()->get();
        $coverImages = $coverCollection
            ->sortBy([
                ['sort_order', 'asc'],
                ['id_event_cover_image', 'asc'],
            ])
            ->values()
            ->map(static fn ($image) => $image->toApiArray())
            ->all();

        return [
            'id_event' => $this->id_event,
            'location_id' => $this->location_id,
            'organizer_id' => $this->organizer_id,
            'include_club_host_on_pdf' => (bool) $this->include_club_host_on_pdf,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->when(
                ! $this->shouldOmitDescription($request),
                $this->description
            ),
            'cover_url' => $this->cover_url,
            'cover_images' => $coverImages,
            'event_starts_at' => $this->event_starts_at,
            'event_ends_at' => $this->event_ends_at,
            'event_nr_of_days' => $this->event_nr_of_days,
            'finalized_at' => $this->finalized_at,
            'registration_allowed_until' => $this->registration_allowed_until,
            'cancellation_allowed_until' => $this->cancellation_allowed_until,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'location' => $this->whenLoaded('location', function () {
                return new LocationResource($this->location);
            }),
            'organizer' => $this->whenLoaded('organizer', function () {
                return $this->organizer ? new OrganizerResource($this->organizer) : null;
            }),
            'event_divisions' => $this->whenLoaded('divisions', function () {
                return EventDivisionResource::collection($this->divisions);
            }),
            'participations' => $this->whenLoaded('participations', function () {
                return EventParticipationResource::collection($this->participations);
            }),
            'lotteries' => $this->whenLoaded('lotteries', function () {
                return EventLotteryResource::collection($this->lotteries);
            }),
            'badges' => $this->when(
                array_key_exists('badges', $this->resource->getAttributes()),
                fn () => $this->badges
            ),
            'has_participated' => $this->when(
                array_key_exists('has_participated', $this->resource->getAttributes()),
                fn () => (bool) $this->resource->getAttribute('has_participated')
            ),
        ];
    }

    private function shouldOmitDescription(Request $request): bool
    {
        return $request->is('api/v1/app/events')
            || $request->is('v1/app/events');
    }
}
