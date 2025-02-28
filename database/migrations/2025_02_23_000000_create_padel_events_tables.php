<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Clubs table
        Schema::create('clubs', function (Blueprint $table) {
            $table->id('id_club');
            $table->string('name', 30)->unique();
            $table->string('city', 30)->default('Bucharest');
        });

        // Divisions table
        Schema::create('divisions', function (Blueprint $table) {
            $table->id('id_division');
            $table->string('name', 25);
            $table->string('color', 7);
            $table->tinyInteger('is_students_only');
            $table->integer('its_first_edition_id');
        });

        // Locations table
        Schema::create('locations', function (Blueprint $table) {
            $table->id('id_location');
            $table->foreignId('club_id')->constrained('clubs', 'id_club');
            $table->string('name', 30);
            $table->string('address', 200);
            $table->integer('total_indoors_courts');
            $table->integer('total_outdoor_courts');
        });

        // Editions table
        Schema::create('editions', function (Blueprint $table) {
            $table->id('id_edition');
            $table->string('name', 25);
            $table->date('date');
            $table->foreignId('location_id')->constrained('locations', 'id_location');
            $table->integer('entry_fee')->default(55)->comment('ron');
            $table->integer('students_entry_fee')->default(45)->comment('ron');
            $table->time('arriving_time');
            $table->time('closing_time');
        });

        // Players table
        Schema::create('players', function (Blueprint $table) {
            $table->id('id_player');
            $table->string('name', 250)->unique('uidx_name');
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        // Edition Divisions table
        Schema::create('edition_divisions', function (Blueprint $table) {
            $table->id('id_edition_division');
            $table->foreignId('edition_id')->constrained('editions', 'id_edition');
            $table->foreignId('division_id')->constrained('divisions', 'id_division');
            $table->integer('players_limit');
            $table->string('court', 30);
            $table->time('playing_start_time');
            $table->time('playing_end_time');
            $table->integer('player_opponents_nr')->nullable();
            $table->integer('player_matches_repeat')->default(1);
            $table->tinyInteger('include_scores')->default(1);
            $table->tinyInteger('has_demonstrative_match')->default(0);
            $table->tinyInteger('fixed_teams')->default(0);
        });

        // Edition Participations table
        Schema::create('edition_participations', function (Blueprint $table) {
            $table->id('id_participation');
            $table->foreignId('edition_id')->constrained('editions', 'id_edition');
            $table->foreignId('player_id')->constrained('players', 'id_player');
            $table->foreignId('division_id')->constrained('divisions', 'id_division');
            $table->boolean('is_minor')->default(false);
            $table->boolean('is_student');
            $table->integer('discount')->nullable()->comment('ron');
            $table->integer('payment')->comment('ron');
            $table->enum('absence_reason', ['SICK', 'FORGOT', 'EXAMS', 'RECONSIDERED'])->nullable();
            $table->enum('ban_reason', ['LEFT SUDDENLY', 'AGGRESSIVE PLAYER', 'LIBIDINOUS', ''])->nullable();
        });

        // Prizes table
        Schema::create('prizes', function (Blueprint $table) {
            $table->id('id_prize');
            $table->string('name', 60)->unique();
            $table->string('image', 100)->nullable();
            $table->string('box_1_text', 200);
            $table->string('box_1_bg_color', 7);
            $table->string('box_1_text_color', 7);
            $table->string('box_2_text', 200)->nullable();
            $table->string('box_2_bg_color', 7)->nullable();
            $table->string('box_2_text_color', 7)->nullable();
        });

        // Prizes from Clubs table
        Schema::create('prizes_from_clubs', function (Blueprint $table) {
            $table->id('id_club_prize');
            $table->foreignId('club_id')->constrained('clubs', 'id_club');
            $table->foreignId('prize_id')->constrained('prizes', 'id_prize');
            $table->string('image', 100)->nullable();
            $table->string('box_1_text', 200)->nullable();
            $table->string('box_1_bg_color', 7)->nullable();
            $table->string('box_1_text_color', 7)->nullable();
            $table->string('box_2_text', 200)->nullable();
            $table->string('box_2_bg_color', 7)->nullable();
            $table->string('box_2_text_color', 7)->nullable();
        });

        // Edition Lotteries table
        Schema::create('edition_lotteries', function (Blueprint $table) {
            $table->id('id_lottery');
            $table->foreignId('edition_id')->constrained('editions', 'id_edition');
            $table->integer('orderliness')->default(0);
            $table->foreignId('division_id')->nullable()->constrained('divisions', 'id_division');
            $table->foreignId('prize_id')->nullable()->constrained('prizes', 'id_prize');
            $table->tinyInteger('total_draws_nr');
        });

        // Edition Lucky Ones table
        Schema::create('edition_lucky_ones', function (Blueprint $table) {
            $table->id('id_lucky_one');
            $table->foreignId('lottery_id')->constrained('edition_lotteries', 'id_lottery');
            $table->foreignId('drawn_participation_id')->constrained('edition_participations', 'id_participation');
            $table->integer('drawn_at');
            $table->integer('available_at');
            $table->integer('announced_at')->nullable();
            $table->integer('accepted_at')->nullable();
            $table->integer('rejected_at')->nullable();
        });

        // Matches Teams table
        Schema::create('matches_teams', function (Blueprint $table) {
            $table->id('id_team');
            $table->string('player_1', 50);
            $table->string('player_2', 50);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        // Validations Site table
        Schema::create('validations_site', function (Blueprint $table) {
            $table->string('error', 100)->primary();
            $table->integer('vars', false, true)->nullable()->default(0);
            $table->mediumText('message_en');
        });
    }

    public function down()
    {
        // Drop tables in reverse order to avoid foreign key constraints
        Schema::dropIfExists('validations_site');
        Schema::dropIfExists('matches_teams');
        Schema::dropIfExists('edition_lucky_ones');
        Schema::dropIfExists('edition_lotteries');
        Schema::dropIfExists('prizes_from_clubs');
        Schema::dropIfExists('prizes');
        Schema::dropIfExists('edition_participations');
        Schema::dropIfExists('edition_divisions');
        Schema::dropIfExists('players');
        Schema::dropIfExists('editions');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('clubs');
    }
};
