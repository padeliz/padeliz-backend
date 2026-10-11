<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventCoverImage;
use App\Models\PadelEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<EventCoverImage>
 */
class EventCoverImageFactory extends Factory
{
    protected $model = EventCoverImage::class;

    public function definition(): array
    {
        return [
            'event_id' => PadelEvent::factory(),
            'cover_path' => 'events/factory/cover.png',
            'aspect_ratio' => 1.5,
            'sort_order' => 0,
            'inserted_at' => time(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (EventCoverImage $image): void {
            $disk = (string) config('filesystems.user_uploads_disk', 'public');
            if (! Storage::disk($disk)->exists($image->cover_path)) {
                $png = base64_decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAMAAAACCAYAAACddGYaAAAADklEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
                );
                Storage::disk($disk)->put($image->cover_path, $png !== false ? $png : 'x');
            }
        });
    }
}
