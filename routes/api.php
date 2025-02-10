<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\UserScoreController;
use App\Http\Controllers\AdminScoreController;
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
    Route::get('/modules/{module}/materials', [MaterialController::class, 'getMaterialsByModule']);


    Route::get('/materials', [MaterialController::class, 'index']);
    Route::get('/materials/{material}', [MaterialController::class, 'show']);

    Route::get('/challenges', [ChallengeController::class, 'index']);
    // Route untuk mendapatkan detail challenge
    Route::get('/challenges/{challenge}', [ChallengeController::class, 'show']);
    // Route untuk mendapatkan daftar challenge berdasarkan material
    Route::get('/materials/{material}/challenges', [ChallengeController::class, 'getChallengesByMaterial']);

    // Endpoint untuk melakukan attempt pada challenge
    Route::post('/challenges/{challenge}/attempts/{attemptId}/check-answer', [UserAnswerController::class, 'checkAnswer']);
    Route::get('/challenges/{challenge}/attempts/{attemptId}/statistics', [UserAnswerController::class, 'attemptStatistics']);

    // Untuk pertanyaan, jika sebelumnya menggunakan material, sekarang gunakan challenge
    Route::get('/challenges/{challenge}/questions', [QuestionController::class, 'getQuestionsByChallenge']);

    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questions/{question}', [QuestionController::class, 'show']);
    Route::get('/questions/{question}/answers', [AnswerController::class, 'getAnswersByQuestion']);
    
    Route::get('/answers', [AnswerController::class, 'index']);
    Route::get('/answers/{answer}', [AnswerController::class, 'show']);

    // Progress tracking
    Route::post('/materials/{material}/progress', [MaterialController::class, 'storeProgress']);
    Route::get('/materials/{material}/progress', [MaterialController::class, 'getProgress']);

    Route::post('/user-answers', [UserScoreController::class, 'store']);
    // Rute untuk mengambil detail user (data lengkap untuk tampilan /user)
    Route::get('/user-detail', [UserScoreController::class, 'getUserDetail']);

    Route::get('/user-statistics', [UserScoreController::class, 'getStatistics']);
    Route::get('/user-scores', [UserScoreController::class, 'getScores']);
    Route::get('/user-daily-points', [UserScoreController::class, 'getDailyPoints']);

    // Update other methods to not accept $userId

    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::delete('/profile', [AuthController::class, 'deleteAccount']);
    Route::post('/profile/disconnect-social', [AuthController::class, 'disconnectSocial']);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/auth/validate-token', [AuthController::class, 'validateToken']);
    Route::get('/files/{path}', [FileController::class, 'getFile'])->where('path', '.*');
});

// Only admins can CUD (Create, Update, Delete) dan mengakses data admin
Route::middleware('admin')->group(function () {
    // Endpoint khusus untuk soal: get/set jawaban benar
    Route::get('/questions/{questionId}/correct-answer', [QuestionController::class, 'getCorrectAnswer']);
    Route::put('/questions/{questionId}/correct-answer', [QuestionController::class, 'setCorrectAnswer']);
    
    // Resource routes (CUD) untuk user, challenges, modules, materials, questions, answers
    Route::apiResource('users', AuthController::class);
    Route::apiResource('challenges', ChallengeController::class)->except(['index', 'show']);
    Route::apiResource('modules', ModuleController::class)->except(['index', 'show']);
    Route::apiResource('materials', MaterialController::class)->except(['index', 'show']);
    Route::apiResource('questions', QuestionController::class)->except(['index', 'show']);
    Route::apiResource('answers', AnswerController::class)->except(['index', 'show']);
    
    // Route untuk count modules dan materials
    Route::get('/modules-count', [ModuleController::class, 'count']);
    Route::get('/materials-count', [MaterialController::class, 'count']);

    // Route untuk mendapatkan data lengkap user (termasuk statistik dan detail modul)
    Route::get('/users-all', [AdminScoreController::class, 'getAllUserDetails']);


    // Rute untuk mendapatkan ringkasan statistik semua user
    Route::get('/admin/users-stats', [AdminScoreController::class, 'index']);

    // Rute untuk mendapatkan statistik detail satu user (menggunakan query parameter user_id, misalnya: /admin/user-stats?user_id=1)
    Route::get('/admin/user-stats', [AdminScoreController::class, 'detailedStats']);
});
