<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->id('id_club');
            $table->string('name', 30)->unique();
            $table->string('city', 30)->default('Bucharest');
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('divisions', function (Blueprint $table) {
            $table->id('id_division');
            $table->string('name', 25)->unique('uq_divisions_name');
            $table->string('color', 7);
            $table->integer('its_first_edition_id');
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id('id_location');
            $table->foreignId('club_id')->constrained('clubs', 'id_club');
            $table->string('name', 30);
            $table->string('address', 200);
            $table->integer('total_indoors_courts');
            $table->integer('total_outdoor_courts');
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('courts', function (Blueprint $table) {
            $table->id('id_court');
            $table->foreignId('location_id')->constrained('locations', 'id_location');
            $table->string('name', 30);
            $table->boolean('is_indoor');
            $table->smallInteger('display_order')->default(0);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['location_id', 'name'], 'uq_court_location_name');
        });

        Schema::create('editions', function (Blueprint $table) {
            $table->id('id_edition');
            $table->string('name', 25);
            $table->date('date');
            $table->foreignId('location_id')->constrained('locations', 'id_location');
            $table->integer('entry_fee')->default(55)->comment('ron');
            $table->integer('students_entry_fee')->default(45)->comment('ron');
            $table->time('arriving_time');
            $table->time('closing_time');
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id('id_player');
            $table->string('first_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->json('user_types')->nullable();
            $table->string('playtomic_id', 10)->nullable();
            $table->string('rankedin_id', 15)->nullable();
            $table->integer('year_of_birth')->nullable();
            $table->boolean('being_student')->default(false);
            $table->rememberToken();
            $table->float('skill_division', 3, 1)->nullable();
            $table->string('instagram_username', 50)->nullable();
            $table->string('facebook_username', 50)->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        Schema::create('player_emails', function (Blueprint $table) {
            $table->id('id_player_email');
            $table->foreignId('player_id')->constrained('players', 'id_player')->cascadeOnDelete();
            $table->string('email', 255)->unique();
            $table->json('purposes');
            $table->boolean('is_primary')->default(false);
            $table->integer('verified_at')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        Schema::create('player_phones', function (Blueprint $table) {
            $table->id('id_player_phone');
            $table->foreignId('player_id')->constrained('players', 'id_player')->cascadeOnDelete();
            $table->string('phone', 30)->unique();
            $table->json('purposes');
            $table->boolean('is_primary')->default(false);
            $table->integer('verified_at')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        Schema::create('player_identities', function (Blueprint $table) {
            $table->id('id_identity');
            $table->foreignId('player_id')->constrained('players', 'id_player')->cascadeOnDelete();
            $table->enum('provider', ['google', 'apple', 'sms', 'dev']);
            $table->string('provider_subject', 255);
            $table->foreignId('email_id')->nullable()->constrained('player_emails', 'id_player_email')->nullOnDelete();
            $table->foreignId('phone_id')->nullable()->constrained('player_phones', 'id_player_phone')->nullOnDelete();
            $table->string('display_name', 255)->nullable();
            $table->string('avatar_url', 512)->nullable();
            $table->integer('linked_at');
            $table->integer('last_login_at')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['provider', 'provider_subject'], 'uq_player_identity_provider_subject');
        });

        Schema::create('player_invites', function (Blueprint $table) {
            $table->id('id_invite');
            $table->foreignId('player_id')->constrained('players', 'id_player')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->integer('expires_at');
            $table->integer('accepted_at')->nullable();
            $table->integer('revoked_at')->nullable();
            $table->foreignId('created_by_player_id')->nullable()->constrained('players', 'id_player')->nullOnDelete();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index('player_id', 'player_invites_player_id_idx');
        });

        Schema::create('player_invite_events', function (Blueprint $table) {
            $table->id('id_invite_event');
            $table->foreignId('invite_id')->nullable()->constrained('player_invites', 'id_invite')->nullOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('players', 'id_player')->nullOnDelete();
            $table->enum('event', ['created', 'redeem_started', 'redeem_succeeded', 'redeem_failed', 'revoked']);
            $table->string('result_code', 64)->nullable();
            $table->enum('provider', ['google', 'apple', 'sms', 'dev'])->nullable();
            $table->string('provider_subject', 255)->nullable();
            $table->enum('contact_kind', ['email', 'phone'])->nullable();
            $table->string('contact_value_hash', 64)->nullable();
            $table->foreignId('actor_player_id')->nullable()->constrained('players', 'id_player')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('meta')->nullable();
            $table->integer('inserted_at');
        });

        Schema::create('player_profile_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players', 'id_player')->cascadeOnDelete();
            $table->string('path', 512);
            $table->unsignedTinyInteger('sort_order');
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index('player_id', 'player_profile_images_player_id_idx');
        });

        Schema::create('edition_divisions', function (Blueprint $table) {
            $table->id('id_edition_division');
            $table->foreignId('edition_id')->constrained('editions', 'id_edition');
            $table->foreignId('division_id')->constrained('divisions', 'id_division');
            $table->string('division_title', 64);
            $table->integer('players_limit');
            $table->time('playing_start_time');
            $table->time('playing_end_time');
            $table->integer('player_opponents_nr')->nullable();
            $table->integer('player_matches_repeat')->nullable()->default(1);
            $table->tinyInteger('include_scores')->default(1);
            $table->tinyInteger('has_demonstrative_match')->default(0);
            $table->tinyInteger('fixed_teams')->default(0);
            $table->tinyInteger('points_per_match')->nullable();
            $table->tinyInteger('final_points_target')->nullable();
            $table->boolean('include_final')->default(false);
            $table->integer('finalized_at')->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('edition_division_courts', function (Blueprint $table) {
            $table->id('id_edition_division_court');
            $table->foreignId('edition_division_id')->constrained('edition_divisions', 'id_edition_division')->cascadeOnDelete();
            $table->foreignId('court_id')->constrained('courts', 'id_court');
            $table->smallInteger('display_order')->default(0);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['edition_division_id', 'court_id'], 'uq_ed_court');
        });

        Schema::create('edition_division_posters', function (Blueprint $table) {
            $table->id('id_edition_division_poster');
            $table->foreignId('edition_division_id')->constrained('edition_divisions', 'id_edition_division')->cascadeOnDelete();
            $table->enum('poster_kind', ['MATCHES', 'RANKING', 'COMBINED', 'BATTLES', 'HOUSE', 'SETUP']);
            $table->string('filename', 255);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['edition_division_id', 'poster_kind', 'filename'], 'uq_ed_poster');
        });

        Schema::create('edition_participations', function (Blueprint $table) {
            $table->id('id_participation');
            $table->foreignId('edition_id')->constrained('editions', 'id_edition');
            $table->foreignId('player_id')->constrained('players', 'id_player');
            $table->foreignId('edition_division_id')->constrained('edition_divisions', 'id_edition_division');
            $table->boolean('is_minor')->default(false);
            $table->boolean('is_student');
            $table->integer('discount')->nullable()->comment('ron');
            $table->integer('payment')->comment('ron');
            $table->enum('absence_reason', ['SICK', 'FORGOT', 'EXAMS', 'RECONSIDERED'])->nullable();
            $table->enum('ban_reason', ['LEFT SUDDENLY', 'AGGRESSIVE PLAYER', 'LIBIDINOUS', ''])->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('prizes', function (Blueprint $table) {
            $table->id('id_prize');
            $table->string('name', 60);
            $table->string('image', 100)->nullable();
            $table->enum('template', ['IMAGE_LEFT', 'IMAGE_TOP', 'IMAGE_RIGHT', 'IMAGE_MIDDLE', 'IMAGE_BOTTOM'])->nullable();
            $table->string('box_1_text', 200);
            $table->string('box_1_bg_color', 7);
            $table->string('box_1_text_color', 7);
            $table->string('box_2_text', 200)->nullable();
            $table->string('box_2_bg_color', 7)->nullable();
            $table->string('box_2_text_color', 7)->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('prizes_from_clubs', function (Blueprint $table) {
            $table->id('id_club_prize');
            $table->foreignId('club_id')->nullable()->constrained('clubs', 'id_club');
            $table->foreignId('prize_id')->constrained('prizes', 'id_prize');
            $table->string('image', 100)->nullable();
            $table->enum('template', ['IMAGE_LEFT', 'IMAGE_TOP', 'IMAGE_RIGHT', 'IMAGE_MIDDLE', 'IMAGE_BOTTOM'])->nullable();
            $table->string('box_1_text', 200)->nullable();
            $table->string('box_1_bg_color', 7)->nullable();
            $table->string('box_1_text_color', 7)->nullable();
            $table->string('box_2_text', 200)->nullable();
            $table->string('box_2_bg_color', 7)->nullable();
            $table->string('box_2_text_color', 7)->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('edition_lotteries', function (Blueprint $table) {
            $table->id('id_lottery');
            $table->foreignId('edition_id')->constrained('editions', 'id_edition');
            $table->foreignId('prize_id')->nullable()->constrained('prizes', 'id_prize');
            $table->tinyInteger('prize_quantity');
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('edition_lottery_rules', function (Blueprint $table) {
            $table->id('id_lottery_rule');
            $table->foreignId('lottery_id')->constrained('edition_lotteries', 'id_lottery');
            $table->tinyInteger('rule_prize_quantity');
            $table->tinyInteger('orderliness')->default(0);
            $table->foreignId('edition_division_id')->nullable()->constrained('edition_divisions', 'id_edition_division');
            $table->enum('gender', ['MALE', 'FEMALE'])->nullable();
            $table->enum('has_social_status', ['STUDENT', 'ADULT'])->nullable();
            $table->enum('legal_age', ['MINOR', 'MAJOR'])->nullable();
            $table->integer('is_younger_than')->nullable();
            $table->integer('is_older_than')->nullable();
            $table->enum('has_tshirt_size', ['XS', 'S', 'M', 'L', 'XL', 'XXL'])->nullable();
            $table->boolean('is_guest')->nullable();
            $table->boolean('is_padel_first_timer')->nullable();
            $table->boolean('is_event_first_timer')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        Schema::create('edition_lottery_luckies', function (Blueprint $table) {
            $table->id('id_lucky');
            $table->foreignId('lottery_rule_id')->nullable()->constrained('edition_lottery_rules', 'id_lottery_rule');
            $table->foreignId('drawn_participation_id')->constrained('edition_participations', 'id_participation');
            $table->integer('drawn_at');
            $table->integer('available_at');
            $table->integer('announced_at')->nullable();
            $table->integer('accepted_at')->nullable();
            $table->integer('rejected_at')->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id('id_match');
            $table->foreignId('edition_division_id')->constrained('edition_divisions', 'id_edition_division');
            $table->foreignId('edition_division_court_id')->constrained('edition_division_courts', 'id_edition_division_court');
            $table->enum('match_kind', ['REGULAR', 'FINAL', 'DEMONSTRATIVE']);
            $table->smallInteger('sequence_no');
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();
            $table->tinyInteger('points_target')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(
                ['edition_division_id', 'match_kind', 'edition_division_court_id', 'sequence_no'],
                'uq_match_sequence'
            );
        });

        Schema::create('match_sides', function (Blueprint $table) {
            $table->id('id_match_side');
            $table->foreignId('match_id')->constrained('matches', 'id_match')->cascadeOnDelete();
            $table->enum('side', ['LEFT', 'RIGHT']);
            $table->tinyInteger('score')->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->unique(['match_id', 'side'], 'uq_match_side');
        });

        Schema::create('match_players', function (Blueprint $table) {
            $table->id('id_match_player');
            $table->foreignId('match_side_id')->constrained('match_sides', 'id_match_side')->cascadeOnDelete();
            $table->tinyInteger('slot');
            $table->foreignId('participation_id')->nullable()->constrained('edition_participations', 'id_participation');
            $table->foreignId('player_id')->nullable()->constrained('players', 'id_player');
            $table->boolean('got_replaced')->default(false);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['match_side_id', 'slot'], 'uq_match_side_slot');
        });

        Schema::create('edition_division_standings', function (Blueprint $table) {
            $table->id('id_standing');
            $table->foreignId('edition_division_id')->constrained('edition_divisions', 'id_edition_division');
            $table->foreignId('participation_id')->constrained('edition_participations', 'id_participation');
            $table->boolean('is_collecting_points')->default(true);
            $table->integer('points_total')->nullable();
            $table->decimal('wins', 4, 1)->nullable();
            $table->smallInteger('rank')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['edition_division_id', 'participation_id'], 'uq_standing_participation');
        });

        Schema::create('edition_division_replacements', function (Blueprint $table) {
            $table->id('id_replacement');
            $table->unsignedBigInteger('edition_division_id');
            $table->unsignedBigInteger('original_participation_id');
            $table->unsignedBigInteger('substitute_participation_id')->nullable();
            $table->unsignedBigInteger('substitute_player_id')->nullable();
            $table->unsignedBigInteger('match_id')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        $prefix = DB::getTablePrefix();
        DB::statement(
            "ALTER TABLE `{$prefix}edition_division_replacements` " .
            'ADD COLUMN `match_scope_key` BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(`match_id`, 0)) STORED'
        );
        DB::statement(
            'CREATE UNIQUE INDEX `uq_replacement_scope` ' .
            "ON `{$prefix}edition_division_replacements` (`edition_division_id`, `original_participation_id`, `match_scope_key`)"
        );
        Schema::table('edition_division_replacements', function (Blueprint $table) {
            $table->foreign('edition_division_id', 'fk_repl_ed')
                ->references('id_edition_division')->on('edition_divisions');
            $table->foreign('original_participation_id', 'fk_repl_orig')
                ->references('id_participation')->on('edition_participations');
            $table->foreign('substitute_participation_id', 'fk_repl_sub_part')
                ->references('id_participation')->on('edition_participations');
            $table->foreign('substitute_player_id', 'fk_repl_sub_player')
                ->references('id_player')->on('players');
            // No FK on match_id: MariaDB rejects FKs on columns used by generated match_scope_key.
            $table->index('match_id', 'fk_repl_match');
        });

        // Note: $prefix already set above for replacements generated column
        // Reuse for eligibilities below — redefine if needed after other creates.

        Schema::create('matches_teams', function (Blueprint $table) {
            $table->id('id_team');
            $table->string('player_1', 50);
            $table->string('player_2', 50);
            $table->boolean('player_1_is_collecting_points')->default(true);
            $table->boolean('player_2_is_collecting_points')->default(true);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        Schema::create('validations_site', function (Blueprint $table) {
            $table->string('error', 100)->primary();
            $table->integer('vars', false, true)->nullable()->default(0);
            $table->mediumText('message_en');
        });

        Schema::create('eligibilities', function (Blueprint $table) {
            $table->id('id_eligibility');
            $table->foreignId('player_id')->constrained('players', 'id_player')->index();
            $table->json('edition_ids')->nullable();
            $table->json('media_source')->nullable();
            $table->json('friends_source')->nullable();
            $table->json('racket_experience')->nullable();
            $table->string('playtomic_id', 10)->nullable();
            $table->string('rankedin_id', 15)->nullable();
            $table->string('first_name', 50)->nullable();
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->string('phone', 100)->nullable();
            $table->integer('birth_year')->nullable();
            $table->integer('birth_month')->nullable();
            $table->integer('birth_day')->nullable();
            $table->boolean('being_student')->nullable();
            $table->string('instagram_username', 50)->nullable();
            $table->string('facebook_username', 50)->nullable();
            $table->integer('submitted_at');
            $table->integer('resubmitted_at')->nullable();
            $table->boolean('ready_for_review')->default(false);
            $table->integer('reviewed_at')->nullable();
            $table->enum('review_status', ['pending', 'approved', 'rejected', 'discarded'])->default('pending');
            $table->text('review_message')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index(['player_id', 'review_status'], 'eligibilities_player_status_idx');
        });

        DB::statement(
            "ALTER TABLE `{$prefix}eligibilities` " .
            'ADD COLUMN `pending_player_id` BIGINT UNSIGNED ' .
            "GENERATED ALWAYS AS (CASE WHEN `review_status` = 'pending' THEN `player_id` ELSE NULL END) STORED"
        );
        DB::statement(
            'CREATE UNIQUE INDEX `eligibilities_one_pending_per_player` ' .
            "ON `{$prefix}eligibilities` (`pending_player_id`)"
        );

        Schema::create('eligibility_student_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eligibility_id')->constrained('eligibilities', 'id_eligibility')->cascadeOnDelete();
            $table->string('path', 512);
            $table->unsignedTinyInteger('sort_order');
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index('eligibility_id', 'eligibility_student_proofs_eligibility_id_idx');
        });

        Schema::create('eligibility_portrait_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eligibility_id')->constrained('eligibilities', 'id_eligibility')->cascadeOnDelete();
            $table->string('path', 512);
            $table->unsignedTinyInteger('sort_order');
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index('eligibility_id', 'eligibility_portrait_photos_eligibility_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibility_portrait_photos');
        Schema::dropIfExists('eligibility_student_proofs');
        Schema::dropIfExists('eligibilities');
        Schema::dropIfExists('validations_site');
        Schema::dropIfExists('matches_teams');
        Schema::dropIfExists('edition_division_replacements');
        Schema::dropIfExists('edition_division_standings');
        Schema::dropIfExists('match_players');
        Schema::dropIfExists('match_sides');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('edition_lottery_luckies');
        Schema::dropIfExists('edition_lottery_rules');
        Schema::dropIfExists('edition_lotteries');
        Schema::dropIfExists('prizes_from_clubs');
        Schema::dropIfExists('prizes');
        Schema::dropIfExists('edition_participations');
        Schema::dropIfExists('edition_division_posters');
        Schema::dropIfExists('edition_division_courts');
        Schema::dropIfExists('edition_divisions');
        Schema::dropIfExists('player_profile_images');
        Schema::dropIfExists('player_invite_events');
        Schema::dropIfExists('player_invites');
        Schema::dropIfExists('player_identities');
        Schema::dropIfExists('player_phones');
        Schema::dropIfExists('player_emails');
        Schema::dropIfExists('players');
        Schema::dropIfExists('editions');
        Schema::dropIfExists('courts');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('clubs');
    }
};
