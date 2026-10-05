<?php

declare(strict_types=1);

namespace Tests\Unit\Services\EventSlug;

use App\Services\EventSlug\EventSlugGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EventSlugGeneratorTest extends TestCase
{
    private EventSlugGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new EventSlugGenerator();
    }

    public function test_joins_organizer_and_event_with_dashes(): void
    {
        $this->assertSame(
            'padel-club-summer-open',
            $this->generator->generate('Padel Club', 'Summer Open'),
        );
    }

    #[DataProvider('emptyOrganizerProvider')]
    public function test_uses_event_name_only_when_organizer_slug_empty(
        ?string $organizerName,
    ): void {
        $this->assertSame(
            'summer-open',
            $this->generator->generate($organizerName, 'Summer Open'),
        );
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function emptyOrganizerProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'punctuation only' => ['!!!'],
            'whitespace only' => ['   '],
        ];
    }

    public function test_returns_null_when_both_sides_slugify_empty(): void
    {
        $this->assertNull($this->generator->generate(null, null));
        $this->assertNull($this->generator->generate('', ''));
        $this->assertNull($this->generator->generate('!!!', '...'));
        $this->assertNull($this->generator->generate(null, '!!!'));
    }
}
