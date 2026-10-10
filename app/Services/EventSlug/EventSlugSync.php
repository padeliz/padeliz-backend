<?php

declare(strict_types=1);

namespace App\Services\EventSlug;

use App\Models\Organizer;
use App\Models\PadelEvent;

/**
 * Persists computed event slugs via Eloquent (cache observers still run).
 */
class EventSlugSync
{
    public function __construct(
        private readonly EventSlugGenerator $generator,
    ) {
    }

    public function syncForEvent(PadelEvent $event): void
    {
        $event->loadMissing('organizer');

        $slug = $this->generator->generate(
            $event->organizer?->name,
            $event->name !== null ? (string) $event->name : null,
        );

        if ($event->slug === $slug) {
            return;
        }

        $event->forceFill(['slug' => $slug])->save();
    }

    public function syncForOrganizer(Organizer $organizer): void
    {
        $organizerName = $organizer->name !== null ? (string) $organizer->name : null;

        PadelEvent::query()
            ->where('organizer_id', $organizer->id_organizer)
            ->orderBy('id_event')
            ->chunkById(100, function ($events) use ($organizerName): void {
                foreach ($events as $event) {
                    $slug = $this->generator->generate(
                        $organizerName,
                        $event->name !== null ? (string) $event->name : null,
                    );

                    if ($event->slug === $slug) {
                        continue;
                    }

                    $event->forceFill(['slug' => $slug])->save();
                }
            }, 'id_event');
    }
}
