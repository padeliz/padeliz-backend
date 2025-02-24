<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use App\Models\Club;
use App\Models\Division;
use App\Models\Location;
use App\Models\Edition;
use App\Models\Player;
use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use App\Models\Prize;
use App\Models\PrizeFromClub;
use App\Models\EditionLottery;
use App\Models\EditionLuckyOne;
use Carbon\Carbon;
use Faker\Factory as Faker;
use App\Models\MatchesTeam;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        if (App::environment('production')) {
            $this->command->error('Seeding is not allowed in production!');
            return;
        }

        $this->command->error(Carbon::now()->format('Y-m-d H:i:s'));

        $faker = Faker::create();

        $this->command->info('Clearing existing data...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        EditionLuckyOne::truncate();
        EditionLottery::truncate();
        PrizeFromClub::truncate();
        Prize::truncate();
        EditionParticipation::truncate();
        EditionDivision::truncate();
        Player::truncate();
        Edition::truncate();
        Location::truncate();
        Division::truncate();
        Club::truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('Creating clubs...');
        $clubs = collect([
            Club::factory()->create(['name' => 'Magic Padel', 'city' => 'Bucharest']),
            Club::factory()->create(['name' => 'PadelMania', 'city' => 'Bucharest']),
            Club::factory()->create(['name' => 'Padel One', 'city' => 'Bucharest']),
            Club::factory()->create(['name' => 'Padel World', 'city' => 'Bucharest']),
        ]);

        $this->command->info('Creating locations...');
        foreach ($clubs as $club) {
            Location::factory()->count(rand(1, 2))->create([
                'club_id' => $club->id_club,
                'name' => $club->name . ' ' . $faker->unique()->streetName(),
            ]);
        }

        $this->command->info('Creating divisions...');
        $divisions = collect([
            Division::factory()->create(['name' => 'Students Rookies', 'color' => '#ffff00', 'is_students_only' => 1]),
            Division::factory()->create(['name' => 'Students Beginners', 'color' => '#ffa500', 'is_students_only' => 1]),
            Division::factory()->create(['name' => 'Students Novice', 'color' => '#cb2b2b', 'is_students_only' => 1]),
            Division::factory()->create(['name' => 'Kids', 'color' => '#7ab857', 'is_students_only' => 0]),
            Division::factory()->create(['name' => 'MiniTour Couples', 'color' => '#ff0000', 'is_students_only' => 0]),
            Division::factory()->create(['name' => 'Adults Explorers', 'color' => '#7ab857', 'is_students_only' => 0]),
            Division::factory()->create(['name' => 'Adults Learners', 'color' => '#89bdce', 'is_students_only' => 0]),
        ]);

        $this->command->error(Carbon::now()->format('Y-m-d H:i:s'));

        $this->command->info('Creating players...');
        $players = Player::factory()->count(rand(15, 20))->create();

        $this->command->info('Creating past editions...');
        $pastEditions = $this->createEditions(0, rand(10, 20), Carbon::now()->subMonths(16), Carbon::now());

        $this->command->info('Creating ongoing editions...');
        $ongoingEditions = $this->createEditions($pastEditions->count(), rand(1, 2), Carbon::now(), Carbon::now());

        $this->command->info('Creating future editions...');
        $futureEditions = $this->createEditions($ongoingEditions->count() + $pastEditions->count(), rand(6, 10), Carbon::now(), Carbon::now()->addMonths(3));

        $this->command->info('Creating prizes and club prizes...');
        $prizes = Prize::factory()->count(5)->create();

        // Create prizes from clubs
        foreach ($clubs as $club) {
            PrizeFromClub::factory()
                ->count(rand(1, 3))
                ->create([
                    'club_id' => $club->id_club,
                    'prize_id' => $prizes->random()->id_prize,
                ]);
        }

        $this->command->info('Creating edition divisions and participations...');
        foreach ($pastEditions->merge($futureEditions) as $edition) {
            $this->createEditionDivisionsAndParticipations($edition, $players);
        }

        $this->command->info('Creating lotteries and lucky ones...');
        $this->createLotteriesAndLuckyOnes($pastEditions, $divisions, $prizes, $faker);

        $this->command->info('Creating matches teams...');
        // Create some matches teams with random players
        MatchesTeam::factory()
            ->count(rand(10, 20))
            ->create([
                'player_1' => fn() => $players->random()->id_player,
                'player_2' => fn() => $players->random()->id_player,
            ]);
    }

    private function createEditions(int $index_nr, int $count, Carbon $startDate, Carbon $endDate)
    {
        $editions = [];
        $locations = Location::all();

        // Ensure we have locations before creating editions
        if ($locations->isEmpty()) {
            $this->command->error('No locations found. Cannot create editions.');
            return [];
        }

        for ($i = 0; $i < $count; $i++) {
            $arriving_time = Carbon::createFromTime(rand(12, 19), 30);

            $editions[] = Edition::factory()->create(['name' => '#' . ($index_nr + $i + 1),
                'date' => Carbon::parse($startDate)->addDays(rand(0, Carbon::parse($endDate)->diffInDays($startDate))),
                'location_id' => $locations->random()->id_location, // Changed from id to id_location
                'entry_fee' => rand(45, 55),
                'students_entry_fee' => rand(35, 45),
                'arriving_time' => $arriving_time->format('H:i:s'),
                'closing_time' => $arriving_time->addHours(rand(2, 4))->format('H:i:s'),
            ]);
        }

        return collect($editions);  // Convert array to collection
    }

    private function createEditionDivisionsAndParticipations($edition, $players)
    {
        // Get random divisions for this edition
        $selectedDivisions = Division::inRandomOrder()->take(rand(2, 3))->get();

        foreach ($selectedDivisions as $division) {
            // Create division for this edition
            EditionDivision::factory()->create([
                'edition_id' => $edition->id_edition,
                'division_id' => $division->id_division,
                'players_limit' => rand(8, 16),
                'court' => 'Court ' . rand(1, 3),
                'playing_start_time' => Carbon::createFromTime(rand(12, 19), 30)->format('H:i:s'),
                'playing_end_time' => Carbon::createFromTime(rand(16, 21), 30)->format('H:i:s'),
                'include_scores' => $division->name !== 'Kids',
                'has_demonstrative_match' => $division->name == 'Students Novice',
                'fixed_teams' => $division->name == 'MiniTour Couples',
            ]);

            // Create 6-12 participations for this division
            $participationCount = rand(6, 12);
            for ($i = 0; $i < $participationCount; $i++) {
                EditionParticipation::factory()->create([
                    'edition_id' => $edition->id_edition,
                    'division_id' => $division->id_division,
                    'player_id' => $players->random()->id_player,
                ]);
            }
        }
    }

    private function createLotteriesAndLuckyOnes($editions, $divisions, $prizes, $faker)
    {
        foreach ($editions as $edition) {
            $lotteries = EditionLottery::factory()
                ->count(rand(1, 2))
                ->create([
                    'edition_id' => $edition->id_edition,
                    'division_id' => $faker->optional()->randomElement([$divisions->random()->id_division, null]),
                    'prize_id' => $prizes->random()->id_prize,
                ]);

            // Create lucky ones for each lottery
            foreach ($lotteries as $lottery) {
                $participants = EditionParticipation::where('edition_id', $edition->id_edition)->get();
                if ($participants->count() > 0) {
                    EditionLuckyOne::factory()
                        ->count(rand(1, 3))
                        ->create([
                            'lottery_id' => $lottery->id_lottery,
                            'drawn_participation_id' => $participants->random()->id_participation,
                        ]);
                }
            }
        }
    }
}
