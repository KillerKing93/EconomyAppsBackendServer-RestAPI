<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\UserAnswerController;
use App\Http\Controllers\LeaderboardController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Social login
Route::get('/auth/{provider}', [AuthController::class, 'redirectToProvider']);
Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback']);

// Forgot Password
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Leaderboard
Route::get('/leaderboard', [LeaderboardController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    // Anyone can read modules, materials, and questions (R)
    Route::get('/modules', [ModuleController::class, 'index']);
    Route::get('/modules/{module}', [ModuleController::class, 'show']);
    
    Route::get('/materials', [MaterialController::class, 'index']);
    Route::get('/materials/{material}', [MaterialController::class, 'show']);
    
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questions/{question}', [QuestionController::class, 'show']);
    
    Route::get('/answers', [AnswerController::class, 'index']);
    Route::get('/answers/{answer}', [AnswerController::class, 'show']);

    Route::post('/user-answers', [UserAnswerController::class, 'store']);
    Route::get('/user-statistics', [UserAnswerController::class, 'getUserStatistics']);
    // Update other methods to not accept $userId

    // Progress tracking
    Route::post('/materials/{material}/progress', [MaterialController::class, 'storeProgress']);
    Route::get('/materials/{material}/progress', [MaterialController::class, 'getProgress']);

    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::delete('/profile', [AuthController::class, 'deleteAccount']);
    Route::post('/profile/disconnect-social', [AuthController::class, 'disconnectSocial']);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
});

// Only admins can CUD (Create, Update, Delete)
Route::middleware('admin')->group(function () {
    Route::apiResource('modules', ModuleController::class)->except(['index', 'show']);
    Route::apiResource('materials', MaterialController::class)->except(['index', 'show']);
    Route::apiResource('questions', QuestionController::class)->except(['index', 'show']);
    Route::apiResource('answers', AnswerController::class)->except(['index', 'show']);
});