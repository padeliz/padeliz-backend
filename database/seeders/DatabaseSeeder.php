<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Court;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionLottery;
use App\Models\EditionLotteryLucky;
use App\Models\EditionLotteryRule;
use App\Models\EditionParticipation;
use App\Models\Eligibility;
use App\Models\EligibilityPortraitPhoto;
use App\Models\EligibilityStudentProof;
use App\Models\Location;
use App\Models\MatchesTeam;
use App\Models\Player;
use App\Models\PlayerEmail;
use App\Models\PlayerIdentity;
use App\Models\PlayerPhone;
use App\Models\PlayerProfileImage;
use App\Models\Prize;
use App\Models\PrizeFromClub;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dev database seeding: truncates core tables, rebuilds synthetic clubs/editions/players/eligibilities, etc.
 *
 * Upload-related behaviour:
 * - Player profile images: {@see PlayerFactory} afterCreating writes files under `players/{id}/profile/`
 *   on `filesystems.user_uploads_disk` (skipped in `testing`). Seeder clears `storage/app/public/players`
 *   when that directory exists.
 * - Eligibility rows: {@see createEligibilities()} only (no attachment rows).
 * - Eligibility student proofs / portrait photos: {@see seedEligibilityAttachments()} writes files under
 *   `eligibilities/{id_eligibility}/student-proof` and `.../portrait-photo` (same layout as the API upload
 *   controller), skipped in `testing`. Seeder removes prior `eligibilities/` tree on that disk before re-seeding.
 */
class DatabaseSeeder extends Seeder
{
    /** Minimal valid 1x1 PNG (transparent pixel). */
    private const SEED_PNG_BYTES = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** Minimal PDF document body for seeded student-proof files. */
    private const SEED_PDF_BYTES = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";

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

        EditionLotteryLucky::truncate();
        EditionLotteryRule::truncate();
        EditionLottery::truncate();
        PrizeFromClub::truncate();
        Prize::truncate();
        EditionParticipation::truncate();
        EditionDivision::truncate();
        EligibilityPortraitPhoto::truncate();
        EligibilityStudentProof::truncate();
        Eligibility::truncate();
        PlayerProfileImage::truncate();
        PlayerIdentity::query()->delete();
        PlayerPhone::query()->delete();
        PlayerEmail::query()->delete();
        Player::truncate();
        Edition::truncate();
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

        $this->cleanEligibilityUploadsOnDisk();

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
        $divisions = collect([
            Division::factory()->create(['name' => 'Students Rookies', 'color' => '#ffff00']),
            Division::factory()->create(['name' => 'Students Beginners', 'color' => '#ffa500']),
            Division::factory()->create(['name' => 'Students Novice', 'color' => '#cb2b2b']),
            Division::factory()->create(['name' => 'Kids', 'color' => '#7ab857']),
            Division::factory()->create(['name' => 'MiniTour Couples', 'color' => '#ff0000']),
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
            Player::factory()->count($regularPlayersCount)->playerOnly()->create()
        );

        // Create managers (20% of total) — no admin role in seed data
        $managersCount = max(1, (int) (\rand(15, 20) * 0.2));
        $players = $players->merge(
            Player::factory()->count($managersCount)->manager()->create()
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
            $division->update(['its_first_edition_id' => $allEditions->random()->id_edition]);
        }

        $this->command->info('Creating prizes and club prizes...');
        $prizes = Prize::factory()->count(5)->create();

        // Create prizes from clubs
        foreach ($clubs as $club) {
            PrizeFromClub::factory()
                ->count(\rand(1, 3))
                ->forClub($club)
                ->create([
                    'prize_id' => $prizes->random()->id_prize,
                ]);
        }

        $this->command->info('Creating edition divisions and participations...');
        foreach ($pastEditions->merge($futureEditions) as $edition) {
            $this->createEditionDivisionsAndParticipations($edition, $players);
        }

        $this->command->info('Creating lotteries, rules and luckies...');
        $this->createLotteriesAndLuckies($pastEditions, $prizes, $faker);

        $this->command->info('Creating matches teams...');
        // Create some matches teams with random players
        MatchesTeam::factory()
            ->count(\rand(10, 20))
            ->create([
                'player_1' => fn () => $players->random()->id_player,
                'player_2' => fn () => $players->random()->id_player,
            ]);

        $this->command->info('Creating player eligibilities...');
        $this->createEligibilities($players, $allEditions, $faker);

        $this->seedEligibilityAttachments($faker);
    }

