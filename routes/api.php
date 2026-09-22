<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\ParlayController;
use App\Http\Controllers\Api\PickController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Parlay Tracker
|--------------------------------------------------------------------------
|
| Todas las rutas protegidas requieren el header:
|   Authorization: Bearer {sanctum_token}
|
| Rutas para Angular (SPA separada en otro puerto/dominio):
|   POST   /api/auth/register      → AuthController@register
|   POST   /api/auth/login         → AuthController@login
|   POST   /api/auth/logout        → AuthController@logout  (auth)
|   GET    /api/auth/me            → AuthController@me      (auth)
|
|   GET    /api/events             → EventController@index  (auth)
|   GET    /api/events/{id}        → EventController@show   (auth)
|
|   GET    /api/parlays            → ParlayController@index   (auth)
|   POST   /api/parlays            → ParlayController@store   (auth)
|   GET    /api/parlays/{id}       → ParlayController@show    (auth)
|   PUT    /api/parlays/{id}       → ParlayController@update  (auth)
|   DELETE /api/parlays/{id}       → ParlayController@destroy (auth)
|
|   GET    /api/parlays/{id}/picks → PickController@index   (auth)
|   POST   /api/parlays/{id}/picks → PickController@store   (auth)
|   GET    /api/picks/{id}         → PickController@show    (auth)
|   DELETE /api/picks/{id}         → PickController@destroy (auth)
|
*/

// ── Autenticación (pública) ───────────────────────────────────────────────────
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
    });
});

// ── Rutas protegidas ──────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Eventos deportivos
    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');

    // Parlays del usuario
    Route::apiResource('parlays', ParlayController::class);

    // Picks anidados bajo parlay
    Route::get('parlays/{parlay}/picks', [PickController::class, 'index'])->name('parlays.picks.index');
    Route::post('parlays/{parlay}/picks', [PickController::class, 'store'])->name('parlays.picks.store');

    // Picks individuales
    Route::get('picks/{pick}', [PickController::class, 'show'])->name('picks.show');
    Route::delete('picks/{pick}', [PickController::class, 'destroy'])->name('picks.destroy');
});

