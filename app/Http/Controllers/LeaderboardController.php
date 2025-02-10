<?php
// app/Http/Controllers/LeaderboardController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaderboardController extends Controller
{
    /**
     * Ubah variabel ini ke false untuk mematikan logging detil.
     *
     * @var bool
     */
    protected $enableDetailedLogging = true;

    /**
     * Mengembalikan 20 data pengguna untuk leaderboard.
     *
     * Perhitungan:
     * - Total challenge points: Jumlah points dari jawaban yang benar (jika user_answers.answer_id = questions.answer_id)
     * - Total challenge time: Total selisih (end_time - start_time) untuk seluruh jawaban challenge (dalam detik)
     * - Total material points: Jumlah dari (progress/100 * materials.points) pada setiap record progress pengguna.
     *
     * Urutan ranking:
     *   1. Total challenge points DESC (semakin tinggi, semakin baik)
     *   2. Total challenge time ASC (semakin rendah, semakin baik)
     *   3. Total material points DESC (semakin tinggi, semakin baik)
     *
     * Data yang dikembalikan: nickname, logo_path, total_challenge_points, total_material_points, total_challenge_time.
     */
    public function index(Request $request)
    {
        // Log data request masuk (jika logging aktif)
        if ($this->enableDetailedLogging) {
            Log::info('LeaderboardController@index - Incoming request', [
                'request_data' => $request->all(),
                'timestamp'    => now()->toDateTimeString(),
            ]);
        }

        /**
         * Hitung statistik untuk challenge:
         * - total_challenge_points: Sum points dari question yang dijawab dengan benar.
         * - total_challenge_time: Sum waktu pengerjaan (dalam detik) dari setiap jawaban.
         */
        $challengeStats = DB::table('user_answers')
            ->join('questions', 'user_answers.question_id', '=', 'questions.id')
            ->select(
                'user_answers.user_id',
                DB::raw('SUM(IF(user_answers.answer_id = questions.answer_id, questions.points, 0)) as total_challenge_points'),
                DB::raw('SUM(TIMESTAMPDIFF(SECOND, user_answers.start_time, user_answers.end_time)) as total_challenge_time')
            )
            ->groupBy('user_answers.user_id');

        /**
         * Hitung statistik untuk material:
         * - total_material_points: Sum dari (progress/100 * materials.points) untuk setiap record progress pengguna.
         */
        $materialStats = DB::table('material_progress')
            ->join('materials', 'material_progress.material_id', '=', 'materials.id')
            ->select(
                'material_progress.user_id',
                DB::raw('SUM((material_progress.progress / 100) * materials.points) as total_material_points')
            )
            ->groupBy('material_progress.user_id');

        // Gabungkan statistik di atas dengan data user
        $leaderboard = DB::table('users')
            ->leftJoinSub($challengeStats, 'cs', 'users.id', '=', 'cs.user_id')
            ->leftJoinSub($materialStats, 'ms', 'users.id', '=', 'ms.user_id')
            ->select(
                'users.nickname',
                'users.logo_path',
                DB::raw('IFNULL(cs.total_challenge_points, 0) as total_challenge_points'),
                DB::raw('IFNULL(ms.total_material_points, 0) as total_material_points'),
                DB::raw('IFNULL(cs.total_challenge_time, 0) as total_challenge_time')
            )
            // Urutkan berdasarkan kriteria:
            ->orderBy('total_challenge_points', 'desc')
            ->orderBy('total_challenge_time', 'asc')
            ->orderBy('total_material_points', 'desc')
            ->limit(20)
            ->get();

        // Log hasil perhitungan leaderboard
        if ($this->enableDetailedLogging) {
            Log::info('LeaderboardController@index - Leaderboard data computed', [
                'leaderboard' => $leaderboard,
                'timestamp'   => now()->toDateTimeString(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $leaderboard,
        ], 200);
    }
}
