<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Public storefront
Route::get('categories', [CategoryController::class, 'index']);

// Back office: the Next.js proxy forwards /api/admin/* to /admin/* here
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('categories', [CategoryController::class, 'adminIndex']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
});