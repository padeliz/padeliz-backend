<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGoogleAuthFieldsToPlayersTable extends Migration
{
    public function up()
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('google_id')->unique()->after('name');
            $table->string('google_name')->after('google_id');
            $table->string('google_email')->after('google_name');
            $table->string('google_avatar')->after('google_email');
            $table->rememberToken()->after('google_avatar');
        });
    }

    public function down()
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn([
                'google_id',
                'google_name',
                'google_email',
                'google_avatar',
                'remember_token'
            ]);
        });
    }
}
