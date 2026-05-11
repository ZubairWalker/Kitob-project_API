<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

// OTP Authentication
Route::post('/auth/send-otp',        [AuthController::class, 'sendOtp']);
Route::post('/auth/verify-otp',      [AuthController::class, 'verifyOtp']);
Route::post('/auth/complete-profile', [AuthController::class, 'completeProfile']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
