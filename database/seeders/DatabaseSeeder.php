<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Court;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventDivisionSchedule;
use App\Models\EventLottery;
use App\Models\EventLotteryLucky;
use App\Models\EventLotteryPrize;
use App\Models\EventLotteryRule;
use App\Models\EventParticipation;
use App\Models\Level;
use App\Models\Location;
use App\Models\Organizer;
use App\Models\PadelEvent;
use App\Models\Player;
use App\Models\PlayerEmail;
use App\Models\PlayerIdentity;
use App\Models\PlayerLevel;
use App\Models\PlayerLevelAssessment;
use App\Models\PlayerPhone;
use App\Models\PlayerProfileImage;
use App\Models\Prize;
use App\Models\Sponsor;
use App\Models\SponsorPrize;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Dev database seeding: truncates core tables, rebuilds synthetic clubs/events/players/player levels, etc.
 *
 * Upload-related behaviour:
 * - Player profile images: {@see PlayerFactory} afterCreating writes files under `players/{id}/profile/`
 *   on `filesystems.user_uploads_disk` (skipped in `testing`). Seeder clears `storage/app/public/players`
 *   when that directory exists.
 * - Player levels: {@see createPlayerLevels()} seeds assessments and the levels they approved (no files).
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        if (App::environment() == 'production') {
            $this->command->error('Seeding is not allowed in production!');

            return;
        }

        $faker = Faker::create();

        $this->command->info('Clearing existing data...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        EventLotteryLucky::truncate();
        EventLotteryRule::truncate();
        EventLotteryPrize::truncate();
        EventLottery::truncate();
        SponsorPrize::truncate();
        DB::table('sponsor_players')->truncate();
        Sponsor::truncate();
        Prize::truncate();
        EventParticipation::truncate();
        EventDivisionSchedule::truncate();
        EventDivision::truncate();
        PlayerLevel::truncate();
        PlayerLevelAssessment::truncate();
        PlayerProfileImage::truncate();
        PlayerIdentity::query()->delete();
        PlayerPhone::query()->delete();
        PlayerEmail::query()->delete();
        Player::truncate();
        PadelEvent::truncate();
        Organizer::truncate();
        Court::truncate();
        Location::truncate();
        Division::truncate();
        Club::truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Clear seeded profile image files from the public disk (dev only)
        $profileDir = storage_path('app/public/players');
        if (File::isDirectory($profileDir)) {
            $this->command->info('Clearing storage/app/public/players...');
            File::cleanDirectory($profileDir);
        }

        // Create specific managers with contacts/identities for local smoke login
        $this->command->info('Creating specific managers...');
        $specificManagers = collect([
            $this->createNamedPlayer(
                'Arsh Padel Minitour',
                'arsh.padel.minitour@gmail.com',
                'ARSH Padel Minitour',
                ['player', 'manager']
            ),
            $this->createNamedPlayer(
                'Arshavin Padeliz',
                'arshavin@padeliz.ro',
                'Arshavin (Padeliz)',
                ['player', 'manager']
            ),
        ]);

        // Create specific players
        $this->command->info('Creating specific players...');
        $specificPlayers = collect([
            $this->createNamedPlayer('Arsavin Wallentyn', 'arsavinwallentyn@gmail.com', 'Arsavin Wallentyn', ['player']),
            $this->createNamedPlayer('Raluca Mihale 2003', 'ralucamihale2003@gmail.com', 'Raluca Mihale 2003', ['player']),
            $this->createNamedPlayer('Raluca Mihale', 'ralucamihale@gmail.com', 'Raluca Mihale', ['player']),
            $this->createNamedPlayer('Valentin Arsavin', 'valentin.arsavin@bosromania.ro', 'Valentin Arsavin (BOS)', ['player']),
        ]);

        $this->command->info('Creating clubs...');
        $clubs = collect([
            Club::factory()->create(['name' => 'Magic Padel', 'city' => 'Bucharest']),
            Club::factory()->create(['name' => 'PadelMania', 'city' => 'Bucharest']),
            Club::factory()->create(['name' => 'Padel One', 'city' => 'Bucharest']),
            Club::factory()->create(['name' => 'Padel World', 'city' => 'Bucharest']),
        ]);

        $this->command->info('Creating organizers...');
        $now = time();
        Organizer::query()->create([
            'name' => 'ARSH Padel MiniTour',
            'logo_path' => null,
            'show_event_label' => true,
            'inserted_at' => $now,
        ]);
        Organizer::query()->create([
            'name' => 'Bucharest Padel Tour',
            'logo_path' => null,
            'show_event_label' => false,
            'inserted_at' => $now,
        ]);

        $this->command->info('Creating locations and courts...');
        $locations = collect();
        foreach ($clubs as $club) {
            $clubLocations = Location::factory()
                ->count(\rand(1, 2))
                ->forClub($club)
                ->create([
                    'name' => substr($club->name . ' ' . $faker->unique()->streetName(), 0, 30),
                ]);
            $locations = $locations->merge($clubLocations);

            foreach ($clubLocations as $location) {
                Court::factory()
                    ->count(\rand(2, 4))
                    ->create(['location_id' => $location->id_location]);
            }
        }

        $this->command->info('Creating divisions...');
        // MiniTour Couples must stay id 5 (fixed-partner Couples for seed/demo only; schedule APIs ignore it).
        $divisions = collect([
            Division::factory()->create(['name' => 'Students Rookies', 'color' => '#ffff00']),
            Division::factory()->create(['name' => 'Students Beginners', 'color' => '#ffa500']),
            Division::factory()->create(['name' => 'Students Novice', 'color' => '#cb2b2b']),
            Division::factory()->create(['name' => 'Kids', 'color' => '#7ab857']),
            tap(Division::factory()->make([
                'name' => DivisionSeeder::MINITOUR_COUPLES_NAME,
                'color' => '#ff0000',
            ]), static function (Division $division): void {
                $division->id_division = DivisionSeeder::MINITOUR_COUPLES_ID;
                $division->save();
            }),
            Division::factory()->create(['name' => 'Adults Explorers', 'color' => '#7ab857']),
            Division::factory()->create(['name' => 'Adults Learners', 'color' => '#89bdce']),
        ]);

        $this->command->info('Creating additional players...');
        $players = collect();

        // Start with our specific players and managers
        $players = $players->merge($specificPlayers)->merge($specificManagers);

        // Create regular players (80% of total)
        $regularPlayersCount = (int) (\rand(15, 20) * 0.8);
        $players = $players->merge(
            Player::factory()->count($regularPlayersCount)->playerOnly()->withContacts()->create()
        );

        // Create managers (20% of total) — no admin role in seed data
        $managersCount = max(1, (int) (\rand(15, 20) * 0.2));
        $players = $players->merge(
            Player::factory()->count($managersCount)->manager()->withContacts()->create()
        );

        $this->command->info('Creating past editions...');
        $pastEditions = $this->createEditions(0, \rand(10, 20), Carbon::now()->subMonths(16), Carbon::now(), $locations);

        $this->command->info('Creating ongoing editions...');
        $ongoingEditions = $this->createEditions($pastEditions->count(), \rand(1, 2), Carbon::now(), Carbon::now(), $locations);

        $this->command->info('Creating future editions...');
        $futureEditions = $this->createEditions($ongoingEditions->count() + $pastEditions->count(), \rand(6, 10), Carbon::now(), Carbon::now()->addMonths(3), $locations);

        // Set the first edition for each division after editions are created
        $this->command->info('Setting first editions for divisions...');
        $allEditions = $pastEditions->merge($ongoingEditions)->merge($futureEditions);
        foreach ($divisions as $division) {
            $division->update(['its_first_event_id' => $allEditions->random()->id_event]);
        }

        $this->command->info('Creating prizes and sponsors...');
        $prizes = Prize::factory()->count(5)->create();

        $sponsors = collect();
        foreach ($clubs->take(2) as $club) {
            $sponsor = Sponsor::factory()->forClub($club)->create();
            $sponsor->syncPlayerIds($players->random(min(2, $players->count()))->pluck('id_player')->all());
            $sponsors->push($sponsor->fresh(['players']));

            SponsorPrize::factory()->create([
                'sponsor_id' => $sponsor->id_sponsor,
                'prize_id' => $prizes->random()->id_prize,
                'media_path' => 'prizes-from-clubs/' . $club->id_club . '/media/seed.jpg',
                'box_1_text' => 'Offered by ' . $club->name,
            ]);
        }

        $organizerSponsor = Sponsor::factory()->create(['name' => 'Padeliz Partners', 'club_id' => null]);
        $sponsors->push($organizerSponsor);
        SponsorPrize::factory()->create([
            'sponsor_id' => $organizerSponsor->id_sponsor,
            'prize_id' => $prizes->first()->id_prize,
            'media_path' => null,
            'box_1_text' => null,
        ]);

        $this->command->info('Creating edition divisions and participations...');
        foreach ($pastEditions->merge($futureEditions) as $event) {
            $this->createEventDivisionsAndParticipations($event, $players);
        }

        $this->command->info('Creating lotteries, prize lines, rules and luckies...');
        $this->createLotteriesAndLuckies($pastEditions, $prizes, $sponsors, $faker);

        $this->command->info('Creating player level assessments and levels...');
        $this->createPlayerLevels($players, $faker);
    }

    /**
     * Create a named player with live-like contacts and Google identity.
     *
     * @param  list<string>  $userTypes
     */
    private function createNamedPlayer(string $name, string $email, string $displayName, array $userTypes): Player
    {
        $parts = \App\Support\PlayerNameSplitter::split($name);

        $player = Player::factory()->create([
            'first_name' => $parts['first_name'],
            'middle_name' => $parts['middle_name'],
            'last_name' => $parts['last_name'],
            'user_types' => $userTypes,
        ]);

        $playerEmail = PlayerEmail::create([
            'player_id' => $player->id_player,
            'email' => $email,
            'purposes' => ['login', 'contact'],
            'is_primary' => true,
            'verified_at' => time(),
        ]);

        PlayerPhone::create([
            'player_id' => $player->id_player,
            'phone' => '+407' . str_pad((string) $player->id_player, 8, '0', STR_PAD_LEFT),
            'purposes' => ['contact'],
            'is_primary' => true,
        ]);

        PlayerIdentity::create([
            'player_id' => $player->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'seed_google_' . $player->id_player,
            'email_id' => $playerEmail->id_player_email,
            'display_name' => $displayName,
            'linked_at' => time(),
        ]);

        return $player->fresh(['emails', 'phones', 'identities']);
    }

    private function createEditions(int $index_nr, int $count, Carbon $startDate, Carbon $endDate, $locations)
    {
        $editions = [];

        // Ensure we have locations before creating editions
        if ($locations->isEmpty()) {
            $this->command->error('No locations found. Cannot create editions.');

            return collect();
        }

        $organizerId = Organizer::query()
            ->where('name', 'ARSH Padel MiniTour')
            ->value('id_organizer');

        for ($i = 0; $i < $count; $i++) {
            $arriving_time = Carbon::createFromTime(\rand(12, 19), 30);

            $editions[] = PadelEvent::factory()->forLocation($locations->random())->create([
                'name' => '#' . ($index_nr + $i + 1),
                'organizer_id' => $organizerId,
                'include_club_host_on_pdf' => true,
                'date' => Carbon::parse($startDate)->addDays(\rand(0, (int) Carbon::parse($endDate)->diffInDays($startDate))),
                'arriving_time' => $arriving_time->format('H:i:s'),
                'closing_time' => $arriving_time->addHours(\rand(2, 4))->format('H:i:s'),
            ]);
        }

        return collect($editions);
    }

    private function createEventDivisionsAndParticipations($event, $players)
    {
        // Get random divisions for this edition
        $selectedDivisions = Division::inRandomOrder()->take(\rand(2, 3))->get();

        foreach ($selectedDivisions->values() as $skillOrder => $division) {
            // Create division for this edition (courts live on courts + pivot, not on event_divisions)
            $eventDivision = EventDivision::factory()->create([
                'event_id' => $event->id_event,
                'division_id' => $division->id_division,
                'division_title' => $division->name,
                'players_limit' => \rand(8, 16),
                'skill_order' => $skillOrder,
                'playing_start_time' => Carbon::createFromTime(\rand(12, 19), 30)->format('H:i:s'),
                'playing_end_time' => Carbon::createFromTime(\rand(16, 21), 30)->format('H:i:s'),
            ]);

            // Schedule knobs live on their own row, one per edition division.
            EventDivisionSchedule::factory()->create([
                'event_division_id' => $eventDivision->id_event_division,
                'include_scores' => $division->name !== 'Kids',
                'has_demonstrative_match' => $division->name == 'Students Novice',
                'allow_replacements' => $division->name !== DivisionSeeder::MINITOUR_COUPLES_NAME
                    && (int) $division->id_division !== DivisionSeeder::MINITOUR_COUPLES_ID,
                'points_per_match' => $division->name === 'Kids' ? null : 1,
                'final_points_target' => 24,
                'include_final' => $division->name !== 'Kids',
                'adjust' => 0,
            ]);

            // Create 6-12 participations for this edition division (unique players)
            $participationCount = \min(\rand(6, 12), $players->count());
            $selectedPlayers = $players->random($participationCount);
            foreach ($selectedPlayers->values() as $i => $player) {
                EventParticipation::factory()->create([
                    'event_id' => $event->id_event,
                    'event_division_id' => $eventDivision->id_event_division,
                    'player_id' => $player->id_player,
                    'sort_order' => $i,
                    'is_collecting_points' => true,
                ]);
            }
        }
    }

    private function createLotteriesAndLuckies($editions, $prizes, $sponsors, $faker)
    {
        foreach ($editions as $event) {
            $lotteryCount = \rand(1, 2);
            $lotteries = collect();
            for ($i = 0; $i < $lotteryCount; $i++) {
                $lotteries->push(EventLottery::factory()->create([
                    'event_id' => $event->id_event,
                    'name' => $i === 0 ? 'Main draw' : 'Kids raffle',
                    'display_order' => $i,
                ]));
            }

            $eventDivisions = EventDivision::where('event_id', $event->id_event)->get();

            foreach ($lotteries as $lotteryIndex => $lottery) {
                $prize = $prizes->random();
                $useSponsor = $lotteryIndex === 0 && $sponsors->isNotEmpty() && $faker->boolean(50);
                $line = EventLotteryPrize::factory()->create([
                    'lottery_id' => $lottery->id_lottery,
                    'prize_id' => $prize->id_prize,
                    'prize_quantity' => \rand(1, 5),
                    'sponsor_id' => $useSponsor ? $sponsors->random()->id_sponsor : null,
                    'display_order' => 0,
                ]);

                $rules = collect();
                $remaining = (int) $line->prize_quantity;

                $openQty = max(1, (int) ceil($remaining / 2));
                $rules->push(EventLotteryRule::factory()->create([
                    'lottery_prize_id' => $line->id_lottery_prize,
                    'rule_prize_quantity' => $openQty,
                    'orderliness' => 0,
                    'event_division_id' => null,
                    'has_social_status' => null,
                ]));
                $remaining -= $openQty;

                if ($remaining > 0) {
                    $rules->push(EventLotteryRule::factory()->create([
                        'lottery_prize_id' => $line->id_lottery_prize,
                        'rule_prize_quantity' => $remaining,
                        'orderliness' => 1,
                        'event_division_id' => $eventDivisions->isNotEmpty()
                            ? $faker->optional()->randomElement($eventDivisions->pluck('id_event_division')->all())
                            : null,
                        'has_social_status' => $faker->optional()->randomElement(['STUDENT', 'ADULT']),
                    ]));
                }

                $participants = EventParticipation::where('event_id', $event->id_event)->get();
                if ($participants->isEmpty()) {
                    continue;
                }

                foreach ($rules as $rule) {
                    EventLotteryLucky::factory()
                        ->count(\rand(1, min(3, (int) $rule->rule_prize_quantity)))
                        ->create([
                            'lottery_rule_id' => $rule->id_lottery_rule,
                            'drawn_participation_id' => $participants->random()->id_participation,
                        ]);
                }
            }
        }
    }

    /**
     * Most players get an approved assessment with a level; a few have one waiting in the review queue.
     */
    private function createPlayerLevels($players, $faker): void
    {
        $bands = Level::query()->get();
        if ($bands->isEmpty()) {
            return;
        }

        foreach ($players as $player) {
            if ($faker->boolean(75)) {
                $band = $bands->random();
                $level = number_format(((float) $band->level_min + (float) $band->level_max) / 2, 2, '.', '');

                $assessment = PlayerLevelAssessment::factory()->forPlayer($player)->approved()->create();
                PlayerLevel::factory()->fromAssessment($assessment)->level($level)->create();
            } elseif ($faker->boolean(40)) {
                PlayerLevelAssessment::factory()->forPlayer($player)->readyForReview()->create();
            }
        }
    }
}
