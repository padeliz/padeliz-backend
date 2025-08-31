<?php

declare(strict_types=1);

use App\Models\Player;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPlayerDetailsColumns extends Migration
{
    public function up()
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('google_avatar');
            $table->float('skill_division', 3, 1)->nullable()->after('phone');
            $table->string('first_name', 50)->nullable()->after('skill_division');
            $table->string('middle_name', 50)->nullable()->after('first_name');
            $table->string('last_name', 50)->nullable()->after('middle_name');
            $table->integer('year_of_birth')->nullable()->after('last_name');
            $table->boolean('being_student')->default(false)->after('year_of_birth');
            $table->string('playtomic_id', 10)->nullable()->after('being_student');
            $table->string('rankedin_id', 15)->nullable()->after('playtomic_id');
            $table->string('instagram_username', 50)->nullable()->after('rankedin_id');
            $table->string('facebook_username', 50)->nullable()->after('instagram_username');
        });

        // Update existing players
        Player::chunk(100, function ($players) {
            foreach ($players as $player) {
                $nameParts = explode(' ', trim($player->name));

                if (count($nameParts) >= 2) {
                    // First name is always the first part
                    $firstName = $nameParts[0];

                    // Last name is the last part
                    $lastName = end($nameParts);

                    // Middle name is everything in between (if exists)
                    $middleName = null;
                    if (count($nameParts) > 2) {
                        // Get all parts except first and last, join with space
                        $middleParts = array_slice($nameParts, 1, -1);
                        $middleName = implode(' ', $middleParts);
                    }

                    $player->update([
                        'first_name' => $firstName,
                        'middle_name' => $middleName,
                        'last_name' => $lastName,
                    ]);
                }
            }
        });
    }

    public function down()
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'skill_division',
                'first_name',
                'middle_name',
                'last_name',
                'year_of_birth',
                'being_student',
                'playtomic_id',
                'rankedin_id',
                'instagram_username',
                'facebook_username',
            ]);
        });
    }
}
