<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Club;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\Location;
use App\Models\PadelEvent;
use App\Models\PlayerLevel;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

final class ManagerEventFixtures
{
    public static function manager(array $overrides = []): User
    {
        return User::factory()->manager()->create(array_merge([
            'user_types' => ['player', 'manager'],
        ], $overrides));
    }

    public static function actingManager(array $overrides = []): User
    {
        $manager = self::manager($overrides);
        Sanctum::actingAs($manager);

        return $manager;
    }

    public static function player(array $overrides = []): User
    {
        return User::factory()->playerOnly()->create($overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function event(array $overrides = []): PadelEvent
    {
        return UnixEventFactory::event($overrides);
    }

    /**
     * @return array{0: Club, 1: Location}
     */
    public static function clubWithLocation(): array
    {
        $club = Club::factory()->create([
            'name' => 'Test Club',
            'city' => 'Test City',
        ]);
        $location = Location::factory()->create([
            'club_id' => $club->id_club,
            'name' => 'Test Location',
            'address' => 'Test Address',
        ]);

        return [$club, $location];
    }

    /**
     * Event with club + location when callers need a concrete venue.
     *
     * @param  array<string, mixed>  $eventOverrides
     * @return array{0: Club, 1: Location, 2: PadelEvent}
     */
    public static function eventWithLocation(array $eventOverrides = []): array
    {
        [$club, $location] = self::clubWithLocation();
        $event = UnixEventFactory::event(array_merge([
            'location_id' => $location->id_location,
            'name' => 'Test PadelEvent',
        ], $eventOverrides));

        return [$club, $location, $event];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function eventDivisionFor(PadelEvent $event, array $overrides = []): EventDivision
    {
        return UnixEventFactory::division($event, $overrides);
    }

    /**
     * @param  array<string, mixed>  $divisionOverrides
     * @return array{0: User, 1: PadelEvent, 2: EventDivision}
     */
    public static function actingManagerWithDivision(array $divisionOverrides = []): array
    {
        $manager = self::actingManager();
        $event = self::event();
        $eventDivision = self::eventDivisionFor($event, $divisionOverrides);

        return [$manager, $event, $eventDivision];
    }

    /**
     * App registration context: player with level + event division.
     *
     * @param  array<string, mixed>  $eventOverrides
     * @param  array<string, mixed>  $divisionOverrides
     * @return array{0: User, 1: PadelEvent, 2: EventDivision}
     */
    public static function playerRegistrationContext(
        array $eventOverrides = [],
        array $divisionOverrides = [],
        string $level = '4.50',
    ): array {
        $player = self::player();
        PlayerLevel::factory()->level($level)->create(['player_user_id' => $player->id_user]);

        if (! isset($eventOverrides['event_starts_at'])) {
            $eventOverrides = array_merge(
                UnixEventFactory::eventBounds(now()->addDays(30)->format('Y-m-d')),
                $eventOverrides,
            );
        }
        $event = self::event($eventOverrides);
        $division = Division::factory()->create();
        $eventDivision = self::eventDivisionFor($event, array_merge([
            'division_id' => $division->id_division,
            'division_title' => $division->name,
            'players_limit' => 10,
            'entry_fee' => 55,
        ], $divisionOverrides));

        return [$player, $event, $eventDivision];
    }
}
