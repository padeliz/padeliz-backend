<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// API Documentation Index
Route::get('/documentation', [\App\Http\Controllers\ApiDocumentationController::class, 'index'])
    ->middleware('cacheResponse');

// External integrations (isolated from core Padeliz domain)
Route::prefix('external/bpt-rankedin')->group(function () {
    Route::get('top-players', \App\Http\Controllers\External\RankedinBpt\BptTopPlayersController::class);
});

// Include versioned route files
require __DIR__ . '/api/v1.php';
