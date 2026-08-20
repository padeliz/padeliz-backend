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
        Route::post('invite/redeem', [\App\Http\Controllers\Api\V1\App\PlayerAuthController::class, 'redeemInvite']);

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
    // PLAYER PUBLIC ROUTES (no authentication)
    // ========================================
    Route::prefix('app')->group(function () {
        Route::get('editions', [\App\Http\Controllers\Api\V1\App\EditionController::class, 'index']);
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

        Route::prefix('my')->group(function () {
            Route::get('editions', [\App\Http\Controllers\Api\V1\App\MyEditionController::class, 'index']);
        });

        // Player management
        Route::prefix('players')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\V1\App\PlayerController::class, 'store']);
            Route::get('{player}', [\App\Http\Controllers\Api\V1\App\PlayerController::class, 'show']);
            Route::put('{player}', [\App\Http\Controllers\Api\V1\App\PlayerController::class, 'update']);
            Route::post('image', [\App\Http\Controllers\Api\V1\App\PlayerController::class, 'uploadImage']);
            Route::delete('image/{playerProfileImage}', [\App\Http\Controllers\Api\V1\App\PlayerController::class, 'deleteImage'])
                ->whereNumber('playerProfileImage');
        });

        // Player participation
        Route::prefix('edition-registrations')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\V1\App\EditionRegistrationController::class, 'store']);
            Route::delete('{editionRegistration}', [\App\Http\Controllers\Api\V1\App\EditionRegistrationController::class, 'destroy'])
                ->whereNumber('editionRegistration');
        });

        Route::prefix('my/eligibility')->group(function () {
            Route::get('status', [\App\Http\Controllers\Api\V1\App\MyEligibilityController::class, 'status']);
            Route::get('approved', [\App\Http\Controllers\Api\V1\App\MyEligibilityController::class, 'approved']);
            Route::get('latest', [\App\Http\Controllers\Api\V1\App\MyEligibilityController::class, 'latest']);
            Route::post('pending/student-proof', [\App\Http\Controllers\Api\V1\App\MyEligibilityUploadController::class, 'storeStudentProof']);
            Route::delete('pending/student-proof/{eligibilityStudentProof}', [\App\Http\Controllers\Api\V1\App\MyEligibilityUploadController::class, 'destroyStudentProof'])
                ->whereNumber('eligibilityStudentProof');
            Route::post('pending/portrait-photo', [\App\Http\Controllers\Api\V1\App\MyEligibilityUploadController::class, 'storePortraitPhoto']);
            Route::delete('pending/portrait-photo/{eligibilityPortraitPhoto}', [\App\Http\Controllers\Api\V1\App\MyEligibilityUploadController::class, 'destroyPortraitPhoto'])
                ->whereNumber('eligibilityPortraitPhoto');
            Route::patch('pending', [\App\Http\Controllers\Api\V1\App\MyEligibilityController::class, 'pending']);
            Route::post('discard', [\App\Http\Controllers\Api\V1\App\MyEligibilityController::class, 'discard']);
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

        Route::get('branding', [\App\Http\Controllers\Api\V1\Manager\BrandingController::class, 'index']);

        // Club management
        Route::prefix('clubs')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'index']);
            Route::get('{club}', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'store']);
            Route::put('{club}', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'update']);
            Route::patch('{club}', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'bulkDestroy']);
            Route::delete('{club}', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'destroy']);
        });

        // Location management
        Route::prefix('locations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'index']);
            Route::get('{location}', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'store']);
            Route::put('{location}', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'update']);
            Route::patch('{location}', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'bulkDestroy']);
            Route::delete('{location}', [\App\Http\Controllers\Api\V1\Manager\LocationController::class, 'destroy']);
        });

        // Division management
        Route::prefix('divisions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'index']);
            Route::get('{division}', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'store']);
            Route::put('{division}', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'update']);
            Route::patch('{division}', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'bulkDestroy']);
            Route::delete('{division}', [\App\Http\Controllers\Api\V1\Manager\DivisionController::class, 'destroy']);
        });

        // Edition management
        Route::prefix('editions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'index']);
            Route::get('{edition}', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'store']);
            Route::put('{edition}', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'update']);
            Route::patch('{edition}', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'patch']);
            Route::post('{edition}/finalize', [\App\Http\Controllers\Api\V1\Manager\EditionFinalizeController::class, 'store']);
            Route::get('{edition}/replacements', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionReplacementController::class, 'indexForEdition']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'bulkDestroy']);
            Route::delete('{edition}', [\App\Http\Controllers\Api\V1\Manager\EditionController::class, 'destroy']);
        });

        // Prize management
        Route::prefix('prizes')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'index']);
            Route::post('{prize}/media', [\App\Http\Controllers\Api\V1\Manager\PrizeMediaController::class, 'store'])
                ->whereNumber('prize');
            Route::delete('{prize}/media', [\App\Http\Controllers\Api\V1\Manager\PrizeMediaController::class, 'destroy'])
                ->whereNumber('prize');
            Route::get('{prize}', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'store']);
            Route::put('{prize}', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'update']);
            Route::patch('{prize}', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'bulkDestroy']);
            Route::delete('{prize}', [\App\Http\Controllers\Api\V1\Manager\PrizeController::class, 'destroy']);
        });

        // Sponsors
        Route::prefix('sponsors')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\SponsorController::class, 'index']);
            Route::get('{sponsor}', [\App\Http\Controllers\Api\V1\Manager\SponsorController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\SponsorController::class, 'store']);
            Route::put('{sponsor}', [\App\Http\Controllers\Api\V1\Manager\SponsorController::class, 'update']);
            Route::patch('{sponsor}', [\App\Http\Controllers\Api\V1\Manager\SponsorController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\SponsorController::class, 'bulkDestroy']);
            Route::delete('{sponsor}', [\App\Http\Controllers\Api\V1\Manager\SponsorController::class, 'destroy']);
        });

        // Sponsor prize overlays
        Route::prefix('sponsor-prizes')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\SponsorPrizeController::class, 'index']);
            Route::post('{sponsorPrize}/media', [\App\Http\Controllers\Api\V1\Manager\SponsorPrizeMediaController::class, 'store'])
                ->whereNumber('sponsorPrize');
            Route::delete('{sponsorPrize}/media', [\App\Http\Controllers\Api\V1\Manager\SponsorPrizeMediaController::class, 'destroy'])
                ->whereNumber('sponsorPrize');
            Route::get('{sponsorPrize}', [\App\Http\Controllers\Api\V1\Manager\SponsorPrizeController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\SponsorPrizeController::class, 'store']);
            Route::put('{sponsorPrize}', [\App\Http\Controllers\Api\V1\Manager\SponsorPrizeController::class, 'update']);
            Route::patch('{sponsorPrize}', [\App\Http\Controllers\Api\V1\Manager\SponsorPrizeController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\SponsorPrizeController::class, 'bulkDestroy']);
            Route::delete('{sponsorPrize}', [\App\Http\Controllers\Api\V1\Manager\SponsorPrizeController::class, 'destroy']);
        });

        // Lottery prize lines
        Route::prefix('edition-lottery-prizes')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryPrizeController::class, 'index']);
            Route::get('{lotteryPrize}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryPrizeController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryPrizeController::class, 'store']);
            Route::put('{lotteryPrize}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryPrizeController::class, 'update']);
            Route::patch('{lotteryPrize}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryPrizeController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryPrizeController::class, 'bulkDestroy']);
            Route::delete('{lotteryPrize}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryPrizeController::class, 'destroy']);
        });

        // Lottery management
        Route::prefix('edition-lotteries')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'index']);
            Route::get('{lottery}/live', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryLiveController::class, 'live']);
            Route::post('{lottery}/draw', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryLiveController::class, 'draw']);
            Route::post('{lottery}/announce', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryLiveController::class, 'announce']);
            Route::post('{lottery}/accept', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryLiveController::class, 'accept']);
            Route::post('{lottery}/reject', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryLiveController::class, 'reject']);
            Route::get('{lottery}/prizes-board', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryLiveController::class, 'prizesBoard']);
            Route::get('{lottery}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'store']);
            Route::put('{lottery}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'update']);
            Route::patch('{lottery}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'bulkDestroy']);
            Route::delete('{lottery}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryController::class, 'destroy']);
        });

        // Lottery luckies management
        Route::prefix('edition-lottery-luckies')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryLuckyController::class, 'index']);
            Route::get('{lucky}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryLuckyController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryLuckyController::class, 'store']);
            Route::put('{lucky}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryLuckyController::class, 'update']);
            Route::patch('{lucky}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryLuckyController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryLuckyController::class, 'bulkDestroy']);
            Route::delete('{lucky}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryLuckyController::class, 'destroy']);
        });

        // Courts management
        Route::prefix('courts')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\CourtController::class, 'index']);
            Route::get('{court}', [\App\Http\Controllers\Api\V1\Manager\CourtController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\CourtController::class, 'store']);
            Route::put('{court}', [\App\Http\Controllers\Api\V1\Manager\CourtController::class, 'update']);
            Route::patch('{court}', [\App\Http\Controllers\Api\V1\Manager\CourtController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\CourtController::class, 'bulkDestroy']);
            Route::delete('{court}', [\App\Http\Controllers\Api\V1\Manager\CourtController::class, 'destroy']);
        });

        // Lottery rules management
        Route::prefix('edition-lottery-rules')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryRuleController::class, 'index']);
            Route::get('{lotteryRule}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryRuleController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryRuleController::class, 'store']);
            Route::put('{lotteryRule}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryRuleController::class, 'update']);
            Route::patch('{lotteryRule}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryRuleController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryRuleController::class, 'bulkDestroy']);
            Route::delete('{lotteryRule}', [\App\Http\Controllers\Api\V1\Manager\EditionLotteryRuleController::class, 'destroy']);
        });

        // Scratch schedule tools (Mode B — no DB writes)
        Route::prefix('tools/schedule')->group(function () {
            Route::post('preview', [\App\Http\Controllers\Api\V1\Manager\ScratchScheduleController::class, 'preview']);
            Route::post('pdf/matches', [\App\Http\Controllers\Api\V1\Manager\ScratchScheduleController::class, 'pdfMatches']);
            Route::post('pdf/ranking', [\App\Http\Controllers\Api\V1\Manager\ScratchScheduleController::class, 'pdfRanking']);
        });

        // Template catalog / demo / metrics (Manager tools)
        Route::prefix('tools/templates')->group(function () {
            Route::get('catalog', [\App\Http\Controllers\Api\V1\Manager\TemplatesToolsController::class, 'catalog']);
            Route::get('metrics', [\App\Http\Controllers\Api\V1\Manager\TemplatesToolsController::class, 'metrics']);
            Route::get('demo/{players}/{partners}/{repeat}/{courts}', [\App\Http\Controllers\Api\V1\Manager\TemplatesToolsController::class, 'demo'])
                ->whereNumber('players')
                ->whereNumber('partners')
                ->whereNumber('repeat')
                ->whereNumber('courts');
        });

        // Match scores
        Route::patch('matches/{match}/scores', [\App\Http\Controllers\Api\V1\Manager\MatchScoreController::class, 'update']);

        // Standings manual edit
        Route::prefix('edition-division-standings')->group(function () {
            Route::put('{standing}', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionStandingController::class, 'update']);
            Route::patch('{standing}', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionStandingController::class, 'patch']);
        });

        // Replacements destroy / patch (by id)
        Route::delete('edition-division-replacements/{replacement}', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionReplacementController::class, 'destroy']);
        Route::patch('edition-division-replacements/{replacement}', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionReplacementController::class, 'patch']);

        // Edition divisions management
        Route::prefix('edition-divisions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'index']);
            Route::get('{editionDivision}', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'store']);
            Route::put('{editionDivision}', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'update']);
            Route::patch('{editionDivision}', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'bulkDestroy']);
            Route::delete('{editionDivision}', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionController::class, 'destroy']);

            // Mode A schedule
            Route::get('{editionDivision}/schedule', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionScheduleController::class, 'show']);
            Route::put('{editionDivision}/schedule/form', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionScheduleController::class, 'updateForm']);
            Route::patch('{editionDivision}/schedule/form', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionScheduleController::class, 'patchForm']);
            Route::post('{editionDivision}/schedule/preview', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionScheduleController::class, 'preview']);
            Route::post('{editionDivision}/schedule/generate', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionScheduleController::class, 'generate']);
            Route::post('{editionDivision}/schedule/matches/reorder', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionScheduleController::class, 'reorderMatches']);
            Route::delete('{editionDivision}/schedule', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionScheduleController::class, 'destroy']);

            // Mode A posters
            Route::get('{editionDivision}/posters', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionPosterController::class, 'index']);
            Route::post('{editionDivision}/posters/matches', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionPosterController::class, 'matches']);
            Route::get('{editionDivision}/posters/matches/file', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionPosterController::class, 'matchesFile']);
            Route::post('{editionDivision}/posters/ranking', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionPosterController::class, 'ranking']);
            Route::get('{editionDivision}/posters/ranking/file', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionPosterController::class, 'rankingFile']);
            Route::post('{editionDivision}/posters/{poster}/printed', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionPosterController::class, 'uploadPrinted'])
                ->whereNumber('poster');
            Route::get('{editionDivision}/posters/{poster}/printed/file', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionPosterController::class, 'printedFile'])
                ->whereNumber('poster');

            // Standings / replacements
            Route::get('{editionDivision}/standings', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionStandingController::class, 'index']);
            Route::post('{editionDivision}/standings/recalculate', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionStandingController::class, 'recalculate']);
            Route::get('{editionDivision}/replacements', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionReplacementController::class, 'index']);
            Route::post('{editionDivision}/replacements', [\App\Http\Controllers\Api\V1\Manager\EditionDivisionReplacementController::class, 'store']);
        });

        // Player management
        Route::prefix('players')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'index']);
            Route::post('import/preview', [\App\Http\Controllers\Api\V1\Manager\PlayerImportController::class, 'preview']);
            Route::post('import', [\App\Http\Controllers\Api\V1\Manager\PlayerImportController::class, 'store']);
            Route::post('profile-images/import/preview', [\App\Http\Controllers\Api\V1\Manager\PlayerProfileImageImportController::class, 'preview']);
            Route::post('profile-images/import', [\App\Http\Controllers\Api\V1\Manager\PlayerProfileImageImportController::class, 'store']);
            Route::post('{player}/profile-images', [\App\Http\Controllers\Api\V1\Manager\PlayerProfileImageController::class, 'store'])
                ->whereNumber('player');
            Route::post('{player}/profile-images/{playerProfileImage}/pdf-primary', [\App\Http\Controllers\Api\V1\Manager\PlayerProfileImageController::class, 'setPdfPrimary'])
                ->whereNumber('player')
                ->whereNumber('playerProfileImage');
            Route::delete('{player}/profile-images/{playerProfileImage}', [\App\Http\Controllers\Api\V1\Manager\PlayerProfileImageController::class, 'destroy'])
                ->whereNumber('player')
                ->whereNumber('playerProfileImage');
            Route::post('{player}/pdf-avatar', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'generatePdfAvatar'])
                ->whereNumber('player');
            Route::get('{player}', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'show'])
                ->whereNumber('player');
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'store']);
            Route::put('{player}', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'update'])
                ->whereNumber('player');
            Route::patch('{player}', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'patch'])
                ->whereNumber('player');
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'bulkDestroy']);
            Route::delete('{player}', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'destroy'])
                ->whereNumber('player');
        });

        Route::prefix('player-imports')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerImportController::class, 'index']);
            Route::get('{playerImport}', [\App\Http\Controllers\Api\V1\Manager\PlayerImportController::class, 'show'])
                ->whereNumber('playerImport');
        });

        Route::get('player-import-rows', [\App\Http\Controllers\Api\V1\Manager\PlayerImportRowController::class, 'index']);

        Route::prefix('player-profile-image-imports')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerProfileImageImportController::class, 'index']);
            Route::get('{playerProfileImageImport}', [\App\Http\Controllers\Api\V1\Manager\PlayerProfileImageImportController::class, 'show'])
                ->whereNumber('playerProfileImageImport');
        });

        Route::get('player-profile-image-import-rows', [\App\Http\Controllers\Api\V1\Manager\PlayerProfileImageImportRowController::class, 'index']);

        // Player invites
        Route::prefix('player-invites')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\PlayerInviteController::class, 'store']);
            Route::post('{playerInvite}/revoke', [\App\Http\Controllers\Api\V1\Manager\PlayerInviteController::class, 'revoke']);
            Route::delete('{playerInvite}', [\App\Http\Controllers\Api\V1\Manager\PlayerInviteController::class, 'revoke']);
        });

        // Edition registration management
        Route::prefix('edition-registrations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EditionRegistrationController::class, 'index']);
            Route::get('{editionRegistration}', [\App\Http\Controllers\Api\V1\Manager\EditionRegistrationController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EditionRegistrationController::class, 'store']);
            Route::put('{editionRegistration}', [\App\Http\Controllers\Api\V1\Manager\EditionRegistrationController::class, 'update']);
            Route::patch('{editionRegistration}', [\App\Http\Controllers\Api\V1\Manager\EditionRegistrationController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EditionRegistrationController::class, 'bulkDestroy']);
            Route::delete('{editionRegistration}', [\App\Http\Controllers\Api\V1\Manager\EditionRegistrationController::class, 'destroy']);
        });

        // Edition participation management
        Route::prefix('edition-participations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EditionParticipationController::class, 'index']);
            Route::patch('reorder', [\App\Http\Controllers\Api\V1\Manager\EditionParticipationController::class, 'reorder']);
            Route::get('{editionParticipation}', [\App\Http\Controllers\Api\V1\Manager\EditionParticipationController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EditionParticipationController::class, 'store']);
            Route::put('{editionParticipation}', [\App\Http\Controllers\Api\V1\Manager\EditionParticipationController::class, 'update']);
            Route::patch('{editionParticipation}', [\App\Http\Controllers\Api\V1\Manager\EditionParticipationController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EditionParticipationController::class, 'bulkDestroy']);
            Route::delete('{editionParticipation}', [\App\Http\Controllers\Api\V1\Manager\EditionParticipationController::class, 'destroy']);
        });

        // Eligibility management
        Route::prefix('eligibilities')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'index']);
            Route::post('{playerEligibility}/student-proofs', [\App\Http\Controllers\Api\V1\Manager\EligibilityUploadController::class, 'storeStudentProof'])
                ->whereNumber('playerEligibility');
            Route::delete('{playerEligibility}/student-proofs/{eligibilityStudentProof}', [\App\Http\Controllers\Api\V1\Manager\EligibilityUploadController::class, 'destroyStudentProof'])
                ->whereNumber('playerEligibility')
                ->whereNumber('eligibilityStudentProof');
            Route::post('{playerEligibility}/portrait-photos', [\App\Http\Controllers\Api\V1\Manager\EligibilityUploadController::class, 'storePortraitPhoto'])
                ->whereNumber('playerEligibility');
            Route::delete('{playerEligibility}/portrait-photos/{eligibilityPortraitPhoto}', [\App\Http\Controllers\Api\V1\Manager\EligibilityUploadController::class, 'destroyPortraitPhoto'])
                ->whereNumber('playerEligibility')
                ->whereNumber('eligibilityPortraitPhoto');
            Route::get('{playerEligibility}', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'store']);
            Route::put('{playerEligibility}', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'update']);
            Route::patch('{playerEligibility}', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'bulkDestroy']);
            Route::delete('{playerEligibility}', [\App\Http\Controllers\Api\V1\Manager\EligibilityController::class, 'destroy']);
        });

        // Resource metadata management
        Route::prefix('metadata')->group(function () {
            Route::get('{schema}', [\App\Http\Controllers\Api\V1\Manager\ResourceMetadataController::class, 'show']);
            Route::delete('{schema}/cache', [\App\Http\Controllers\Api\V1\Manager\ResourceMetadataController::class, 'clearCache']);
            Route::delete('cache', [\App\Http\Controllers\Api\V1\Manager\ResourceMetadataController::class, 'clearAllCache']);
        });
    });
});
