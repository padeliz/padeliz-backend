<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

// API Version 1 Routes
Route::prefix('v1')->group(function () {
    // ========================================
    // PLAYER AUTHENTICATION ROUTES
    // ========================================
    Route::prefix('app/auth')->group(function () {
        Route::post('google', [\App\Http\Controllers\Api\V1\App\PlayerAuthController::class, 'googleCallback']);

        if (App::environment() != 'production') {
            Route::post('dev-login', [\App\Http\Controllers\Api\V1\App\PlayerAuthController::class, 'devLogin']);
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
    Route::middleware(['auth:sanctum', 'user.type:player'])->prefix('app')->group(function () {
        // Player authentication
        Route::prefix('auth')->group(function () {
            Route::post('logout', [\App\Http\Controllers\Api\V1\App\PlayerAuthController::class, 'logout']);
            Route::get('me', [\App\Http\Controllers\Api\V1\App\PlayerAuthController::class, 'me']);
        });

        // Player management
        Route::prefix('players')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\V1\App\PlayerController::class, 'store']);
            Route::get('{player}', [\App\Http\Controllers\Api\V1\App\PlayerController::class, 'show']);
            Route::put('{player}', [\App\Http\Controllers\Api\V1\App\PlayerController::class, 'update']);
            Route::post('image', [\App\Http\Controllers\Api\V1\App\PlayerController::class, 'uploadImage']);
            Route::delete('image/{filename}', [\App\Http\Controllers\Api\V1\App\PlayerController::class, 'deleteImage']);
        });

        // Player participation
        Route::prefix('editions/{edition}')->group(function () {
            Route::post('participate', [\App\Http\Controllers\Api\V1\App\EditionParticipationController::class, 'store']);
            Route::delete('withdraw', [\App\Http\Controllers\Api\V1\App\EditionParticipationController::class, 'destroy']);
        });

        // Player matches
        Route::prefix('matches/teams')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\App\MatchesTeamController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\App\MatchesTeamController::class, 'store']);
        });

        // Player eligibility
        Route::prefix('eligibility')->group(function () {
            Route::get('approved', [\App\Http\Controllers\Api\V1\App\EligibilityController::class, 'approved']);
            Route::get('pending', [\App\Http\Controllers\Api\V1\App\EligibilityController::class, 'pending']);
            Route::get('rejected', [\App\Http\Controllers\Api\V1\App\EligibilityController::class, 'rejected']);
            Route::patch('/', [\App\Http\Controllers\Api\V1\App\EligibilityController::class, 'update']);
            Route::post('/', [\App\Http\Controllers\Api\V1\App\EligibilityController::class, 'store']);
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
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'bulkDestroy']);
            Route::delete('{club}', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'destroy']);
            Route::get('{club}/locations', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'locations']);
            Route::get('{club}/prizes', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'prizes']);
        });

        // Location management
        Route::prefix('locations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'index']);
            Route::get('{location}', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'store']);
            Route::put('{location}', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'update']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'bulkDestroy']);
            Route::delete('{location}', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'destroy']);
            Route::get('{location}/editions', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'editions']);
        });

        // Division management
        Route::prefix('divisions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'index']);
            Route::get('{division}', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'store']);
            Route::put('{division}', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'update']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'bulkDestroy']);
            Route::delete('{division}', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'destroy']);
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
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'bulkDestroy']);
            Route::delete('{edition}', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'destroy']);
            Route::get('{edition}/divisions', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'divisions']);
            Route::get('{edition}/participants', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'participants']);
            Route::get('{edition}/lotteries', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'lotteries']);
        });

        // Prize management
        Route::prefix('prizes')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'index']);
            Route::get('{prize}', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'store']);
            Route::put('{prize}', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'update']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'bulkDestroy']);
            Route::delete('{prize}', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'destroy']);
        });

        // Club prizes management
        Route::prefix('prizes-from-clubs')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PrizeFromClubController::class, 'index']);
            Route::get('{prizeFromClub}', [\App\Http\Controllers\Api\V1\Manager\PrizeFromClubController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\PrizeFromClubController::class, 'store']);
            Route::put('{prizeFromClub}', [\App\Http\Controllers\Api\V1\Manager\PrizeFromClubController::class, 'update']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\PrizeFromClubController::class, 'bulkDestroy']);
            Route::delete('{prizeFromClub}', [\App\Http\Controllers\Api\V1\Manager\PrizeFromClubController::class, 'destroy']);
        });

        // Lottery management
        Route::prefix('lotteries')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'index']);
            Route::get('{lottery}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'store']);
            Route::put('{lottery}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'update']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'bulkDestroy']);
            Route::delete('{lottery}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'destroy']);
            Route::get('{lottery}/lucky-ones', [\App\Http\Controllers\Api\V1\Manager\EditionLuckyOneController::class, 'index']);
        });

        // Lucky ones management
        Route::prefix('lucky-ones')->group(function () {
            Route::get('{luckyOne}', [\App\Http\Controllers\Api\V1\Manager\EditionLuckyOneController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EditionLuckyOneController::class, 'store']);
            Route::put('{luckyOne}', [\App\Http\Controllers\Api\V1\Manager\EditionLuckyOneController::class, 'update']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EditionLuckyOneController::class, 'bulkDestroy']);
            Route::delete('{luckyOne}', [\App\Http\Controllers\Api\V1\Manager\EditionLuckyOneController::class, 'destroy']);
        });

        // Edition divisions management
        Route::prefix('edition-divisions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'index']);
            Route::get('{editionDivision}', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'store']);
            Route::put('{editionDivision}', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'update']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'bulkDestroy']);
            Route::delete('{editionDivision}', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'destroy']);
            Route::get('{editionDivision}/participants', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'participants']);
        });

        // Player management
        Route::prefix('players')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'store']);
            Route::put('{player}', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'update']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'bulkDestroy']);
            Route::delete('{player}', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'destroy']);
        });

        // Participation management
        Route::prefix('editions/{edition}/participations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\ParticipationController::class, 'index']);
            Route::get('{participation}', [\App\Http\Controllers\Api\V1\Manager\ParticipationController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\ParticipationController::class, 'store']);
            Route::put('{participation}', [\App\Http\Controllers\Api\V1\Manager\ParticipationController::class, 'update']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\ParticipationController::class, 'bulkDestroy']);
            Route::delete('{participation}', [\App\Http\Controllers\Api\V1\Manager\ParticipationController::class, 'destroy']);
        });

        // Eligibility management
        Route::prefix('eligibility')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'index']);
            Route::get('{eligibility}', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'store']);
            Route::put('{eligibility}', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'update']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'bulkDestroy']);
            Route::delete('{eligibility}', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'destroy']);
        });

        // Matches teams management
        Route::prefix('matches-teams')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\MatchesTeamController::class, 'index']);
            Route::get('{team}', [\App\Http\Controllers\Api\V1\Manager\MatchesTeamController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\MatchesTeamController::class, 'store']);
            Route::put('{team}', [\App\Http\Controllers\Api\V1\Manager\MatchesTeamController::class, 'update']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\MatchesTeamController::class, 'bulkDestroy']);
            Route::delete('{team}', [\App\Http\Controllers\Api\V1\Manager\MatchesTeamController::class, 'destroy']);
        });
    });
});
