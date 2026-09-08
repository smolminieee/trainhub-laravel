<?php

use App\Http\Controllers\UnifiedLoginHandoffController;
use Illuminate\Support\Facades\Route;

Route::get('/unified-login/handoff', [UnifiedLoginHandoffController::class, 'consume'])
    ->middleware('throttle:30,1')
    ->name('unified-login.handoff');
