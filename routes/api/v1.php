<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\App;

// API Version 1 Routes
Route::prefix('v1')->group(function () {
    // ========================================
    // PLAYER AUTHENTICATION ROUTES
    // ========================================
    Route::prefix('player/auth')->group(function () {
        Route::post('google', [\App\Http\Controllers\Api\V1\Player\PlayerAuthController::class, 'googleCallback']);

        if (App::environment() != 'production') {
            Route::post('dev-login', [\App\Http\Controllers\Api\V1\Player\PlayerAuthController::class, 'devLogin']);
        }
    });

    // ========================================
    // MANAGER AUTHENTICATION ROUTES
    // ========================================
    Route::prefix('manager/auth')->group(function () {
        Route::post('google', [\App\Http\Controllers\Api\V1\Manager\ManagerAuthController::class, 'googleCallback']);

        if (App::environment() != 'production') {
            Route::post('dev-login', [\App\Http\Controllers\Api\V1\Manager\ManagerAuthController::class, 'devLogin']);
        }
    });

    // ========================================
    // PLAYER ROUTES (Requires player authentication)
    // ========================================
    Route::middleware(['auth:sanctum', 'user.type:player'])->prefix('player')->group(function () {
        // Player authentication
        Route::prefix('auth')->group(function () {
            Route::post('logout', [\App\Http\Controllers\Api\V1\Player\PlayerAuthController::class, 'logout']);
            Route::get('me', [\App\Http\Controllers\Api\V1\Player\PlayerAuthController::class, 'me']);
        });

        // Player profile management
        Route::prefix('profile')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\V1\Player\PlayerController::class, 'store']);
            Route::get('{player}', [\App\Http\Controllers\Api\V1\Player\PlayerController::class, 'show']);
            Route::put('{player}', [\App\Http\Controllers\Api\V1\Player\PlayerController::class, 'update']);
            Route::post('image', [\App\Http\Controllers\Api\V1\Player\PlayerController::class, 'uploadImage']);
            Route::delete('image/{filename}', [\App\Http\Controllers\Api\V1\Player\PlayerController::class, 'deleteImage']);
        });

        // Player participation
        Route::prefix('editions/{edition}')->group(function () {
            Route::post('participate', [\App\Http\Controllers\Api\V1\Player\EditionParticipationController::class, 'store']);
            Route::delete('withdraw', [\App\Http\Controllers\Api\V1\Player\EditionParticipationController::class, 'destroy']);
        });

        // Player matches
        Route::prefix('matches/teams')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Player\MatchesTeamController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Player\MatchesTeamController::class, 'store']);
        });

        // Player eligibility
        Route::prefix('eligibility')->group(function () {
            Route::get('approved', [\App\Http\Controllers\Api\V1\Player\EligibilityController::class, 'approved']);
            Route::get('pending', [\App\Http\Controllers\Api\V1\Player\EligibilityController::class, 'pending']);
            Route::get('rejected', [\App\Http\Controllers\Api\V1\Player\EligibilityController::class, 'rejected']);
            Route::patch('/', [\App\Http\Controllers\Api\V1\Player\EligibilityController::class, 'update']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Player\EligibilityController::class, 'store']);
        });
    });

    // ========================================
    // MANAGER ROUTES (Requires manager authentication)
    // ========================================
    Route::middleware(['auth:sanctum', 'user.type:manager'])->prefix('manager')->group(function () {
        // Manager authentication
        Route::prefix('auth')->group(function () {
            Route::post('logout', [\App\Http\Controllers\Api\V1\Manager\ManagerAuthController::class, 'logout']);
            Route::get('me', [\App\Http\Controllers\Api\V1\Manager\ManagerAuthController::class, 'me']);
        });

        // Club management
        Route::prefix('clubs')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'index']);
            Route::get('{club}', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'store']);
            Route::put('{club}', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'update']);
            Route::delete('{club}', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'destroy']);
            Route::get('{club}/locations', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'locations']);
            Route::get('{club}/prizes', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'prizes']);
        });

        // Location management
        Route::prefix('locations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'index']);
            Route::get('{location}', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'show']);
            Route::get('{location}/editions', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'editions']);
        });

        // Division management
        Route::prefix('divisions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'index']);
            Route::get('{division}', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'show']);
        });

        // Edition management
        Route::prefix('editions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'index']);
            Route::get('upcoming', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'upcoming']);
            Route::get('ongoing', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'ongoing']);
            Route::get('past', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'past']);
            Route::get('{edition}', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'store']);
            Route::put('{edition}', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'update']);
            Route::delete('{edition}', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'destroy']);
            Route::get('{edition}/divisions', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'divisions']);
            Route::get('{edition}/participants', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'participants']);
            Route::get('{edition}/lotteries', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'lotteries']);
        });

        // Prize management
        Route::prefix('prizes')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'index']);
            Route::get('{prize}', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'show']);
        });

        // Club prizes management
        Route::prefix('prizes-from-clubs')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PrizeFromClubController::class, 'index']);
            Route::get('{prizeFromClub}', [\App\Http\Controllers\Api\V1\Manager\PrizeFromClubController::class, 'show']);
        });

        // Lottery management
        Route::prefix('lotteries')->group(function () {
            Route::get('{lottery}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'show']);
            Route::get('{lottery}/lucky-ones', [\App\Http\Controllers\Api\V1\Manager\EditionLuckyOneController::class, 'index']);
        });

        // Lucky ones management
        Route::prefix('lucky-ones')->group(function () {
            Route::get('{luckyOne}', [\App\Http\Controllers\Api\V1\Manager\EditionLuckyOneController::class, 'show']);
        });

        // Edition divisions management
        Route::prefix('edition-divisions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'index']);
            Route::get('{editionDivision}', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'show']);
            Route::get('{editionDivision}/participants', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'participants']);
        });

        // Player management
        Route::prefix('players')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'store']);
            Route::put('{player}', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'update']);
            Route::delete('{player}', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'destroy']);
        });

        // Participation management
        Route::prefix('editions/{edition}/participations')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\ParticipationController::class, 'store']);
            Route::delete('{participation}', [\App\Http\Controllers\Api\V1\Manager\ParticipationController::class, 'destroy']);
        });

        // Eligibility management
        Route::prefix('eligibility')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'index']);
            Route::patch('{eligibility}', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'update']);
        });
    });
});
