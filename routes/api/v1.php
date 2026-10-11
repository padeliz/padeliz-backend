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
        Route::get('events', [\App\Http\Controllers\Api\V1\App\EventController::class, 'index'])
            ->middleware('cacheResponse');
        Route::get('events/filter-options', [\App\Http\Controllers\Api\V1\App\EventFilterOptionsController::class, 'index'])
            ->middleware('cacheResponse');
        Route::get('events/{event}', [\App\Http\Controllers\Api\V1\App\EventController::class, 'show'])
            ->whereNumber('event')
            ->middleware('cacheResponse');
    });

    Route::post('webhooks/netopia/ipn', [\App\Http\Controllers\Api\V1\Webhooks\NetopiaIpnController::class, 'handle']);
    Route::get('payments/netopia/return', [\App\Http\Controllers\Api\V1\Webhooks\NetopiaReturnController::class, 'handle']);

    // ========================================
    // PLAYER ROUTES (Requires player authentication)
    // ========================================
    Route::middleware(['auth:sanctum', 'user.type:player'])->prefix('app')->group(function () {
        // Player authentication
        Route::prefix('auth')->group(function () {
            Route::post('logout', [\App\Http\Controllers\Api\V1\App\PlayerAuthController::class, 'logout']);
            Route::get('me', [\App\Http\Controllers\Api\V1\App\PlayerAuthController::class, 'me']);
            Route::get('identities', [\App\Http\Controllers\Api\V1\App\PlayerIdentityController::class, 'index']);
            Route::post('identities/{provider}', [\App\Http\Controllers\Api\V1\App\PlayerIdentityController::class, 'store']);
            Route::delete('identities/{playerIdentity}', [\App\Http\Controllers\Api\V1\App\PlayerIdentityController::class, 'destroy'])
                ->whereNumber('playerIdentity');
            Route::post('identities/{playerIdentity}/avatar-primary', [\App\Http\Controllers\Api\V1\App\PlayerIdentityController::class, 'setAvatarPrimary'])
                ->whereNumber('playerIdentity');
        });

        Route::prefix('my')->group(function () {
            Route::get('event-history', [\App\Http\Controllers\Api\V1\App\EventHistoryController::class, 'index']);
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
        Route::get('my/event-registrations', [\App\Http\Controllers\Api\V1\App\EventRegistrationController::class, 'index']);
        Route::prefix('event-registrations')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\V1\App\EventRegistrationController::class, 'store']);
            Route::post('{eventRegistration}/payments/netopia/start', [\App\Http\Controllers\Api\V1\App\RegistrationPaymentController::class, 'startNetopia'])
                ->whereNumber('eventRegistration');
            Route::post('{eventRegistration}/payments/bank-proofs', [\App\Http\Controllers\Api\V1\App\RegistrationPaymentController::class, 'uploadBankProof'])
                ->whereNumber('eventRegistration');
            Route::delete('{eventRegistration}', [\App\Http\Controllers\Api\V1\App\EventRegistrationController::class, 'destroy'])
                ->whereNumber('eventRegistration');
        });
        Route::prefix('registration-checkouts')->group(function () {
            Route::get('{checkout}', [\App\Http\Controllers\Api\V1\App\RegistrationPaymentController::class, 'show'])
                ->whereNumber('checkout');
            Route::get('{checkout}/quote', [\App\Http\Controllers\Api\V1\App\CheckoutPricingController::class, 'quote'])
                ->whereNumber('checkout');
            Route::patch('{checkout}/benefit-selections', [\App\Http\Controllers\Api\V1\App\CheckoutPricingController::class, 'commitSelections'])
                ->whereNumber('checkout');
            Route::post('{checkout}/payments/netopia/start', [\App\Http\Controllers\Api\V1\App\RegistrationPaymentController::class, 'startNetopiaForCheckout'])
                ->whereNumber('checkout');
            Route::post('{checkout}/payments/bank-proofs', [\App\Http\Controllers\Api\V1\App\RegistrationPaymentController::class, 'uploadBankProofForCheckout'])
                ->whereNumber('checkout');
            Route::post('{checkout}/payments/reset', [\App\Http\Controllers\Api\V1\App\RegistrationPaymentController::class, 'resetCheckout'])
                ->whereNumber('checkout');
            Route::post('{checkout}/exclude', [\App\Http\Controllers\Api\V1\App\RegistrationPaymentController::class, 'excludeFromCheckout'])
                ->whereNumber('checkout');
        });
        Route::get('events/{event}/registration-checkouts', [\App\Http\Controllers\Api\V1\App\RegistrationPaymentController::class, 'indexForEvent'])
            ->whereNumber('event');
        Route::post('referral-touchpoints', [\App\Http\Controllers\Api\V1\App\ReferralTouchpointController::class, 'store']);
        Route::get('events/{event}/referral-codes', [\App\Http\Controllers\Api\V1\App\ReferralCodeController::class, 'index'])
            ->whereNumber('event');
        Route::post('events/{event}/referral-codes', [\App\Http\Controllers\Api\V1\App\ReferralCodeController::class, 'store'])
            ->whereNumber('event');
        Route::post('referral-codes/{referralCode}/revoke', [\App\Http\Controllers\Api\V1\App\ReferralCodeController::class, 'revoke'])
            ->whereNumber('referralCode');
        Route::get('bank-transfer-settings', [\App\Http\Controllers\Api\V1\App\BankTransferSettingsController::class, 'show']);
        Route::post('device-tokens', [\App\Http\Controllers\Api\V1\App\DeviceTokenController::class, 'store']);
        Route::delete('device-tokens', [\App\Http\Controllers\Api\V1\App\DeviceTokenController::class, 'destroy']);

        Route::prefix('my/level-assessment')->group(function () {
            Route::get('status', [\App\Http\Controllers\Api\V1\App\MyLevelAssessmentController::class, 'status']);
            Route::get('latest', [\App\Http\Controllers\Api\V1\App\MyLevelAssessmentController::class, 'latest']);
            Route::get('current-level', [\App\Http\Controllers\Api\V1\App\MyLevelAssessmentController::class, 'currentLevel']);
            Route::get('pending', [\App\Http\Controllers\Api\V1\App\MyLevelAssessmentController::class, 'showPending']);
            Route::post('pending', [\App\Http\Controllers\Api\V1\App\MyLevelAssessmentController::class, 'storePending']);
            Route::patch('pending', [\App\Http\Controllers\Api\V1\App\MyLevelAssessmentController::class, 'patchPending']);
            Route::post('pending/ready', [\App\Http\Controllers\Api\V1\App\MyLevelAssessmentController::class, 'markReady']);
            Route::post('pending/reopen', [\App\Http\Controllers\Api\V1\App\MyLevelAssessmentController::class, 'reopen']);
            Route::post('pending/profile-image', [\App\Http\Controllers\Api\V1\App\MyLevelAssessmentController::class, 'storeProfileImage']);
            Route::delete('pending/profile-image/{playerProfileImage}', [\App\Http\Controllers\Api\V1\App\MyLevelAssessmentController::class, 'destroyProfileImage'])
                ->whereNumber('playerProfileImage');
            Route::post('discard', [\App\Http\Controllers\Api\V1\App\MyLevelAssessmentController::class, 'discard']);
        });

        // Discovery (media sources + friends). Friends are never required by the API.
        Route::prefix('my/discovery')->group(function () {
            $controller = \App\Http\Controllers\Api\V1\App\MyDiscoveryController::class;
            Route::get('/', [$controller, 'summary']);
            Route::get('media-sources', [$controller, 'catalog']);
            Route::get('media', [$controller, 'showMedia']);
            Route::put('media', [$controller, 'replaceMedia']);
            Route::patch('media', [$controller, 'replaceMedia']);
            Route::get('friends', [$controller, 'showFriends']);
            Route::put('friends', [$controller, 'replaceFriends']);
            Route::patch('friends', [$controller, 'replaceFriends']);
            Route::get('suggest-friends', [$controller, 'suggestFriends']);
            Route::get('search-players', [$controller, 'searchPlayers']);
        });

        // Student proofs (one pending per player, 1-3 files)
        Route::prefix('my/student-proofs')->group(function () {
            $controller = \App\Http\Controllers\Api\V1\App\MyStudentProofController::class;
            Route::get('/', [$controller, 'index']);
            Route::post('/', [$controller, 'store']);
            Route::get('pending', [$controller, 'showPending']);
            Route::patch('pending', [$controller, 'updatePending']);
            Route::post('pending/files', [$controller, 'addFile']);
            Route::delete('pending/files/{file}', [$controller, 'removeFile'])->whereNumber('file');
            Route::post('pending/ready', [$controller, 'markReady']);
            Route::post('pending/reopen', [$controller, 'reopen']);
            Route::post('discard', [$controller, 'discard']);
        });

        // Level bands (read-only)
        Route::prefix('levels')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\App\LevelController::class, 'index']);
            Route::get('{level}', [\App\Http\Controllers\Api\V1\App\LevelController::class, 'show'])
                ->whereNumber('level');
        });
    });

    // ========================================
    // MANAGER ROUTES (Requires manager authentication)
    // ========================================
    Route::middleware(['auth:sanctum', 'user.type:manager', 'cache.no-store', 'cache.etag'])->prefix('manager')->group(function () {
        // Manager authentication
        Route::prefix('auth')->group(function () {
            Route::post('logout', [\App\Http\Controllers\Api\V1\Manager\ManagerAuthController::class, 'logout']);
            Route::get('me', [\App\Http\Controllers\Api\V1\Manager\ManagerAuthController::class, 'me']);
        });

        // Organizer management
        Route::prefix('organizers')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\OrganizerController::class, 'index']);
            Route::post('{organizer}/logo', [\App\Http\Controllers\Api\V1\Manager\OrganizerLogoController::class, 'store'])
                ->whereNumber('organizer');
            Route::delete('{organizer}/logo', [\App\Http\Controllers\Api\V1\Manager\OrganizerLogoController::class, 'destroy'])
                ->whereNumber('organizer');
            Route::get('{organizer}', [\App\Http\Controllers\Api\V1\Manager\OrganizerController::class, 'show'])
                ->whereNumber('organizer');
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\OrganizerController::class, 'store']);
            Route::put('{organizer}', [\App\Http\Controllers\Api\V1\Manager\OrganizerController::class, 'update'])
                ->whereNumber('organizer');
            Route::patch('{organizer}', [\App\Http\Controllers\Api\V1\Manager\OrganizerController::class, 'patch'])
                ->whereNumber('organizer');
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\OrganizerController::class, 'bulkDestroy']);
            Route::delete('{organizer}', [\App\Http\Controllers\Api\V1\Manager\OrganizerController::class, 'destroy'])
                ->whereNumber('organizer');
        });

        // Club management
        Route::prefix('clubs')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\ClubController::class, 'index']);
            Route::post('{club}/logo', [\App\Http\Controllers\Api\V1\Manager\ClubLogoController::class, 'store'])
                ->whereNumber('club');
            Route::delete('{club}/logo', [\App\Http\Controllers\Api\V1\Manager\ClubLogoController::class, 'destroy'])
                ->whereNumber('club');
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

        // PadelEvent management
        Route::prefix('events')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EventController::class, 'index']);
            Route::get('{event}', [\App\Http\Controllers\Api\V1\Manager\EventController::class, 'show'])
                ->whereNumber('event');
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EventController::class, 'store']);
            Route::put('{event}', [\App\Http\Controllers\Api\V1\Manager\EventController::class, 'update'])
                ->whereNumber('event');
            Route::patch('{event}', [\App\Http\Controllers\Api\V1\Manager\EventController::class, 'patch'])
                ->whereNumber('event');
            Route::post('{event}/cover-images', [\App\Http\Controllers\Api\V1\Manager\EventCoverController::class, 'store'])
                ->name('manager.events.cover-images.store');
            Route::delete('{event}/cover-images/{coverImage}', [\App\Http\Controllers\Api\V1\Manager\EventCoverController::class, 'destroy'])
                ->name('manager.events.cover-images.destroy');
            // Legacy single-cover path kept as alias to the multi uploader.
            Route::post('{event}/cover', [\App\Http\Controllers\Api\V1\Manager\EventCoverController::class, 'store'])
                ->whereNumber('event');
            Route::post('{event}/finalize', [\App\Http\Controllers\Api\V1\Manager\EventFinalizeController::class, 'store'])
                ->whereNumber('event');
            Route::get('{event}/replacements', [\App\Http\Controllers\Api\V1\Manager\EventDivisionReplacementController::class, 'indexForEvent'])
                ->whereNumber('event');
            Route::post('{event}/registrations/import/preview', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationImportController::class, 'preview'])
                ->whereNumber('event');
            Route::post('{event}/registrations/import', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationImportController::class, 'store'])
                ->whereNumber('event');
            Route::get('{event}/participations/map/preview', [\App\Http\Controllers\Api\V1\Manager\EventParticipationMappingController::class, 'preview'])
                ->whereNumber('event');
            Route::post('{event}/participations/map', [\App\Http\Controllers\Api\V1\Manager\EventParticipationMappingController::class, 'map'])
                ->whereNumber('event');
            Route::get('{event}/posters', [\App\Http\Controllers\Api\V1\Manager\EventPosterController::class, 'index'])
                ->whereNumber('event');
            Route::get('{event}/posters/merged/file', [\App\Http\Controllers\Api\V1\Manager\EventPosterController::class, 'mergedFile'])
                ->whereNumber('event');
            Route::post('{event}/posters/attendance', [\App\Http\Controllers\Api\V1\Manager\EventPosterController::class, 'attendance'])
                ->whereNumber('event');
            Route::get('{event}/posters/attendance/preview', [\App\Http\Controllers\Api\V1\Manager\EventPosterController::class, 'attendancePreview'])
                ->whereNumber('event');
            Route::get('{event}/posters/attendance/file', [\App\Http\Controllers\Api\V1\Manager\EventPosterController::class, 'attendanceFile'])
                ->whereNumber('event');
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EventController::class, 'bulkDestroy']);
            Route::delete('{event}', [\App\Http\Controllers\Api\V1\Manager\EventController::class, 'destroy'])
                ->whereNumber('event');
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
        Route::prefix('event-lottery-prizes')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EventLotteryPrizeController::class, 'index']);
            Route::post('reorder', [\App\Http\Controllers\Api\V1\Manager\EventLotteryPrizeController::class, 'reorder']);
            Route::get('{lotteryPrize}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryPrizeController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EventLotteryPrizeController::class, 'store']);
            Route::put('{lotteryPrize}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryPrizeController::class, 'update']);
            Route::patch('{lotteryPrize}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryPrizeController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EventLotteryPrizeController::class, 'bulkDestroy']);
            Route::delete('{lotteryPrize}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryPrizeController::class, 'destroy']);
        });

        // Lottery management
        Route::prefix('event-lotteries')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EventLotteryController::class, 'index']);
            Route::get('{lottery}/live', [\App\Http\Controllers\Api\V1\Manager\EventLotteryLiveController::class, 'live']);
            Route::post('{lottery}/draw', [\App\Http\Controllers\Api\V1\Manager\EventLotteryLiveController::class, 'draw']);
            Route::post('{lottery}/announce', [\App\Http\Controllers\Api\V1\Manager\EventLotteryLiveController::class, 'announce']);
            Route::post('{lottery}/accept', [\App\Http\Controllers\Api\V1\Manager\EventLotteryLiveController::class, 'accept']);
            Route::post('{lottery}/reject', [\App\Http\Controllers\Api\V1\Manager\EventLotteryLiveController::class, 'reject']);
            Route::get('{lottery}/prizes-board', [\App\Http\Controllers\Api\V1\Manager\EventLotteryLiveController::class, 'prizesBoard']);
            Route::post('{lottery}/rules/generate', [\App\Http\Controllers\Api\V1\Manager\EventLotteryController::class, 'generateRules']);
            Route::get('{lottery}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EventLotteryController::class, 'store']);
            Route::put('{lottery}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryController::class, 'update']);
            Route::patch('{lottery}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EventLotteryController::class, 'bulkDestroy']);
            Route::delete('{lottery}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryController::class, 'destroy']);
        });

        // Lottery luckies management
        Route::prefix('event-lottery-luckies')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EventLotteryLuckyController::class, 'index']);
            Route::get('{lucky}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryLuckyController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EventLotteryLuckyController::class, 'store']);
            Route::put('{lucky}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryLuckyController::class, 'update']);
            Route::patch('{lucky}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryLuckyController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EventLotteryLuckyController::class, 'bulkDestroy']);
            Route::delete('{lucky}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryLuckyController::class, 'destroy']);
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
        Route::prefix('event-lottery-rules')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EventLotteryRuleController::class, 'index']);
            Route::post('reorder', [\App\Http\Controllers\Api\V1\Manager\EventLotteryRuleController::class, 'reorder']);
            Route::get('{lotteryRule}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryRuleController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EventLotteryRuleController::class, 'store']);
            Route::put('{lotteryRule}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryRuleController::class, 'update']);
            Route::patch('{lotteryRule}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryRuleController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EventLotteryRuleController::class, 'bulkDestroy']);
            Route::delete('{lotteryRule}', [\App\Http\Controllers\Api\V1\Manager\EventLotteryRuleController::class, 'destroy']);
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
        Route::prefix('event-division-standings')->group(function () {
            Route::put('{standing}', [\App\Http\Controllers\Api\V1\Manager\EventDivisionStandingController::class, 'update']);
            Route::patch('{standing}', [\App\Http\Controllers\Api\V1\Manager\EventDivisionStandingController::class, 'patch']);
        });

        // Replacements destroy / patch (by id)
        Route::delete('event-division-replacements/{replacement}', [\App\Http\Controllers\Api\V1\Manager\EventDivisionReplacementController::class, 'destroy']);
        Route::patch('event-division-replacements/{replacement}', [\App\Http\Controllers\Api\V1\Manager\EventDivisionReplacementController::class, 'patch']);

        // PadelEvent divisions management
        Route::prefix('event-divisions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EventDivisionController::class, 'index']);
            Route::patch('reorder', [\App\Http\Controllers\Api\V1\Manager\EventDivisionController::class, 'reorder']);
            Route::get('{eventDivision}', [\App\Http\Controllers\Api\V1\Manager\EventDivisionController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EventDivisionController::class, 'store']);
            Route::put('{eventDivision}', [\App\Http\Controllers\Api\V1\Manager\EventDivisionController::class, 'update']);
            Route::patch('{eventDivision}', [\App\Http\Controllers\Api\V1\Manager\EventDivisionController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EventDivisionController::class, 'bulkDestroy']);
            Route::delete('{eventDivision}', [\App\Http\Controllers\Api\V1\Manager\EventDivisionController::class, 'destroy']);

            // Mode A schedule
            Route::get('{eventDivision}/schedule', [\App\Http\Controllers\Api\V1\Manager\EventDivisionScheduleController::class, 'show']);
            Route::put('{eventDivision}/schedule/form', [\App\Http\Controllers\Api\V1\Manager\EventDivisionScheduleController::class, 'updateForm']);
            Route::patch('{eventDivision}/schedule/form', [\App\Http\Controllers\Api\V1\Manager\EventDivisionScheduleController::class, 'patchForm']);
            Route::post('{eventDivision}/schedule/preview', [\App\Http\Controllers\Api\V1\Manager\EventDivisionScheduleController::class, 'preview']);
            Route::post('{eventDivision}/schedule/generate', [\App\Http\Controllers\Api\V1\Manager\EventDivisionScheduleController::class, 'generate']);
            Route::post('{eventDivision}/schedule/matches/reorder', [\App\Http\Controllers\Api\V1\Manager\EventDivisionScheduleController::class, 'reorderMatches']);
            Route::delete('{eventDivision}/schedule', [\App\Http\Controllers\Api\V1\Manager\EventDivisionScheduleController::class, 'destroy']);

            // Mode A posters
            Route::get('{eventDivision}/posters', [\App\Http\Controllers\Api\V1\Manager\EventDivisionPosterController::class, 'index']);
            Route::post('{eventDivision}/posters/matches', [\App\Http\Controllers\Api\V1\Manager\EventDivisionPosterController::class, 'matches']);
            Route::get('{eventDivision}/posters/matches/file', [\App\Http\Controllers\Api\V1\Manager\EventDivisionPosterController::class, 'matchesFile']);
            Route::post('{eventDivision}/posters/ranking', [\App\Http\Controllers\Api\V1\Manager\EventDivisionPosterController::class, 'ranking']);
            Route::get('{eventDivision}/posters/ranking/file', [\App\Http\Controllers\Api\V1\Manager\EventDivisionPosterController::class, 'rankingFile']);
            Route::post('{eventDivision}/posters/{poster}/printed', [\App\Http\Controllers\Api\V1\Manager\EventDivisionPosterController::class, 'uploadPrinted'])
                ->whereNumber('poster');
            Route::get('{eventDivision}/posters/{poster}/printed/file', [\App\Http\Controllers\Api\V1\Manager\EventDivisionPosterController::class, 'printedFile'])
                ->whereNumber('poster');

            // Standings / replacements
            Route::get('{eventDivision}/standings', [\App\Http\Controllers\Api\V1\Manager\EventDivisionStandingController::class, 'index']);
            Route::post('{eventDivision}/standings/recalculate', [\App\Http\Controllers\Api\V1\Manager\EventDivisionStandingController::class, 'recalculate']);
            Route::get('{eventDivision}/replacements', [\App\Http\Controllers\Api\V1\Manager\EventDivisionReplacementController::class, 'index']);
            Route::post('{eventDivision}/replacements', [\App\Http\Controllers\Api\V1\Manager\EventDivisionReplacementController::class, 'store']);
        });

        // Player custom fields catalog
        Route::prefix('player-custom-fields')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldController::class, 'store']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldController::class, 'bulkDestroy']);
            Route::get('{playerCustomField}', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldController::class, 'show'])
                ->whereNumber('playerCustomField');
            Route::put('{playerCustomField}', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldController::class, 'update'])
                ->whereNumber('playerCustomField');
            Route::patch('{playerCustomField}', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldController::class, 'patch'])
                ->whereNumber('playerCustomField');
            Route::delete('{playerCustomField}', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldController::class, 'destroy'])
                ->whereNumber('playerCustomField');
            Route::post('{playerCustomField}/archive', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldController::class, 'archive'])
                ->whereNumber('playerCustomField');
            Route::post('{playerCustomField}/unarchive', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldController::class, 'unarchive'])
                ->whereNumber('playerCustomField');
            Route::prefix('{playerCustomField}/options')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldOptionController::class, 'index'])
                    ->whereNumber('playerCustomField');
                Route::post('/', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldOptionController::class, 'store'])
                    ->whereNumber('playerCustomField');
                Route::put('{playerCustomFieldOption}', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldOptionController::class, 'update'])
                    ->whereNumber('playerCustomField')
                    ->whereNumber('playerCustomFieldOption');
                Route::patch('{playerCustomFieldOption}', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldOptionController::class, 'patch'])
                    ->whereNumber('playerCustomField')
                    ->whereNumber('playerCustomFieldOption');
                Route::delete('{playerCustomFieldOption}', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldOptionController::class, 'destroy'])
                    ->whereNumber('playerCustomField')
                    ->whereNumber('playerCustomFieldOption');
            });
        });

        Route::prefix('player-custom-field-value-imports')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldValueImportController::class, 'index']);
            Route::get('{playerCustomFieldValueImport}', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldValueImportController::class, 'show'])
                ->whereNumber('playerCustomFieldValueImport');
        });

        Route::get('player-custom-field-value-import-rows', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldValueImportRowController::class, 'index']);

        // Player management
        Route::prefix('players')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerController::class, 'index']);
            Route::post('custom-field-values/import/preview', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldValueImportController::class, 'preview']);
            Route::post('custom-field-values/import', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldValueImportController::class, 'store']);
            Route::post('import/preview', [\App\Http\Controllers\Api\V1\Manager\UserImportController::class, 'preview']);
            Route::post('import', [\App\Http\Controllers\Api\V1\Manager\UserImportController::class, 'store']);
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
            Route::get('{player}/custom-field-values', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldValueController::class, 'index'])
                ->whereNumber('player');
            Route::post('{player}/custom-field-values', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldValueController::class, 'store'])
                ->whereNumber('player');
            Route::get('{player}/custom-field-values/{playerCustomFieldValue}', [\App\Http\Controllers\Api\V1\Manager\PlayerCustomFieldValueController::class, 'show'])
                ->whereNumber('player')
                ->whereNumber('playerCustomFieldValue');
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

        Route::post('cached-pdf-pair-images', [\App\Http\Controllers\Api\V1\Manager\CachedPdfPairImageController::class, 'store']);

        Route::prefix('user-imports')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\UserImportController::class, 'index']);
            Route::get('{userImport}', [\App\Http\Controllers\Api\V1\Manager\UserImportController::class, 'show'])
                ->whereNumber('userImport');
        });

        Route::get('user-import-rows', [\App\Http\Controllers\Api\V1\Manager\UserImportRowController::class, 'index']);

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

        // PadelEvent registration management
        Route::prefix('event-registrations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationController::class, 'index']);
            Route::get('{eventRegistration}', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationController::class, 'store']);
            Route::put('{eventRegistration}', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationController::class, 'update']);
            Route::patch('{eventRegistration}', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationController::class, 'patch']);
            Route::post('{eventRegistration}/manual-adjustment', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationController::class, 'manualAdjustment'])
                ->whereNumber('eventRegistration');
            Route::patch('{eventRegistration}/manual-adjustment', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationController::class, 'manualAdjustment'])
                ->whereNumber('eventRegistration');
            Route::post('{eventRegistration}/cancel', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationController::class, 'cancel'])
                ->whereNumber('eventRegistration');
            Route::post('{eventRegistration}/decline', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationController::class, 'decline'])
                ->whereNumber('eventRegistration');
            Route::post('{eventRegistration}/mark-refunded', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationController::class, 'markRefunded'])
                ->whereNumber('eventRegistration');
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationController::class, 'bulkDestroy']);
            Route::delete('{eventRegistration}', [\App\Http\Controllers\Api\V1\Manager\EventRegistrationController::class, 'destroy']);
        });

        Route::prefix('benefit-definitions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\BenefitDefinitionController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\BenefitDefinitionController::class, 'store']);
            Route::get('{benefitDefinition}', [\App\Http\Controllers\Api\V1\Manager\BenefitDefinitionController::class, 'show'])
                ->whereNumber('benefitDefinition');
            Route::put('{benefitDefinition}', [\App\Http\Controllers\Api\V1\Manager\BenefitDefinitionController::class, 'update'])
                ->whereNumber('benefitDefinition');
            Route::patch('{benefitDefinition}', [\App\Http\Controllers\Api\V1\Manager\BenefitDefinitionController::class, 'patch'])
                ->whereNumber('benefitDefinition');
            Route::post('{benefitDefinition}/activate', [\App\Http\Controllers\Api\V1\Manager\BenefitDefinitionController::class, 'activate'])
                ->whereNumber('benefitDefinition');
            Route::post('{benefitDefinition}/deactivate', [\App\Http\Controllers\Api\V1\Manager\BenefitDefinitionController::class, 'deactivate'])
                ->whereNumber('benefitDefinition');
            Route::delete('{benefitDefinition}', [\App\Http\Controllers\Api\V1\Manager\BenefitDefinitionController::class, 'destroy'])
                ->whereNumber('benefitDefinition');
        });

        Route::prefix('benefit-entitlements')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\BenefitEntitlementController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\BenefitEntitlementController::class, 'store']);
            Route::get('{benefitEntitlement}', [\App\Http\Controllers\Api\V1\Manager\BenefitEntitlementController::class, 'show'])
                ->whereNumber('benefitEntitlement');
            Route::post('{benefitEntitlement}/revoke', [\App\Http\Controllers\Api\V1\Manager\BenefitEntitlementController::class, 'revoke'])
                ->whereNumber('benefitEntitlement');
            Route::patch('{benefitEntitlement}/extend', [\App\Http\Controllers\Api\V1\Manager\BenefitEntitlementController::class, 'extend'])
                ->whereNumber('benefitEntitlement');
        });

        Route::get('events/{event}/referral-settings', [\App\Http\Controllers\Api\V1\Manager\EventReferralSettingController::class, 'show'])
            ->whereNumber('event');
        Route::put('events/{event}/referral-settings', [\App\Http\Controllers\Api\V1\Manager\EventReferralSettingController::class, 'upsert'])
            ->whereNumber('event');
        Route::get('referral-analytics/touchpoints', [\App\Http\Controllers\Api\V1\Manager\ReferralAnalyticsController::class, 'touchpoints']);
        Route::get('referral-analytics/attributions', [\App\Http\Controllers\Api\V1\Manager\ReferralAnalyticsController::class, 'attributions']);
        Route::post('pricing/preview', [\App\Http\Controllers\Api\V1\Manager\PricingPreviewController::class, 'preview']);

        Route::prefix('payment-proofs')->group(function () {
            Route::post('{paymentProof}/approve', [\App\Http\Controllers\Api\V1\Manager\RegistrationPaymentProofController::class, 'approve'])
                ->whereNumber('paymentProof');
            Route::post('{paymentProof}/reject', [\App\Http\Controllers\Api\V1\Manager\RegistrationPaymentProofController::class, 'reject'])
                ->whereNumber('paymentProof');
        });

        Route::get('bank-transfer-settings', [\App\Http\Controllers\Api\V1\Manager\BankTransferSettingsController::class, 'show']);
        Route::put('bank-transfer-settings', [\App\Http\Controllers\Api\V1\Manager\BankTransferSettingsController::class, 'update']);
        Route::get('registration-checkouts', [\App\Http\Controllers\Api\V1\Manager\RegistrationCheckoutController::class, 'index']);
        Route::get('registration-queue-counts', \App\Http\Controllers\Api\V1\Manager\RegistrationQueueCountsController::class);

        // PadelEvent participation management
        Route::prefix('event-participations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\EventParticipationController::class, 'index']);
            Route::patch('reorder', [\App\Http\Controllers\Api\V1\Manager\EventParticipationController::class, 'reorder']);
            Route::get('{eventParticipation}', [\App\Http\Controllers\Api\V1\Manager\EventParticipationController::class, 'show']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\EventParticipationController::class, 'store']);
            Route::put('{eventParticipation}', [\App\Http\Controllers\Api\V1\Manager\EventParticipationController::class, 'update']);
            Route::patch('{eventParticipation}', [\App\Http\Controllers\Api\V1\Manager\EventParticipationController::class, 'patch']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\EventParticipationController::class, 'bulkDestroy']);
            Route::delete('{eventParticipation}', [\App\Http\Controllers\Api\V1\Manager\EventParticipationController::class, 'destroy']);

            // Standings level entry (participation-sourced player level)
            Route::get('{eventParticipation}/level', [\App\Http\Controllers\Api\V1\Manager\EventParticipationLevelController::class, 'show'])
                ->whereNumber('eventParticipation');
            Route::post('{eventParticipation}/level', [\App\Http\Controllers\Api\V1\Manager\EventParticipationLevelController::class, 'upsert'])
                ->whereNumber('eventParticipation');
            Route::put('{eventParticipation}/level', [\App\Http\Controllers\Api\V1\Manager\EventParticipationLevelController::class, 'upsert'])
                ->whereNumber('eventParticipation');
            Route::patch('{eventParticipation}/level', [\App\Http\Controllers\Api\V1\Manager\EventParticipationLevelController::class, 'upsert'])
                ->whereNumber('eventParticipation');
        });

        // Player phones / emails (full CRUD)
        Route::prefix('player-phones')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerPhoneController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\PlayerPhoneController::class, 'store']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\PlayerPhoneController::class, 'bulkDestroy']);
            Route::get('{playerPhone}', [\App\Http\Controllers\Api\V1\Manager\PlayerPhoneController::class, 'show'])
                ->whereNumber('playerPhone');
            Route::put('{playerPhone}', [\App\Http\Controllers\Api\V1\Manager\PlayerPhoneController::class, 'update'])
                ->whereNumber('playerPhone');
            Route::delete('{playerPhone}', [\App\Http\Controllers\Api\V1\Manager\PlayerPhoneController::class, 'destroy'])
                ->whereNumber('playerPhone');
        });

        Route::prefix('player-emails')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerEmailController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\PlayerEmailController::class, 'store']);
            Route::delete('bulk', [\App\Http\Controllers\Api\V1\Manager\PlayerEmailController::class, 'bulkDestroy']);
            Route::get('{playerEmail}', [\App\Http\Controllers\Api\V1\Manager\PlayerEmailController::class, 'show'])
                ->whereNumber('playerEmail');
            Route::put('{playerEmail}', [\App\Http\Controllers\Api\V1\Manager\PlayerEmailController::class, 'update'])
                ->whereNumber('playerEmail');
            Route::delete('{playerEmail}', [\App\Http\Controllers\Api\V1\Manager\PlayerEmailController::class, 'destroy'])
                ->whereNumber('playerEmail');
        });

        // Player identities (OAuth-linked by players in the App: no store route)
        Route::prefix('player-identities')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerIdentityController::class, 'index']);
            Route::get('{playerIdentity}', [\App\Http\Controllers\Api\V1\Manager\PlayerIdentityController::class, 'show'])
                ->whereNumber('playerIdentity');
            Route::put('{playerIdentity}', [\App\Http\Controllers\Api\V1\Manager\PlayerIdentityController::class, 'update'])
                ->whereNumber('playerIdentity');
            Route::delete('{playerIdentity}', [\App\Http\Controllers\Api\V1\Manager\PlayerIdentityController::class, 'destroy'])
                ->whereNumber('playerIdentity');
        });

        // Player student proofs (review-only; players create them)
        Route::prefix('player-student-proofs')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerStudentProofController::class, 'index']);
            Route::get('{playerStudentProof}', [\App\Http\Controllers\Api\V1\Manager\PlayerStudentProofController::class, 'show'])
                ->whereNumber('playerStudentProof');
            Route::patch('{playerStudentProof}/approve', [\App\Http\Controllers\Api\V1\Manager\PlayerStudentProofController::class, 'approve'])
                ->whereNumber('playerStudentProof');
            Route::patch('{playerStudentProof}/reject', [\App\Http\Controllers\Api\V1\Manager\PlayerStudentProofController::class, 'reject'])
                ->whereNumber('playerStudentProof');
        });

        // Player level assessments (review-only; players create them)
        Route::prefix('player-level-assessments')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\PlayerLevelAssessmentController::class, 'index']);
            Route::get('{playerLevelAssessment}', [\App\Http\Controllers\Api\V1\Manager\PlayerLevelAssessmentController::class, 'show'])
                ->whereNumber('playerLevelAssessment');
            Route::patch('{playerLevelAssessment}/approve', [\App\Http\Controllers\Api\V1\Manager\PlayerLevelAssessmentController::class, 'approve'])
                ->whereNumber('playerLevelAssessment');
            Route::patch('{playerLevelAssessment}/reject', [\App\Http\Controllers\Api\V1\Manager\PlayerLevelAssessmentController::class, 'reject'])
                ->whereNumber('playerLevelAssessment');
        });

        // Level bands (read-only)
        Route::prefix('levels')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\LevelController::class, 'index']);
            Route::get('{level}', [\App\Http\Controllers\Api\V1\Manager\LevelController::class, 'show'])
                ->whereNumber('level');
        });

        // Resource metadata management
        Route::prefix('metadata')->group(function () {
            Route::get('{schema}', [\App\Http\Controllers\Api\V1\Manager\ResourceMetadataController::class, 'show']);
        });

        // Challenges (FE-authored persistence)
        Route::prefix('challenges')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Manager\ChallengeController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\Manager\ChallengeController::class, 'store']);
            Route::get('{session}', [\App\Http\Controllers\Api\V1\Manager\ChallengeController::class, 'show'])
                ->whereNumber('session');
            Route::get('{session}/state', [\App\Http\Controllers\Api\V1\Manager\ChallengeController::class, 'state'])
                ->whereNumber('session');
            Route::post('{id}/sync', [\App\Http\Controllers\Api\V1\Manager\ChallengeController::class, 'sync'])
                ->whereNumber('id');
        });
    });
});
