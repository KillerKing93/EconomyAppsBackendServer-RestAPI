<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    public function index()
    {
        $leaderboard = User::select([
                'users.id',
                'users.name',
                'users.avatar',
                DB::raw('COALESCE(SUM(questions.points), 0) as total_points'),
                DB::raw('COALESCE(SUM(TIMESTAMPDIFF(SECOND, user_answers.start_time, user_answers.end_time)), 0) as total_time')
            ])
            ->leftJoin('user_answers', 'users.id', '=', 'user_answers.user_id')
            ->leftJoin('questions', 'user_answers.question_id', '=', 'questions.id')
            ->groupBy('users.id')
            ->orderByDesc('total_points')
            ->orderBy('total_time')
            ->get();

        return response()->json($leaderboard);
    }
}