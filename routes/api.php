<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    ClubController,
    LocationController,
    DivisionController,
    PlayerController,
    EditionController,
    EditionDivisionController,
    EditionParticipationController,
    PrizeController,
    PrizeFromClubController,
    EditionLotteryController,
    EditionLuckyOneController,
    MatchesTeamController
};

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public routes
Route::prefix('clubs')->group(function () {
    Route::get('/', [ClubController::class, 'index']);
    Route::get('{club}', [ClubController::class, 'show']);
    Route::get('{club}/locations', [ClubController::class, 'locations']);
    Route::get('{club}/prizes', [ClubController::class, 'prizes']);
});

Route::prefix('locations')->group(function () {
    Route::get('/', [LocationController::class, 'index']);
    Route::get('{location}', [LocationController::class, 'show']);
    Route::get('{location}/editions', [LocationController::class, 'editions']);
});

Route::prefix('divisions')->group(function () {
    Route::get('/', [DivisionController::class, 'index']);
    Route::get('{division}', [DivisionController::class, 'show']);
});

Route::prefix('editions')->group(function () {
    Route::get('/', [EditionController::class, 'index']);
    Route::get('upcoming', [EditionController::class, 'upcoming']);
    Route::get('ongoing', [EditionController::class, 'ongoing']);
    Route::get('past', [EditionController::class, 'past']);
    Route::get('{edition}', [EditionController::class, 'show']);
    Route::get('{edition}/divisions', [EditionController::class, 'divisions']);
    Route::get('{edition}/participants', [EditionController::class, 'participants']);
    Route::get('{edition}/lotteries', [EditionController::class, 'lotteries']);
});

Route::prefix('prizes')->group(function () {
    Route::get('/', [PrizeController::class, 'index']);
    Route::get('{prize}', [PrizeController::class, 'show']);
});

Route::prefix('lotteries')->group(function () {
    Route::get('{lottery}', [EditionLotteryController::class, 'show']);
    Route::get('{lottery}/lucky-ones', [EditionLuckyOneController::class, 'index']);
});

Route::prefix('lucky-ones')->group(function () {
    Route::get('{luckyOne}', [EditionLuckyOneController::class, 'show']);
});

// Add these in the public routes section
Route::prefix('edition-divisions')->group(function () {
    Route::get('/', [EditionDivisionController::class, 'index']);
    Route::get('{editionDivision}', [EditionDivisionController::class, 'show']);
    Route::get('{editionDivision}/participants', [EditionDivisionController::class, 'participants']);
});

Route::prefix('prizes-from-clubs')->group(function () {
    Route::get('/', [PrizeFromClubController::class, 'index']);
    Route::get('{prizeFromClub}', [PrizeFromClubController::class, 'show']);
});

// Protected routes (will need auth later)
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('players')->group(function () {
        Route::post('/', [PlayerController::class, 'store']);
        Route::get('{player}', [PlayerController::class, 'show']);
        Route::put('{player}', [PlayerController::class, 'update']);
    });

    Route::prefix('editions/{edition}')->group(function () {
        Route::post('participate', [EditionParticipationController::class, 'store']);
        Route::delete('withdraw', [EditionParticipationController::class, 'destroy']);
    });

    Route::prefix('matches/teams')->group(function () {
        Route::get('/', [MatchesTeamController::class, 'index']);
        Route::post('/', [MatchesTeamController::class, 'store']);
    });
});
