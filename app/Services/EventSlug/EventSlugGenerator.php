<?php

declare(strict_types=1);

namespace App\Services\EventSlug;

use Illuminate\Support\Str;

/**
 * Pure slug composition for padel events.
 *
 * Rules: dash-join organizer + event when organizer slugifies non-empty;
 * otherwise event name only; null when both sides are empty.
 */
final class EventSlugGenerator
{
    public function generate(?string $organizerName, ?string $eventName): ?string
    {
        $organizerSlug = $this->slugify($organizerName);
        $eventSlug = $this->slugify($eventName);

        if ($eventSlug === null) {
            return null;
        }

        if ($organizerSlug !== null) {
            return $organizerSlug.'-'.$eventSlug;
        }

        return $eventSlug;
    }

    private function slugify(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $slug = Str::slug($value);

        return $slug === '' ? null : $slug;
    }
}
