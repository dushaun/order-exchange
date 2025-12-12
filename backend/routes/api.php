<?php

use App\Events\TestBroadcastEvent;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/user', [AuthenticatedSessionController::class, 'show']);

    Route::get('/test-broadcast', function () {
        event(new TestBroadcastEvent());
        return response()->json(['status' => 'Event dispatched']);
    });
});