    /**
     * Create a named player and replace factory-generated contacts/identity with live-like values.
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

        $player->emails()->delete();
        $player->phones()->delete();
        $player->identities()->delete();

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

    /**
     * Remove previous seeded eligibility files from the user uploads disk (local dev only).
     * Skipped in PHPUnit so tests never touch real storage.
     */
    private function cleanEligibilityUploadsOnDisk(): void
    {
        if (App::environment('testing')) {
            return;
        }

        $diskName = config('filesystems.user_uploads_disk', 'public');

        if (! Storage::disk($diskName)->exists('eligibilities')) {
            return;
        }

        $this->command->info('Clearing storage/app/public/eligibilities...');
        Storage::disk($diskName)->deleteDirectory('eligibilities');
    }

    /**
     * Seed eligibility_student_proofs / eligibility_portrait_photos with on-disk blobs matching API paths.
     *
     * Rules (dev realism):
     * - Student proofs: only pending eligibilities with {@see Eligibility::$being_student} true; 0–2 files each.
     * - Portrait photos: random subset (~25%) of any-status eligibilities; 1–2 images each (cap 3).
     */
    private function seedEligibilityAttachments($faker): void
    {
        if (App::environment('testing')) {
            return;
        }

        $diskName = config('filesystems.user_uploads_disk', 'public');
        $disk = Storage::disk($diskName);
        $now = time();
        $png = base64_decode(self::SEED_PNG_BYTES, true);
        if ($png === false) {
            $png = ' ';
        }
        $pdf = self::SEED_PDF_BYTES;

        $this->command->info('Seeding eligibility student proofs and portrait photos...');

        foreach (Eligibility::where('review_status', 'pending')->cursor() as $eligibility) {
            if ($eligibility->being_student !== true) {
                if (! $faker->boolean(55)) {
                    continue;
                }
                $eligibility->being_student = true;
                if ($eligibility->media_source === null && $eligibility->friends_source === null) {
                    $eligibility->media_source = [$faker->numberBetween(1, 6)];
                }
                $eligibility->save();
            }

            if ($eligibility->being_student !== true || ! $faker->boolean(65)) {
                continue;
            }

            $proofCount = \random_int(1, min(2, EligibilityStudentProof::MAX_PER_ELIGIBILITY));
            $id = (int) $eligibility->getKey();

            for ($i = 0; $i < $proofCount; $i++) {
                $ext = $faker->boolean() ? '.pdf' : '.png';
                $body = $ext === '.pdf' ? $pdf : $png;
                $relativePath = 'eligibilities/' . $id . '/student-proof/' . Str::uuid()->toString() . $ext;
                $disk->put($relativePath, $body, ['visibility' => 'public']);

                EligibilityStudentProof::create([
                    'eligibility_id' => $id,
                    'path' => $relativePath,
                    'sort_order' => $i,
                    'inserted_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (Eligibility::cursor() as $eligibility) {
            if (! $faker->boolean(25)) {
                continue;
            }

            $photoCount = \random_int(1, min(2, EligibilityPortraitPhoto::MAX_PER_ELIGIBILITY));
            $id = (int) $eligibility->getKey();

            for ($i = 0; $i < $photoCount; $i++) {
                $relativePath = 'eligibilities/' . $id . '/portrait-photo/' . Str::uuid()->toString() . '.png';
                $disk->put($relativePath, $png, ['visibility' => 'public']);

                EligibilityPortraitPhoto::create([
                    'eligibility_id' => $id,
                    'path' => $relativePath,
                    'sort_order' => $i,
                    'inserted_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function createEditions(int $index_nr, int $count, Carbon $startDate, Carbon $endDate, $locations)
    {
        $editions = [];

        // Ensure we have locations before creating editions
        if ($locations->isEmpty()) {
            $this->command->error('No locations found. Cannot create editions.');

            return collect();
        }

        for ($i = 0; $i < $count; $i++) {
            $arriving_time = Carbon::createFromTime(\rand(12, 19), 30);

            $editions[] = Edition::factory()->forLocation($locations->random())->create([
                'name' => '#' . ($index_nr + $i + 1),
                'date' => Carbon::parse($startDate)->addDays(\rand(0, (int) Carbon::parse($endDate)->diffInDays($startDate))),
                'entry_fee' => \rand(45, 55),
                'students_entry_fee' => \rand(35, 45),
                'arriving_time' => $arriving_time->format('H:i:s'),
                'closing_time' => $arriving_time->addHours(\rand(2, 4))->format('H:i:s'),
            ]);
        }

        return collect($editions);
    }

    private function createEditionDivisionsAndParticipations($edition, $players)
    {
        // Get random divisions for this edition
        $selectedDivisions = Division::inRandomOrder()->take(\rand(2, 3))->get();

        foreach ($selectedDivisions as $division) {
            // Create division for this edition (courts live on courts + pivot, not on edition_divisions)
            $editionDivision = EditionDivision::factory()->create([
                'edition_id' => $edition->id_edition,
                'division_id' => $division->id_division,
                'division_title' => $division->name,
                'players_limit' => \rand(8, 16),
                'playing_start_time' => Carbon::createFromTime(\rand(12, 19), 30)->format('H:i:s'),
                'playing_end_time' => Carbon::createFromTime(\rand(16, 21), 30)->format('H:i:s'),
                'include_scores' => $division->name !== 'Kids',
                'has_demonstrative_match' => $division->name == 'Students Novice',
                'fixed_teams' => $division->name == 'MiniTour Couples',
                'points_per_match' => $division->name === 'Kids' ? null : 1,
                'include_final' => $division->name !== 'Kids',
            ]);

            // Create 6-12 participations for this edition division
            $participationCount = \rand(6, 12);
            for ($i = 0; $i < $participationCount; $i++) {
                EditionParticipation::factory()->create([
                    'edition_id' => $edition->id_edition,
                    'edition_division_id' => $editionDivision->id_edition_division,
                    'player_id' => $players->random()->id_player,
                ]);
            }
        }
    }

    private function createLotteriesAndLuckies($editions, $prizes, $faker)
    {
        foreach ($editions as $edition) {
            $lotteries = EditionLottery::factory()
                ->count(\rand(1, 2))
                ->create([
                    'edition_id' => $edition->id_edition,
                    'prize_id' => $prizes->random()->id_prize,
                    'prize_quantity' => \rand(1, 5),
                ]);

            $editionDivisions = EditionDivision::where('edition_id', $edition->id_edition)->get();

            foreach ($lotteries as $lottery) {
                $rules = collect();
                $remaining = (int) $lottery->prize_quantity;

                // Live-like: one open rule + optional scoped rules (student / division)
                $openQty = max(1, (int) ceil($remaining / 2));
                $rules->push(EditionLotteryRule::factory()->create([
                    'lottery_id' => $lottery->id_lottery,
                    'rule_prize_quantity' => $openQty,
                    'orderliness' => 0,
                    'edition_division_id' => null,
                    'has_social_status' => null,
                ]));
                $remaining -= $openQty;

                if ($remaining > 0) {
                    $rules->push(EditionLotteryRule::factory()->create([
                        'lottery_id' => $lottery->id_lottery,
                        'rule_prize_quantity' => $remaining,
                        'orderliness' => 1,
                        'edition_division_id' => $editionDivisions->isNotEmpty()
                            ? $faker->optional()->randomElement($editionDivisions->pluck('id_edition_division')->all())
                            : null,
                        'has_social_status' => $faker->optional()->randomElement(['STUDENT', 'ADULT']),
                    ]));
                }

                $participants = EditionParticipation::where('edition_id', $edition->id_edition)->get();
                if ($participants->isEmpty()) {
                    continue;
                }

                foreach ($rules as $rule) {
                    EditionLotteryLucky::factory()
                        ->count(\rand(1, min(3, (int) $rule->rule_prize_quantity)))
                        ->create([
                            'lottery_rule_id' => $rule->id_lottery_rule,
                            'drawn_participation_id' => $participants->random()->id_participation,
                        ]);
                }
            }
        }
    }

    private function createEligibilities($players, $editions, $faker): void
    {
        $now = time();

        foreach ($players as $player) {
            $primaryPhone = $player->phones()->where('is_primary', true)->value('phone')
                ?? $player->phones()->value('phone');

            // Create one reviewed eligibility for most players
            if ($faker->boolean(75)) {
                $reviewStatus = $faker->randomElement(['approved', 'rejected', 'discarded']);
                $reviewedAt = $reviewStatus === 'approved' || $reviewStatus === 'rejected' ? $now - $faker->numberBetween(60, 3600) : null;

                $eligibility = new Eligibility();
                $eligibility->player_id = $player->id_player;
                $eligibility->edition_ids = $faker->boolean(70) ? [$editions->random()->id_edition] : null;

                // Sources (required: media_source OR friends_source)
                if ($faker->boolean(60)) {
                    $eligibility->media_source = [$faker->numberBetween(1, 6)];
                    $eligibility->friends_source = null;
                } else {
                    $eligibility->media_source = null;
                    $eligibility->friends_source = [$faker->firstName(), $faker->firstName()];
                }

                $eligibility->racket_experience = $faker->boolean(60) ? [$faker->randomElement(['none', 'tennis', 'squash', 'badminton', 'padel'])] : null;

                $eligibility->first_name = $player->first_name ?? $faker->firstName();
                $eligibility->middle_name = $player->middle_name ?? null;
                $eligibility->last_name = $player->last_name ?? $faker->lastName();
                $eligibility->phone = $primaryPhone ?? $faker->phoneNumber();
                $eligibility->birth_year = $faker->numberBetween(1960, 2006);
                $eligibility->birth_month = $faker->numberBetween(1, 12);
                $eligibility->birth_day = $faker->numberBetween(1, 28);
                $eligibility->being_student = $faker->optional()->boolean();

                $eligibility->playtomic_id = $player->playtomic_id ?? $faker->optional()->bothify('??#####');
                $eligibility->rankedin_id = $player->rankedin_id ?? $faker->optional()->bothify('??######');
                $eligibility->instagram_username = $player->instagram_username ?? $faker->optional()->userName();
                $eligibility->facebook_username = $player->facebook_username ?? $faker->optional()->userName();

                $eligibility->submitted_at = $now - $faker->numberBetween(3600, 86400 * 14);
                $eligibility->resubmitted_at = $faker->boolean(20) ? $now - $faker->numberBetween(60, 3600) : null;
                $eligibility->review_status = $reviewStatus;
                $eligibility->reviewed_at = $reviewedAt;
                $eligibility->review_message = $reviewStatus === 'rejected' ? $faker->sentence() : null;
                $eligibility->inserted_at = $eligibility->submitted_at;
                $eligibility->updated_at = $eligibility->resubmitted_at;

                $eligibility->recomputeReadyForReview();
                $eligibility->save();
            }

            // Create at most one pending eligibility per player (DB constraint also enforces this)
            if ($faker->boolean(25)) {
                $pending = new Eligibility();
                $pending->player_id = $player->id_player;
                $pending->edition_ids = $faker->boolean(40) ? [$editions->random()->id_edition] : null;
                $pending->submitted_at = $now - $faker->numberBetween(60, 3600);
                $pending->review_status = 'pending';
                $pending->inserted_at = $pending->submitted_at;
                $pending->updated_at = null;

                // Sometimes incomplete (not ready), sometimes ready
                if ($faker->boolean(40)) {
                    $pending->media_source = [$faker->numberBetween(1, 6)];
                    $pending->first_name = $player->first_name ?? $faker->firstName();
                    $pending->last_name = $player->last_name ?? $faker->lastName();
                    $pending->phone = $primaryPhone ?? $faker->phoneNumber();
                    $pending->birth_year = $faker->numberBetween(1960, 2006);
                }

                $pending->recomputeReadyForReview();
                $pending->save();
            }
        }
    }
}
