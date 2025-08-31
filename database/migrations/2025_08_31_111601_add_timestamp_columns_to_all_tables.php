<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        // Add timestamp columns to clubs table
        Schema::table('clubs', function (Blueprint $table) {
            $table->integer('inserted_at')->after('city');
            $table->integer('updated_at')->nullable()->after('inserted_at');
        });

        // Add timestamp columns to divisions table
        Schema::table('divisions', function (Blueprint $table) {
            $table->integer('inserted_at')->after('its_first_edition_id');
            $table->integer('updated_at')->nullable()->after('inserted_at');
        });

        // Add timestamp columns to locations table
        Schema::table('locations', function (Blueprint $table) {
            $table->integer('inserted_at')->after('total_outdoor_courts');
            $table->integer('updated_at')->nullable()->after('inserted_at');
        });

        // Add timestamp columns to editions table
        Schema::table('editions', function (Blueprint $table) {
            $table->integer('inserted_at')->after('closing_time');
            $table->integer('updated_at')->nullable()->after('inserted_at');
        });

        // Add timestamp columns to edition_divisions table
        Schema::table('edition_divisions', function (Blueprint $table) {
            $table->integer('inserted_at')->after('fixed_teams');
            $table->integer('updated_at')->nullable()->after('inserted_at');
        });

        // Add timestamp columns to edition_participations table
        Schema::table('edition_participations', function (Blueprint $table) {
            $table->integer('inserted_at')->after('ban_reason');
            $table->integer('updated_at')->nullable()->after('inserted_at');
        });

        // Add timestamp columns to prizes table
        Schema::table('prizes', function (Blueprint $table) {
            $table->integer('inserted_at')->after('box_2_text_color');
            $table->integer('updated_at')->nullable()->after('inserted_at');
        });

        // Add timestamp columns to prizes_from_clubs table
        Schema::table('prizes_from_clubs', function (Blueprint $table) {
            $table->integer('inserted_at')->after('box_2_text_color');
            $table->integer('updated_at')->nullable()->after('inserted_at');
        });

        // Add timestamp columns to edition_lotteries table
        Schema::table('edition_lotteries', function (Blueprint $table) {
            $table->integer('inserted_at')->after('total_draws_nr');
            $table->integer('updated_at')->nullable()->after('inserted_at');
        });

        // Add timestamp columns to edition_lucky_ones table
        Schema::table('edition_lucky_ones', function (Blueprint $table) {
            $table->integer('inserted_at')->after('rejected_at');
            $table->integer('updated_at')->nullable()->after('inserted_at');
        });

        // Update existing rows to have inserted_at set to 1990 year (631152000 unix timestamp)
        DB::table('clubs')->update(['inserted_at' => 631152000]);
        DB::table('divisions')->update(['inserted_at' => 631152000]);
        DB::table('locations')->update(['inserted_at' => 631152000]);
        DB::table('editions')->update(['inserted_at' => 631152000]);
        DB::table('edition_divisions')->update(['inserted_at' => 631152000]);
        DB::table('edition_participations')->update(['inserted_at' => 631152000]);
        DB::table('prizes')->update(['inserted_at' => 631152000]);
        DB::table('prizes_from_clubs')->update(['inserted_at' => 631152000]);
        DB::table('edition_lotteries')->update(['inserted_at' => 631152000]);
        DB::table('edition_lucky_ones')->update(['inserted_at' => 631152000]);
    }

    public function down()
    {
        // Remove timestamp columns from all tables
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn(['inserted_at', 'updated_at']);
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn(['inserted_at', 'updated_at']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['inserted_at', 'updated_at']);
        });

        Schema::table('editions', function (Blueprint $table) {
            $table->dropColumn(['inserted_at', 'updated_at']);
        });

        Schema::table('edition_divisions', function (Blueprint $table) {
            $table->dropColumn(['inserted_at', 'updated_at']);
        });

        Schema::table('edition_participations', function (Blueprint $table) {
            $table->dropColumn(['inserted_at', 'updated_at']);
        });

        Schema::table('prizes', function (Blueprint $table) {
            $table->dropColumn(['inserted_at', 'updated_at']);
        });

        Schema::table('prizes_from_clubs', function (Blueprint $table) {
            $table->dropColumn(['inserted_at', 'updated_at']);
        });

        Schema::table('edition_lotteries', function (Blueprint $table) {
            $table->dropColumn(['inserted_at', 'updated_at']);
        });

        Schema::table('edition_lucky_ones', function (Blueprint $table) {
            $table->dropColumn(['inserted_at', 'updated_at']);
        });
    }
};
