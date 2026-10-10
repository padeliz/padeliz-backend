<?php

declare(strict_types=1);

use App\Services\PlayerLevel\LevelBand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $prefix = DB::getTablePrefix();

        Schema::create('clubs', function (Blueprint $table) {
            $table->id('id_club');
            $table->string('name', 30)->unique();
            $table->string('city', 30)->default('Bucharest');
            $table->string('logo_path', 512)->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('organizers', function (Blueprint $table) {
            $table->id('id_organizer');
            $table->string('name', 60)->unique();
            $table->string('logo_path', 512)->nullable();
            $table->boolean('show_event_label')->default(false);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        Schema::create('divisions', function (Blueprint $table) {
            $table->id('id_division');
            $table->string('name', 25)->unique('uq_divisions_name');
            $table->string('color', 7);
            // FK added after events table exists (see below)
            $table->unsignedBigInteger('its_first_event_id');
            $table->decimal('level_min', 3, 2)->nullable();
            $table->decimal('level_max', 3, 2)->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('levels', function (Blueprint $table) {
            $table->id('id_level');
            $table->string('name', 64)->unique();
            $table->decimal('level_min', 3, 2);
            $table->decimal('level_max', 3, 2);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        Schema::create('division_levels', function (Blueprint $table) {
            $table->id('id_division_level');
            $table->unsignedBigInteger('division_id');
            $table->unsignedBigInteger('level_id');
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['division_id', 'level_id'], 'uq_division_levels_pair');
            $table->foreign('division_id', 'fk_division_levels_division')
                ->references('id_division')->on('divisions')->cascadeOnDelete();
            $table->foreign('level_id', 'fk_division_levels_level')
                ->references('id_level')->on('levels')->cascadeOnDelete();
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

        Schema::create('events', function (Blueprint $table) {
            $table->id('id_event');
            $table->string('name', 25);
            $table->string('slug')->nullable();
            $table->text('description');
            $table->string('cover_path', 512)->nullable();
            $table->date('date');
            $table->foreignId('location_id')->constrained('locations', 'id_location');
            $table->foreignId('organizer_id')
                ->nullable()
                ->constrained('organizers', 'id_organizer')
                ->restrictOnDelete();
            $table->boolean('include_club_host_on_pdf')->default(true);
            $table->time('arriving_time');
            $table->time('closing_time');
            $table->integer('finalized_at')->nullable();
            $table->integer('registration_allowed_until');
            $table->integer('cancellation_allowed_until');
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->foreign('its_first_event_id', 'fk_divisions_its_first_event')
                ->references('id_event')
                ->on('events');
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id('id_player');
            $table->string('first_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->json('user_types')->nullable();
            $table->rememberToken();
            $table->string('created_via', 16)->nullable();
            $table->string('created_via_format', 32)->nullable();
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
            $table->boolean('is_avatar_primary')->default(false);
            $table->integer('linked_at');
            $table->integer('last_login_at')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['provider', 'provider_subject'], 'uq_player_identity_provider_subject');
        });

        Schema::create('media_sources', function (Blueprint $table) {
            $table->id('id_media_source');
            $table->string('code', 32)->unique();
            $table->string('label', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        Schema::create('player_media_sources', function (Blueprint $table) {
            $table->id('id_player_media_source');
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('media_source_id');
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['player_id', 'media_source_id'], 'uq_player_media_sources');
            $table->foreign('player_id', 'fk_pms_player')
                ->references('id_player')->on('players')->cascadeOnDelete();
            $table->foreign('media_source_id', 'fk_pms_source')
                ->references('id_media_source')->on('media_sources')->cascadeOnDelete();
        });

        Schema::create('player_friends_sources', function (Blueprint $table) {
            $table->id('id_player_friends_source');
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('friend_player_id')->nullable();
            $table->string('full_name', 120)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index('player_id', 'player_friends_sources_player_idx');
            $table->foreign('player_id', 'fk_pfs_player')
                ->references('id_player')->on('players')->cascadeOnDelete();
            $table->foreign('friend_player_id', 'fk_pfs_friend')
                ->references('id_player')->on('players')->nullOnDelete();
        });

        Schema::create('player_custom_field_submissions', function (Blueprint $table) {
            $table->id('id_player_custom_field_submission');
            $table->unsignedBigInteger('player_id');
            $table->string('submitted_via', 32);
            $table->unsignedBigInteger('submitted_by_player_id')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->foreign('player_id', 'fk_pcfs_player')
                ->references('id_player')->on('players')->cascadeOnDelete();
            $table->foreign('submitted_by_player_id', 'fk_pcfs_submitted_by')
                ->references('id_player')->on('players')->nullOnDelete();
        });

        Schema::create('player_level_assessments', function (Blueprint $table) {
            $table->id('id_player_level_assessment');
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('player_custom_field_submission_id');
            $table->integer('submitted_at')->nullable();
            $table->integer('resubmitted_at')->nullable();
            $table->boolean('ready_for_review')->default(false);
            $table->integer('reviewed_at')->nullable();
            $table->enum('review_status', ['pending', 'approved', 'rejected', 'discarded'])->default('pending');
            $table->text('review_message')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index(['player_id', 'review_status'], 'pla_player_status_idx');
            // No ON DELETE CASCADE: incompatible with generated pending_player_id.
            $table->foreign('player_id', 'fk_pla_player')
                ->references('id_player')->on('players');
            $table->foreign('player_custom_field_submission_id', 'fk_pla_submission')
                ->references('id_player_custom_field_submission')
                ->on('player_custom_field_submissions');
        });

        DB::statement(
            "ALTER TABLE `{$prefix}player_level_assessments` " .
            'ADD COLUMN `pending_player_id` BIGINT UNSIGNED ' .
            "GENERATED ALWAYS AS (CASE WHEN `review_status` = 'pending' THEN `player_id` ELSE NULL END) STORED"
        );
        DB::statement(
            'CREATE UNIQUE INDEX `pla_one_pending_per_player` ' .
            "ON `{$prefix}player_level_assessments` (`pending_player_id`)"
        );

        Schema::create('player_student_proofs', function (Blueprint $table) {
            $table->id('id_player_student_proof');
            $table->unsignedBigInteger('player_id');
            $table->date('student_until');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('ready_for_review')->default(false);
            $table->integer('reviewed_at')->nullable();
            $table->enum('review_status', ['pending', 'approved', 'rejected', 'discarded'])->default('pending');
            $table->text('review_message')->nullable();
            $table->integer('submitted_at')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index(['player_id', 'review_status'], 'psp_player_status_idx');
            $table->foreign('player_id', 'fk_psp_player')
                ->references('id_player')->on('players');
        });

        DB::statement(
            "ALTER TABLE `{$prefix}player_student_proofs` " .
            'ADD COLUMN `pending_player_id` BIGINT UNSIGNED ' .
            "GENERATED ALWAYS AS (CASE WHEN `review_status` = 'pending' THEN `player_id` ELSE NULL END) STORED"
        );
        DB::statement(
            'CREATE UNIQUE INDEX `psp_one_pending_per_player` ' .
            "ON `{$prefix}player_student_proofs` (`pending_player_id`)"
        );

        Schema::create('player_student_proof_files', function (Blueprint $table) {
            $table->id('id_player_student_proof_file');
            $table->unsignedBigInteger('student_proof_id');
            $table->string('student_proof_path', 512);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index(['student_proof_id', 'sort_order'], 'pspf_proof_sort_idx');
            $table->foreign('student_proof_id', 'fk_pspf_proof')
                ->references('id_player_student_proof')->on('player_student_proofs')
                ->cascadeOnDelete();
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

        Schema::create('player_profile_image_imports', function (Blueprint $table) {
            $table->id('id_player_profile_image_import');
            $table->unsignedBigInteger('imported_by_player_id')->nullable();
            $table->string('original_filename', 255);
            $table->unsignedInteger('included_count')->default(0);
            $table->unsignedInteger('uploaded_count')->default(0);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();

            $table->foreign('imported_by_player_id', 'ppii_imported_by_fk')
                ->references('id_player')
                ->on('players')
                ->nullOnDelete();
        });

        Schema::create('player_profile_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players', 'id_player')->cascadeOnDelete();
            $table->string('profile_path', 512);
            $table->unsignedTinyInteger('sort_order');
            $table->string('uploaded_via', 32)->nullable();
            $table->unsignedBigInteger('uploaded_via_import_id')->nullable();
            $table->unsignedBigInteger('uploaded_via_level_assessment_id')->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->boolean('is_pdf_primary')->default(false);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index('player_id', 'player_profile_images_player_id_idx');
            $table->foreign('uploaded_via_import_id', 'ppi_uploaded_via_import_fk')
                ->references('id_player_profile_image_import')
                ->on('player_profile_image_imports')
                ->nullOnDelete();
            $table->foreign('uploaded_via_level_assessment_id', 'ppi_uploaded_via_assessment_fk')
                ->references('id_player_level_assessment')
                ->on('player_level_assessments')
                ->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $table = Schema::getConnection()->getTablePrefix() . 'player_profile_images';
            DB::statement(
                'CREATE UNIQUE INDEX player_profile_images_one_pdf_primary '
                . 'ON `' . $table . '` ((IF(is_pdf_primary, player_id, NULL)))'
            );
        }

        Schema::create('player_profile_image_import_rows', function (Blueprint $table) {
            $table->id('id_player_profile_image_import_row');
            $table->unsignedBigInteger('player_profile_image_import_id');
            $table->unsignedInteger('row_index');
            $table->string('original_filename', 255);
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('player_profile_image_id');
            $table->boolean('is_pdf_primary')->default(false);
            $table->string('action', 32)->default('uploaded');
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();

            $table->foreign('player_profile_image_import_id', 'ppii_rows_import_fk')
                ->references('id_player_profile_image_import')
                ->on('player_profile_image_imports')
                ->cascadeOnDelete();
            $table->foreign('player_id', 'ppii_rows_player_fk')
                ->references('id_player')
                ->on('players')
                ->cascadeOnDelete();
            $table->foreign('player_profile_image_id', 'ppii_rows_image_fk')
                ->references('id')
                ->on('player_profile_images')
                ->cascadeOnDelete();
        });

        Schema::create('player_imports', function (Blueprint $table) {
            $table->id('id_player_import');
            $table->foreignId('imported_by_player_id')
                ->nullable()
                ->constrained('players', 'id_player')
                ->nullOnDelete();
            $table->string('format', 32);
            $table->string('original_filename', 255);
            $table->unsignedInteger('included_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('attached_count')->default(0);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        Schema::table('players', function (Blueprint $table) {
            $table->foreignId('created_via_import_id')
                ->nullable()
                ->after('created_via_format')
                ->constrained('player_imports', 'id_player_import')
                ->nullOnDelete();
        });

        Schema::create('player_import_rows', function (Blueprint $table) {
            $table->id('id_player_import_row');
            $table->foreignId('player_import_id')
                ->constrained('player_imports', 'id_player_import')
                ->cascadeOnDelete();
            $table->unsignedInteger('row_index');
            $table->string('first_name', 50)->nullable();
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('player_id')
                ->nullable()
                ->constrained('players', 'id_player')
                ->nullOnDelete();
            $table->string('action', 16);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        Schema::create('player_device_tokens', function (Blueprint $table) {
            $table->id('id_player_device_token');
            $table->foreignId('player_id')->constrained('players', 'id_player')->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 32)->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->index('player_id', 'player_device_tokens_player_id_idx');
        });

        Schema::create('event_divisions', function (Blueprint $table) {
            $table->id('id_event_division');
            $table->foreignId('event_id')->constrained('events', 'id_event');
            $table->foreignId('division_id')->constrained('divisions', 'id_division');
            $table->string('division_title', 64);
            $table->integer('players_limit');
            $table->integer('entry_fee')->comment('ron');
            $table->unsignedInteger('skill_order')->default(0);
            $table->decimal('level_min', 3, 2)->nullable();
            $table->decimal('level_max', 3, 2)->nullable();
            $table->time('playing_start_time');
            $table->time('playing_end_time');
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->index(['event_id', 'skill_order'], 'event_divisions_event_skill_order_idx');
        });

        Schema::create('event_division_schedules', function (Blueprint $table) {
            $table->id('id_event_division_schedule');
            $table->foreignId('event_division_id')
                ->unique()
                ->constrained('event_divisions', 'id_event_division')
                ->cascadeOnDelete();
            $table->integer('player_opponents_nr')->nullable();
            $table->integer('player_matches_repeat')->nullable()->default(1);
            $table->tinyInteger('include_scores')->default(1);
            $table->tinyInteger('has_demonstrative_match')->default(0);
            $table->boolean('allow_replacements')->default(true);
            $table->unsignedSmallInteger('points_per_match')->nullable();
            $table->unsignedSmallInteger('final_points_target')->default(24);
            $table->boolean('include_final')->default(false);
            $table->unsignedInteger('template_version')->nullable();
            $table->smallInteger('adjust')->default(0);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        Schema::create('event_division_courts', function (Blueprint $table) {
            $table->id('id_event_division_court');
            $table->foreignId('event_division_id')->constrained('event_divisions', 'id_event_division')->cascadeOnDelete();
            $table->foreignId('court_id')->constrained('courts', 'id_court');
            $table->smallInteger('display_order')->default(0);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['event_division_id', 'court_id'], 'uq_ed_court');
        });

        Schema::create('event_division_posters', function (Blueprint $table) {
            $table->id('id_event_division_poster');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('event_division_id')->nullable();
            $table->unsignedBigInteger('event_division_court_id')->nullable();
            $table->enum('poster_kind', ['MATCHES', 'RANKING', 'COMBINED', 'BATTLES', 'HOUSE', 'SETUP', 'ATTENDANCE']);
            $table->string('generated_path', 512)->nullable();
            $table->string('printed_path', 512)->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index('event_division_id', 'ed_posters_event_division_id_idx');
            $table->index('event_id', 'ed_posters_event_id_idx');
        });

        Schema::table('event_division_posters', function (Blueprint $table) {
            $table->foreign('event_id')
                ->references('id_event')->on('events')->cascadeOnDelete();
            $table->foreign('event_division_id')
                ->references('id_event_division')->on('event_divisions')->cascadeOnDelete();
            $table->foreign('event_division_court_id', 'fk_poster_ed_court')
                ->references('id_event_division_court')->on('event_division_courts');
        });

        Schema::create('event_participations', function (Blueprint $table) {
            $table->id('id_participation');
            $table->foreignId('event_id')->constrained('events', 'id_event');
            $table->foreignId('player_id')->constrained('players', 'id_player');
            $table->foreignId('event_division_id')->constrained('event_divisions', 'id_event_division');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_collecting_points')->default(true);
            $table->enum('absence_reason', ['SICK', 'FORGOT', 'EXAMS', 'RECONSIDERED'])->nullable();
            $table->enum('ban_reason', ['LEFT SUDDENLY', 'AGGRESSIVE PLAYER', 'LIBIDINOUS', ''])->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->unique(['event_division_id', 'player_id'], 'uq_participation_ed_player');
        });

        Schema::create('player_levels', function (Blueprint $table) {
            $table->id('id_player_level');
            $table->unsignedBigInteger('player_id');
            $table->decimal('level', 3, 2);
            $table->unsignedBigInteger('player_level_assessment_id')->nullable();
            $table->unsignedBigInteger('event_participation_id')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique('player_level_assessment_id', 'uq_player_levels_assessment');
            $table->unique('event_participation_id', 'uq_player_levels_participation');
            $table->index(['player_id', 'inserted_at'], 'player_levels_player_inserted_idx');
            $table->foreign('player_id', 'fk_player_levels_player')
                ->references('id_player')->on('players')->cascadeOnDelete();
            $table->foreign('player_level_assessment_id', 'fk_player_levels_assessment')
                ->references('id_player_level_assessment')
                ->on('player_level_assessments')
                ->nullOnDelete();
            $table->foreign('event_participation_id', 'fk_player_levels_participation')
                ->references('id_participation')
                ->on('event_participations')
                ->nullOnDelete();
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id('id_registration');
            $table->foreignId('event_id')->constrained('events', 'id_event');
            $table->foreignId('player_id')->constrained('players', 'id_player');
            $table->foreignId('event_division_id')
                ->constrained('event_divisions', 'id_event_division');
            $table->boolean('is_minor')->default(false);
            $table->boolean('is_student');
            $table->integer('payment')->comment('ron');
            $table->string('lifecycle_status', 32)->default('active');
            $table->string('payment_status', 32)->default('unpaid');
            $table->string('payment_method', 32)->nullable();
            $table->integer('paid_at')->nullable();
            $table->integer('payment_due_at')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('declined_at')->nullable();
            $table->string('cancel_reason', 64)->nullable();
            $table->string('decline_reason', 64)->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        DB::statement(
            "ALTER TABLE `{$prefix}event_registrations` " .
            'ADD COLUMN `active_player_for_division` BIGINT UNSIGNED ' .
            "GENERATED ALWAYS AS (CASE WHEN `lifecycle_status` = 'active' AND `event_division_id` IS NOT NULL THEN `player_id` ELSE NULL END) STORED"
        );
        DB::statement(
            'CREATE UNIQUE INDEX `event_registrations_one_active_per_division_player` ' .
            "ON `{$prefix}event_registrations` (`event_division_id`, `active_player_for_division`)"
        );
        DB::statement(
            "ALTER TABLE `{$prefix}event_registrations` " .
            'ADD COLUMN `active_player_for_event` BIGINT UNSIGNED ' .
            "GENERATED ALWAYS AS (CASE WHEN `lifecycle_status` = 'active' AND `event_division_id` IS NULL THEN `player_id` ELSE NULL END) STORED"
        );
        DB::statement(
            'CREATE UNIQUE INDEX `event_registrations_one_active_event_wide` ' .
            "ON `{$prefix}event_registrations` (`event_id`, `active_player_for_event`)"
        );

        Schema::create('event_registration_checkouts', function (Blueprint $table) {
            $table->id('id_checkout');
            $table->foreignId('event_id')
                ->constrained('events', 'id_event')
                ->cascadeOnDelete();
            $table->foreignId('player_id')
                ->constrained('players', 'id_player')
                ->cascadeOnDelete();
            $table->string('status', 32);
            $table->integer('total_amount')->default(0)->comment('ron payable total');
            $table->string('payment_method', 32)->nullable();
            $table->integer('paid_at')->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->index(['player_id', 'event_id', 'status'], 'checkouts_player_event_status_idx');
        });

        Schema::create('event_registration_checkout_items', function (Blueprint $table) {
            $table->id('id_checkout_item');
            $table->foreignId('checkout_id')
                ->constrained('event_registration_checkouts', 'id_checkout')
                ->cascadeOnDelete();
            $table->foreignId('registration_id')
                ->constrained('event_registrations', 'id_registration')
                ->cascadeOnDelete();
            $table->integer('base_amount_ron')->nullable()->comment('ron base fee snapshot');
            $table->integer('amount')->comment('ron snapshotted fee');
            $table->string('item_status', 32)->default('active');
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->unique(['checkout_id', 'registration_id'], 'checkout_items_checkout_registration_uq');
            $table->index('registration_id', 'checkout_items_registration_id_idx');
        });

        Schema::create('event_registration_payment_attempts', function (Blueprint $table) {
            $table->id('id_payment_attempt');
            $table->foreignId('checkout_id')
                ->constrained('event_registration_checkouts', 'id_checkout')
                ->cascadeOnDelete();
            $table->integer('amount')->comment('ron');
            $table->string('currency', 3)->default('RON');
            $table->string('status', 32);
            $table->string('ntp_id', 64)->nullable();
            $table->string('order_id', 128)->unique();
            $table->string('redirect_url', 1024)->nullable();
            $table->json('provider_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->json('coverage')->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->index('checkout_id', 'payment_attempts_checkout_id_idx');
            $table->index('ntp_id', 'payment_attempts_ntp_id_idx');
        });

        Schema::create('event_registration_payment_proofs', function (Blueprint $table) {
            $table->id('id_payment_proof');
            $table->foreignId('checkout_id')
                ->constrained('event_registration_checkouts', 'id_checkout')
                ->cascadeOnDelete();
            $table->string('proof_path', 512);
            $table->string('review_status', 32)->default('pending');
            $table->string('review_message', 1000)->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('players', 'id_player')
                ->nullOnDelete();
            $table->integer('reviewed_at')->nullable();
            $table->json('coverage')->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->index('checkout_id', 'payment_proofs_checkout_id_idx');
        });

        Schema::create('event_registration_payment_refunds', function (Blueprint $table) {
            $table->id('id_refund');
            $table->unsignedBigInteger('checkout_id');
            $table->unsignedBigInteger('checkout_item_id')->nullable();
            $table->unsignedBigInteger('payment_attempt_id')->nullable();
            $table->integer('amount')->comment('ron');
            $table->string('currency', 3)->default('RON');
            $table->string('status', 32);
            $table->string('provider_refund_id', 128)->nullable();
            $table->json('provider_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->index(['checkout_id', 'status'], 'pay_refunds_checkout_status_idx');
            $table->index(
                ['checkout_id', 'checkout_item_id', 'payment_attempt_id'],
                'pay_refunds_lookup_idx'
            );
            $table->foreign('checkout_id', 'pay_refunds_checkout_fk')
                ->references('id_checkout')
                ->on('event_registration_checkouts')
                ->cascadeOnDelete();
            $table->foreign('checkout_item_id', 'pay_refunds_item_fk')
                ->references('id_checkout_item')
                ->on('event_registration_checkout_items')
                ->nullOnDelete();
            $table->foreign('payment_attempt_id', 'pay_refunds_attempt_fk')
                ->references('id_payment_attempt')
                ->on('event_registration_payment_attempts')
                ->nullOnDelete();
        });

        Schema::create('bank_transfer_settings', function (Blueprint $table) {
            $table->id('id_bank_transfer_settings');
            $table->string('iban', 64);
            $table->string('beneficiary_name', 255);
            $table->string('bank_name', 255)->nullable();
            $table->string('payment_reference_template', 255)->default('PAD-{checkout_id}');
            $table->text('extra_instructions')->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('prizes', function (Blueprint $table) {
            $table->id('id_prize');
            $table->string('name', 60);
            $table->string('media_path', 512)->nullable();
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

        Schema::create('sponsors', function (Blueprint $table) {
            $table->id('id_sponsor');
            $table->string('name', 60)->unique();
            $table->foreignId('club_id')->nullable()->unique()->constrained('clubs', 'id_club');
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('sponsor_players', function (Blueprint $table) {
            $table->id('id_sponsor_player');
            $table->foreignId('sponsor_id')->constrained('sponsors', 'id_sponsor');
            $table->foreignId('player_id')->constrained('players', 'id_player');
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['sponsor_id', 'player_id']);
        });

        Schema::create('sponsor_prizes', function (Blueprint $table) {
            $table->id('id_sponsor_prize');
            $table->foreignId('sponsor_id')->constrained('sponsors', 'id_sponsor');
            $table->foreignId('prize_id')->constrained('prizes', 'id_prize');
            $table->string('media_path', 512)->nullable();
            $table->enum('template', ['IMAGE_LEFT', 'IMAGE_TOP', 'IMAGE_RIGHT', 'IMAGE_MIDDLE', 'IMAGE_BOTTOM'])->nullable();
            $table->string('box_1_text', 200)->nullable();
            $table->string('box_1_bg_color', 7)->nullable();
            $table->string('box_1_text_color', 7)->nullable();
            $table->string('box_2_text', 200)->nullable();
            $table->string('box_2_bg_color', 7)->nullable();
            $table->string('box_2_text_color', 7)->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->unique(['sponsor_id', 'prize_id']);
        });

        Schema::create('event_lotteries', function (Blueprint $table) {
            $table->id('id_lottery');
            $table->unsignedBigInteger('event_id');
            $table->string('name', 60);
            $table->smallInteger('display_order')->default(0);
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->index('event_id', 'pm_event_lotteries_event_id_foreign');
            $table->unique(['event_id', 'name']);
            $table->foreign('event_id')->references('id_event')->on('events');
        });

        Schema::create('event_lottery_prizes', function (Blueprint $table) {
            $table->id('id_lottery_prize');
            $table->foreignId('lottery_id')->constrained('event_lotteries', 'id_lottery');
            $table->foreignId('prize_id')->constrained('prizes', 'id_prize');
            $table->tinyInteger('prize_quantity');
            $table->foreignId('sponsor_id')->nullable()->constrained('sponsors', 'id_sponsor');
            $table->smallInteger('display_order')->default(0);
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('event_lottery_rules', function (Blueprint $table) {
            $table->id('id_lottery_rule');
            $table->foreignId('lottery_prize_id')->constrained('event_lottery_prizes', 'id_lottery_prize');
            $table->tinyInteger('rule_prize_quantity');
            $table->tinyInteger('orderliness')->default(0);
            $table->foreignId('event_division_id')->nullable()->constrained('event_divisions', 'id_event_division');
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

        Schema::create('event_lottery_luckies', function (Blueprint $table) {
            $table->id('id_lucky');
            $table->foreignId('lottery_rule_id')->nullable()->constrained('event_lottery_rules', 'id_lottery_rule');
            $table->foreignId('drawn_participation_id')->constrained('event_participations', 'id_participation');
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
            $table->foreignId('event_division_id')->constrained('event_divisions', 'id_event_division');
            $table->foreignId('event_division_court_id')->constrained('event_division_courts', 'id_event_division_court');
            $table->enum('match_kind', ['REGULAR', 'FINAL', 'DEMONSTRATIVE']);
            $table->smallInteger('sequence_no');
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();
            $table->unsignedSmallInteger('points_target')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(
                ['event_division_id', 'match_kind', 'event_division_court_id', 'sequence_no'],
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
            $table->foreignId('participation_id')->nullable()->constrained('event_participations', 'id_participation');
            $table->foreignId('player_id')->nullable()->constrained('players', 'id_player');
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['match_side_id', 'slot'], 'uq_match_side_slot');
        });

        Schema::create('event_division_standings', function (Blueprint $table) {
            $table->id('id_standing');
            $table->foreignId('event_division_id')->constrained('event_divisions', 'id_event_division');
            $table->foreignId('participation_id')->constrained('event_participations', 'id_participation');
            $table->integer('points_total')->nullable();
            $table->decimal('wins', 4, 1)->nullable();
            $table->smallInteger('rank')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['event_division_id', 'participation_id'], 'uq_standing_participation');
        });

        Schema::create('event_division_replacements', function (Blueprint $table) {
            $table->id('id_replacement');
            $table->unsignedBigInteger('event_division_id');
            $table->unsignedBigInteger('original_participation_id');
            $table->unsignedBigInteger('substitute_participation_id')->nullable();
            $table->unsignedBigInteger('substitute_player_id')->nullable();
            $table->unsignedBigInteger('match_id')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index('event_division_id', 'idx_repl_event_division');
        });

        Schema::table('event_division_replacements', function (Blueprint $table) {
            $table->foreign('event_division_id', 'fk_repl_ed')
                ->references('id_event_division')->on('event_divisions');
            $table->foreign('original_participation_id', 'fk_repl_orig')
                ->references('id_participation')->on('event_participations');
            $table->foreign('substitute_participation_id', 'fk_repl_sub_part')
                ->references('id_participation')->on('event_participations');
            $table->foreign('substitute_player_id', 'fk_repl_sub_player')
                ->references('id_player')->on('players');
            $table->foreign('match_id', 'fk_repl_match')
                ->references('id_match')->on('matches');
        });

        DB::statement(
            "ALTER TABLE `{$prefix}event_division_replacements` " .
            'ADD CONSTRAINT `chk_repl_at_most_one_substitute` ' .
            'CHECK (`substitute_participation_id` IS NULL OR `substitute_player_id` IS NULL)'
        );

        Schema::create('cached_pdf_pair_images', function (Blueprint $table) {
            $table->id('id_cached_pdf_pair_image');
            $table->foreignId('player_1_id')->constrained('players', 'id_player');
            $table->foreignId('player_2_id')->constrained('players', 'id_player');
            $table->boolean('player_1_is_collecting_points')->default(true);
            $table->boolean('player_2_is_collecting_points')->default(true);
            $table->string('picture_path', 512)->nullable();
            $table->unsignedBigInteger('source_player_1_profile_image_id')->nullable();
            $table->unsignedBigInteger('source_player_2_profile_image_id')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->foreign('source_player_1_profile_image_id', 'cached_pdf_pair_src1_ppi_fk')
                ->references('id')
                ->on('player_profile_images')
                ->nullOnDelete();
            $table->foreign('source_player_2_profile_image_id', 'cached_pdf_pair_src2_ppi_fk')
                ->references('id')
                ->on('player_profile_images')
                ->nullOnDelete();
            $table->unique(
                [
                    'player_1_id',
                    'player_2_id',
                    'player_1_is_collecting_points',
                    'player_2_is_collecting_points',
                ],
                'uq_cached_pdf_pair_images_combo'
            );
        });

        Schema::create('cached_pdf_player_images', function (Blueprint $table) {
            $table->id('id_cached_pdf_player_image');
            $table->foreignId('player_id')->constrained('players', 'id_player');
            $table->boolean('player_is_collecting_points')->default(true);
            $table->string('picture_path', 512)->nullable();
            $table->unsignedBigInteger('source_player_profile_image_id')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->foreign('source_player_profile_image_id', 'cached_pdf_player_src_ppi_fk')
                ->references('id')
                ->on('player_profile_images')
                ->nullOnDelete();
            $table->unique(
                ['player_id', 'player_is_collecting_points'],
                'uq_cached_pdf_player_images_combo'
            );
        });

        Schema::create('validations_site', function (Blueprint $table) {
            $table->string('error', 100)->primary();
            $table->integer('vars', false, true)->nullable()->default(0);
            $table->mediumText('message_en');
        });

        Schema::create('player_custom_fields', function (Blueprint $table) {
            $table->id('id_player_custom_field');
            $table->string('code', 64)->unique();
            $table->string('label', 255);
            $table->string('help_text', 512)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type', 32);
            $table->json('rules_json')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('archived_at')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
        });

        Schema::create('player_custom_field_options', function (Blueprint $table) {
            $table->id('id_player_custom_field_option');
            $table->unsignedBigInteger('player_custom_field_id');
            $table->string('code', 64);
            $table->string('label', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->unique(['player_custom_field_id', 'code'], 'uq_pcf_option_field_code');
            $table->foreign('player_custom_field_id', 'fk_pcfo_field')
                ->references('id_player_custom_field')
                ->on('player_custom_fields')
                ->cascadeOnDelete();
        });

        Schema::create('player_custom_field_value_imports', function (Blueprint $table) {
            $table->id('id_player_custom_field_value_import');
            $table->unsignedBigInteger('imported_by_player_id')->nullable();
            $table->string('format', 64);
            $table->string('original_filename', 255);
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedInteger('included_count')->default(0);
            $table->unsignedInteger('values_new_count')->default(0);
            $table->unsignedInteger('values_replaced_count')->default(0);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->foreign('imported_by_player_id', 'fk_pcfvi_importer')
                ->references('id_player')
                ->on('players')
                ->nullOnDelete();
            $table->foreign('event_id', 'fk_pcfvi_event')
                ->references('id_event')
                ->on('events')
                ->nullOnDelete();
        });

        Schema::create('player_custom_field_value_import_rows', function (Blueprint $table) {
            $table->id('id_player_custom_field_value_import_row');
            $table->unsignedBigInteger('player_custom_field_value_import_id');
            $table->unsignedInteger('row_index');
            $table->string('full_name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->unsignedBigInteger('player_id');
            $table->string('action', 16);
            $table->json('answers_json');
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index('player_custom_field_value_import_id', 'pcfvir_import_id_idx');
            $table->foreign('player_custom_field_value_import_id', 'fk_pcfvir_import')
                ->references('id_player_custom_field_value_import')
                ->on('player_custom_field_value_imports')
                ->cascadeOnDelete();
            $table->foreign('player_id', 'fk_pcfvir_player')
                ->references('id_player')
                ->on('players')
                ->cascadeOnDelete();
        });

        Schema::create('player_custom_field_values', function (Blueprint $table) {
            $table->id('id_player_custom_field_value');
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('player_custom_field_id');
            $table->unsignedBigInteger('submission_id');
            $table->text('value_text')->nullable();
            $table->integer('value_integer')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->json('value_json')->nullable();
            $table->unsignedBigInteger('submitted_by_player_id')->nullable();
            $table->string('submitted_via', 16);
            $table->unsignedBigInteger('created_via_import_id')->nullable();
            $table->enum('review_status', ['pending', 'validated', 'rejected', 'discarded'])->default('pending');
            $table->integer('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_manager_id')->nullable();
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->index(['player_id', 'player_custom_field_id'], 'pcfv_player_field_idx');
            $table->index('player_custom_field_id', 'pcfv_field_idx');
            $table->index(
                ['player_id', 'player_custom_field_id', 'review_status', 'reviewed_at'],
                'pcfv_current_idx'
            );
            $table->foreign('player_id', 'fk_pcfv_player')
                ->references('id_player')
                ->on('players')
                ->cascadeOnDelete();
            $table->foreign('player_custom_field_id', 'fk_pcfv_field')
                ->references('id_player_custom_field')
                ->on('player_custom_fields')
                ->restrictOnDelete();
            $table->foreign('submission_id', 'fk_pcfv_submission')
                ->references('id_player_custom_field_submission')
                ->on('player_custom_field_submissions')
                ->restrictOnDelete();
            $table->foreign('submitted_by_player_id', 'fk_pcfv_submitted_by')
                ->references('id_player')
                ->on('players')
                ->nullOnDelete();
            $table->foreign('created_via_import_id', 'fk_pcfv_import')
                ->references('id_player_custom_field_value_import')
                ->on('player_custom_field_value_imports')
                ->nullOnDelete();
            $table->foreign('reviewed_by_manager_id', 'fk_pcfv_reviewed_by')
                ->references('id_player')
                ->on('players')
                ->nullOnDelete();
        });

        Schema::create('player_custom_field_value_options', function (Blueprint $table) {
            $table->id('id_player_custom_field_value_option');
            $table->unsignedBigInteger('player_custom_field_value_id');
            $table->unsignedBigInteger('option_id')->nullable();
            $table->string('option_label_snapshot', 255);
            $table->integer('inserted_at');
            $table->integer('updated_at')->nullable();
            $table->foreign('player_custom_field_value_id', 'fk_pcfvo_value')
                ->references('id_player_custom_field_value')
                ->on('player_custom_field_values')
                ->cascadeOnDelete();
            $table->foreign('option_id', 'fk_pcfvo_option')
                ->references('id_player_custom_field_option')
                ->on('player_custom_field_options')
                ->restrictOnDelete();
        });

        Schema::create('challenge_sessions', function (Blueprint $table) {
            $table->id('id_challenge_session');
            $table->string('type', 32);
            $table->string('status', 16);
            $table->string('title', 80);
            $table->string('bottom_text', 80);
            $table->integer('started_at');
            $table->integer('completed_at')->nullable();
            $table->json('state_json');
            $table->unsignedInteger('state_sequence')->default(0);
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->index(['type', 'status'], 'idx_challenge_sessions_type_status');
        });

        Schema::create('benefit_definitions', function (Blueprint $table) {
            $table->id('id_benefit');
            $table->string('slug', 64)->unique();
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_system')->default(false);
            $table->string('amount_type', 16);
            $table->integer('amount_value')->default(0);
            $table->integer('max_discount_ron')->nullable();
            $table->string('scope_type', 32);
            $table->foreignId('club_id')->nullable()->constrained('clubs', 'id_club')->nullOnDelete();
            $table->integer('valid_from')->nullable();
            $table->integer('valid_until')->nullable();
            $table->boolean('player_optional')->default(false);
            $table->string('stack_group', 64)->nullable();
            $table->string('exclusivity', 32)->default('stackable');
            $table->integer('priority')->default(100);
            $table->unsignedInteger('max_redemptions_per_player')->nullable();
            $table->unsignedInteger('max_redemptions_global')->nullable();
            $table->unsignedInteger('max_redemptions_per_event')->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('benefit_conditions', function (Blueprint $table) {
            $table->id('id_benefit_condition');
            $table->foreignId('benefit_definition_id')
                ->constrained('benefit_definitions', 'id_benefit')
                ->cascadeOnDelete();
            $table->string('condition_type', 64);
            $table->json('parameters')->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->index(['benefit_definition_id', 'condition_type'], 'benefit_conditions_def_type_idx');
        });

        Schema::create('benefit_events', function (Blueprint $table) {
            $table->id('id_benefit_event');
            $table->foreignId('benefit_definition_id')
                ->constrained('benefit_definitions', 'id_benefit')
                ->cascadeOnDelete();
            $table->foreignId('event_id')
                ->constrained('events', 'id_event')
                ->cascadeOnDelete();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->unique(['benefit_definition_id', 'event_id'], 'benefit_events_uq');
        });

        Schema::create('benefit_entitlements', function (Blueprint $table) {
            $table->id('id_entitlement');
            $table->foreignId('benefit_definition_id')
                ->constrained('benefit_definitions', 'id_benefit')
                ->cascadeOnDelete();
            $table->foreignId('player_id')
                ->constrained('players', 'id_player')
                ->cascadeOnDelete();
            $table->unsignedInteger('remaining_uses')->nullable();
            $table->integer('valid_from')->nullable();
            $table->integer('valid_until')->nullable();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 16)->default('active');
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->index(['player_id', 'status'], 'benefit_entitlements_player_status_idx');
            $table->index(['benefit_definition_id', 'player_id'], 'benefit_entitlements_benefit_player_idx');
        });

        Schema::create('event_referral_settings', function (Blueprint $table) {
            $table->id('id_event_referral_setting');
            $table->foreignId('event_id')
                ->unique()
                ->constrained('events', 'id_event')
                ->cascadeOnDelete();
            $table->unsignedInteger('code_ttl_hours');
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
        });

        Schema::create('event_referral_codes', function (Blueprint $table) {
            $table->id('id_referral_code');
            $table->foreignId('event_id')
                ->constrained('events', 'id_event')
                ->cascadeOnDelete();
            $table->foreignId('referrer_player_id')
                ->constrained('players', 'id_player')
                ->cascadeOnDelete();
            $table->string('code', 32);
            $table->integer('expires_at');
            $table->integer('revoked_at')->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->unique(['event_id', 'code'], 'event_referral_codes_event_code_uq');
            $table->index(['referrer_player_id', 'event_id'], 'event_referral_codes_referrer_event_idx');
        });

        Schema::create('event_referral_code_invitees', function (Blueprint $table) {
            $table->id('id_referral_code_invitee');
            $table->foreignId('referral_code_id')
                ->constrained('event_referral_codes', 'id_referral_code')
                ->cascadeOnDelete();
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('player_id')
                ->nullable()
                ->constrained('players', 'id_player')
                ->nullOnDelete();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->index(['referral_code_id'], 'referral_code_invitees_code_idx');
        });

        Schema::create('referral_touchpoints', function (Blueprint $table) {
            $table->id('id_touchpoint');
            $table->foreignId('event_id')
                ->constrained('events', 'id_event')
                ->cascadeOnDelete();
            $table->foreignId('referred_player_id')
                ->constrained('players', 'id_player')
                ->cascadeOnDelete();
            $table->foreignId('referrer_player_id')
                ->constrained('players', 'id_player')
                ->cascadeOnDelete();
            $table->foreignId('referral_code_id')
                ->constrained('event_referral_codes', 'id_referral_code')
                ->cascadeOnDelete();
            $table->boolean('discount_eligible')->default(false);
            $table->integer('touched_at');
            $table->json('client_metadata')->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->index(
                ['event_id', 'referred_player_id', 'discount_eligible'],
                'referral_touchpoints_event_referred_eligible_idx'
            );
        });

        Schema::create('event_referral_attributions', function (Blueprint $table) {
            $table->id('id_attribution');
            $table->foreignId('event_id')
                ->constrained('events', 'id_event')
                ->cascadeOnDelete();
            $table->foreignId('referred_player_id')
                ->constrained('players', 'id_player')
                ->cascadeOnDelete();
            $table->foreignId('referrer_player_id')
                ->constrained('players', 'id_player')
                ->cascadeOnDelete();
            $table->foreignId('referral_code_id')
                ->nullable()
                ->constrained('event_referral_codes', 'id_referral_code')
                ->nullOnDelete();
            $table->foreignId('checkout_id')
                ->nullable()
                ->constrained('event_registration_checkouts', 'id_checkout')
                ->nullOnDelete();
            $table->string('status', 16);
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->unique(['event_id', 'referred_player_id'], 'event_referral_attributions_event_referred_uq');
            $table->index(['referrer_player_id', 'event_id', 'status'], 'event_referral_attributions_referrer_status_idx');
        });

        Schema::create('benefit_redemptions', function (Blueprint $table) {
            $table->id('id_redemption');
            $table->foreignId('checkout_item_id')
                ->constrained('event_registration_checkout_items', 'id_checkout_item')
                ->cascadeOnDelete();
            $table->foreignId('benefit_definition_id')
                ->constrained('benefit_definitions', 'id_benefit')
                ->cascadeOnDelete();
            $table->foreignId('entitlement_id')
                ->nullable()
                ->constrained('benefit_entitlements', 'id_entitlement')
                ->nullOnDelete();
            $table->foreignId('referral_attribution_id')
                ->nullable()
                ->constrained('event_referral_attributions', 'id_attribution')
                ->nullOnDelete();
            $table->integer('amount_ron');
            $table->string('benefit_name', 128);
            $table->string('benefit_snapshot_hash', 64)->nullable();
            $table->string('selection_source', 16);
            $table->text('reason')->nullable();
            $table->integer('inserted_at')->nullable();
            $table->integer('updated_at')->nullable();
            $table->index(['checkout_item_id'], 'benefit_redemptions_item_idx');
        });

        $now = time();
        $this->seedLevels($now);
        $this->seedMediaSources($now);
        $this->seedPlayerCustomFields($now);
        $this->seedSystemBenefits($now);
    }

    private function seedLevels(int $now): void
    {
        foreach (LevelBand::catalog() as $band) {
            DB::table('levels')->insert([
                'name' => $band->name,
                'level_min' => $band->levelMin,
                'level_max' => $band->levelMax,
                'inserted_at' => $now,
                'updated_at' => null,
            ]);
        }
    }

    private function seedMediaSources(int $now): void
    {
        $sources = [
            ['instagram', 'Instagram', 1],
            ['tiktok', 'TikTok', 2],
            ['facebook', 'Facebook', 3],
            ['friends', 'Friends', 4],
        ];

        foreach ($sources as [$code, $label, $sort]) {
            DB::table('media_sources')->insert([
                'code' => $code,
                'label' => $label,
                'sort_order' => $sort,
                'inserted_at' => $now,
                'updated_at' => null,
            ]);
        }
    }

    private function seedPlayerCustomFields(int $now): void
    {
        $fields = [
            [
                'code' => 'racket_experience',
                'label' => 'Racket experience',
                'help_text' => 'Sports you have played and how much.',
                'sort_order' => 1,
                'type' => 'json',
                'rules_json' => json_encode([
                    'sports' => ['padel', 'tennis', 'badminton', 'squash', 'ping-pong', 'pickleball'],
                    'levels' => ['amateur', 'intermediate', 'advanced', 'professional'],
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'code' => 'big_tournament_division',
                'label' => 'Big tournament division',
                'help_text' => 'If you played major tournaments, which division fit you?',
                'sort_order' => 2,
                'type' => 'labeled_foreign_id',
                'rules_json' => json_encode(['catalog' => 'levels'], JSON_THROW_ON_ERROR),
            ],
            [
                'code' => 'year_of_birth',
                'label' => 'Year of birth',
                'help_text' => 'Four-digit year you were born.',
                'sort_order' => 3,
                'type' => 'integer',
                'rules_json' => json_encode(['min' => 1900, 'max' => 2100], JSON_THROW_ON_ERROR),
            ],
            [
                'code' => 'playtomic_id',
                'label' => 'Playtomic ID',
                'help_text' => 'Digits from your Playtomic profile URL.',
                'sort_order' => 4,
                'type' => 'text',
                'rules_json' => json_encode(['max_length' => 32], JSON_THROW_ON_ERROR),
            ],
            [
                'code' => 'rankedin_id',
                'label' => 'Rankedin ID',
                'help_text' => 'Your Rankedin player id, if any.',
                'sort_order' => 5,
                'type' => 'text',
                'rules_json' => json_encode(['max_length' => 32], JSON_THROW_ON_ERROR),
            ],
            [
                'code' => 'gender',
                'label' => 'Gender',
                'help_text' => 'Select your gender.',
                'sort_order' => 6,
                'type' => 'single_select',
                'rules_json' => null,
            ],
            [
                'code' => 'instagram_username',
                'label' => 'Instagram',
                'help_text' => 'Username without @.',
                'sort_order' => 7,
                'type' => 'text',
                'rules_json' => json_encode(['max_length' => 50], JSON_THROW_ON_ERROR),
            ],
            [
                'code' => 'facebook_username',
                'label' => 'Facebook',
                'help_text' => 'Username or profile name.',
                'sort_order' => 8,
                'type' => 'text',
                'rules_json' => json_encode(['max_length' => 50], JSON_THROW_ON_ERROR),
            ],
        ];

        foreach ($fields as $field) {
            $id = DB::table('player_custom_fields')->insertGetId([
                'code' => $field['code'],
                'label' => $field['label'],
                'help_text' => $field['help_text'],
                'sort_order' => $field['sort_order'],
                'type' => $field['type'],
                'rules_json' => $field['rules_json'],
                'is_required' => false,
                'archived_at' => null,
                'inserted_at' => $now,
                'updated_at' => null,
            ], 'id_player_custom_field');

            if ($field['code'] === 'gender') {
                foreach ([['female', 'Female', 1], ['male', 'Male', 2]] as [$code, $label, $sort]) {
                    DB::table('player_custom_field_options')->insert([
                        'player_custom_field_id' => $id,
                        'code' => $code,
                        'label' => $label,
                        'sort_order' => $sort,
                        'is_active' => true,
                        'inserted_at' => $now,
                        'updated_at' => null,
                    ]);
                }
            }
        }
    }

    private function seedSystemBenefits(int $now): void
    {
        $templates = [
            [
                'slug' => 'student',
                'name' => 'Student',
                'description' => 'Discount for players with student eligibility.',
                'is_active' => false,
                'is_system' => false,
                'amount_type' => 'fixed_ron',
                'amount_value' => 0,
                'scope_type' => 'lifetime',
                'player_optional' => false,
                'exclusivity' => 'stackable',
                'priority' => 10,
                'conditions' => [
                    ['condition_type' => 'student_proof_active', 'parameters' => json_encode((object) [])],
                ],
            ],
            [
                'slug' => 'referred',
                'name' => 'Referred player',
                'description' => 'Discount for newcomers attributed to a referrer on first join.',
                'is_active' => false,
                'is_system' => false,
                'amount_type' => 'fixed_ron',
                'amount_value' => 0,
                'scope_type' => 'lifetime',
                'player_optional' => false,
                'exclusivity' => 'stackable',
                'priority' => 20,
                'conditions' => [
                    ['condition_type' => 'referrer_selected', 'parameters' => json_encode((object) [])],
                    ['condition_type' => 'no_prior_paid_registration_in_edition', 'parameters' => json_encode((object) [])],
                ],
            ],
            [
                'slug' => 'referrer',
                'name' => 'Referrer reward',
                'description' => 'Reward entitlement granted after a referred player’s first paid registration.',
                'is_active' => false,
                'is_system' => false,
                'amount_type' => 'fixed_ron',
                'amount_value' => 0,
                'scope_type' => 'lifetime',
                'player_optional' => true,
                'exclusivity' => 'stackable',
                'priority' => 30,
                'conditions' => [],
            ],
            [
                'slug' => 'early_bird',
                'name' => 'Early bird',
                'description' => 'Discount for joining with enough days before the edition date.',
                'is_active' => false,
                'is_system' => false,
                'amount_type' => 'fixed_ron',
                'amount_value' => 0,
                'scope_type' => 'lifetime',
                'player_optional' => false,
                'exclusivity' => 'stackable',
                'priority' => 40,
                'conditions' => [
                    ['condition_type' => 'days_before_edition_date', 'parameters' => json_encode(['min_days' => 7])],
                ],
            ],
            [
                'slug' => 'manual_adjustment',
                'name' => 'Manual adjustment',
                'description' => 'Manager-only fixed RON reduction with required reason.',
                'is_active' => true,
                'is_system' => true,
                'amount_type' => 'fixed_ron',
                'amount_value' => 0,
                'scope_type' => 'lifetime',
                'player_optional' => false,
                'exclusivity' => 'stackable',
                'priority' => 1000,
                'conditions' => [],
            ],
        ];

        foreach ($templates as $template) {
            $conditions = $template['conditions'];
            unset($template['conditions']);

            $id = DB::table('benefit_definitions')->insertGetId(array_merge($template, [
                'max_discount_ron' => null,
                'club_id' => null,
                'valid_from' => null,
                'valid_until' => null,
                'stack_group' => null,
                'max_redemptions_per_player' => null,
                'max_redemptions_global' => null,
                'max_redemptions_per_event' => null,
                'inserted_at' => $now,
                'updated_at' => $now,
            ]), 'id_benefit');

            foreach ($conditions as $condition) {
                DB::table('benefit_conditions')->insert([
                    'benefit_definition_id' => $id,
                    'condition_type' => $condition['condition_type'],
                    'parameters' => $condition['parameters'],
                    'inserted_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('benefit_redemptions');
        Schema::dropIfExists('event_referral_attributions');
        Schema::dropIfExists('referral_touchpoints');
        Schema::dropIfExists('event_referral_code_invitees');
        Schema::dropIfExists('event_referral_codes');
        Schema::dropIfExists('event_referral_settings');
        Schema::dropIfExists('benefit_entitlements');
        Schema::dropIfExists('benefit_events');
        Schema::dropIfExists('benefit_conditions');
        Schema::dropIfExists('benefit_definitions');
        Schema::dropIfExists('challenge_sessions');
        Schema::dropIfExists('player_custom_field_value_options');
        Schema::dropIfExists('player_custom_field_values');
        Schema::dropIfExists('player_custom_field_value_import_rows');
        Schema::dropIfExists('player_custom_field_value_imports');
        Schema::dropIfExists('player_custom_field_options');
        Schema::dropIfExists('player_custom_fields');
        Schema::dropIfExists('validations_site');
        Schema::dropIfExists('cached_pdf_player_images');
        Schema::dropIfExists('cached_pdf_pair_images');
        Schema::dropIfExists('event_division_replacements');
        Schema::dropIfExists('event_division_standings');
        Schema::dropIfExists('match_players');
        Schema::dropIfExists('match_sides');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('event_lottery_luckies');
        Schema::dropIfExists('event_lottery_rules');
        Schema::dropIfExists('event_lottery_prizes');
        Schema::dropIfExists('event_lotteries');
        Schema::dropIfExists('sponsor_prizes');
        Schema::dropIfExists('sponsor_players');
        Schema::dropIfExists('sponsors');
        Schema::dropIfExists('prizes');
        Schema::dropIfExists('bank_transfer_settings');
        Schema::dropIfExists('event_registration_payment_refunds');
        Schema::dropIfExists('event_registration_payment_proofs');
        Schema::dropIfExists('event_registration_payment_attempts');
        Schema::dropIfExists('event_registration_checkout_items');
        Schema::dropIfExists('event_registration_checkouts');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('player_levels');
        Schema::dropIfExists('event_participations');
        Schema::dropIfExists('event_division_posters');
        Schema::dropIfExists('event_division_courts');
        Schema::dropIfExists('event_division_schedules');
        Schema::dropIfExists('event_divisions');
        Schema::dropIfExists('player_device_tokens');
        Schema::dropIfExists('player_profile_image_import_rows');
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $table = Schema::getConnection()->getTablePrefix() . 'player_profile_images';
            DB::statement('DROP INDEX player_profile_images_one_pdf_primary ON `' . $table . '`');
        }
        Schema::dropIfExists('player_profile_images');
        Schema::dropIfExists('player_profile_image_imports');
        Schema::dropIfExists('player_invite_events');
        Schema::dropIfExists('player_invites');
        Schema::dropIfExists('player_student_proof_files');
        Schema::dropIfExists('player_student_proofs');
        Schema::dropIfExists('player_level_assessments');
        Schema::dropIfExists('player_custom_field_submissions');
        Schema::dropIfExists('player_friends_sources');
        Schema::dropIfExists('player_media_sources');
        Schema::dropIfExists('media_sources');
        Schema::dropIfExists('player_identities');
        Schema::dropIfExists('player_phones');
        Schema::dropIfExists('player_emails');
        Schema::dropIfExists('player_import_rows');
        Schema::table('players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_via_import_id');
        });
        Schema::dropIfExists('player_imports');
        Schema::dropIfExists('players');
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropForeign('fk_divisions_its_first_event');
        });
        Schema::dropIfExists('events');
        Schema::dropIfExists('courts');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('division_levels');
        Schema::dropIfExists('levels');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('organizers');
        Schema::dropIfExists('clubs');
    }
};
