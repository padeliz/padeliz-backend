<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlayerEligibilitiesTable extends Migration
{
    public function up()
    {
        Schema::create('player_eligibilities', function (Blueprint $table) {
            $table->id('id_eligibility');
            $table->foreignId('edition_id')->constrained('editions', 'id_edition');
            $table->foreignId('player_id')->constrained('players', 'id_player');

            // Player info snapshot
            $table->string('name');
            $table->string('phone')->nullable();
            $table->float('skill_division', 3, 1)->nullable();
            $table->string('first_name', 50)->nullable();
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->integer('year_of_birth')->nullable();
            $table->boolean('being_student')->default(false);
            $table->string('playtomic_id', 10)->nullable();
            $table->string('rankedin_id', 15)->nullable();
            $table->string('instagram_username', 50)->nullable();
            $table->string('facebook_username', 50)->nullable();

            // Review fields
            $table->integer('reviewed_at')->nullable();
            $table->enum('review_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('review_message')->nullable();
            $table->integer('resubmitted_at')->nullable();

            // Timestamps
            $table->integer('inserted_at');
            $table->integer('updated_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('player_eligibilities');
    }
}
