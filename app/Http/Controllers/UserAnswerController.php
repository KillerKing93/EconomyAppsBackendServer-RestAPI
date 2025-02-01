<?php

namespace App\Http\Controllers;

use App\Models\UserAnswer;
use App\Models\User;
use App\Models\Question;
use App\Models\Material;
use App\Models\Module;
use Illuminate\Http\Request;

class UserAnswerController extends Controller
{
    // Store user answers
    public function store(Request $request)
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer_id' => 'required|exists:answers,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);
    
        // Use authenticated user
        $userAnswer = UserAnswer::create([
            'user_id' => auth()->id(),
            'question_id' => $request->question_id,
            'answer_id' => $request->answer_id,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);
    
        return response()->json($userAnswer, 201);
    }
    
    public function getUserStatistics()
    {
        $user = auth()->user();
    
        $totalPoints = UserAnswer::where('user_id', $user->id)
            ->with('question')
            ->get()
            ->sum(fn($answer) => $answer->question->points);
    
        $totalTime = UserAnswer::where('user_id', $user->id)
            ->selectRaw('SUM(TIMESTAMPDIFF(SECOND, start_time, end_time)) as total_time')
            ->value('total_time') ?? 0;
    
        // Optimized query
        $totalMaterialPoints = Material::withSum('questions as points_sum', 'points')
            ->get()
            ->sum('points_sum');
    
        return response()->json([
            'total_points' => $totalPoints,
            'total_time' => $totalTime,
            'total_material_points' => $totalMaterialPoints,
        ]);
    }

    // Get the time spent per material
    public function getMaterialTime($userId, $materialId)
    {
        $userAnswers = UserAnswer::where('user_id', $userId)
            ->whereHas('question.material', function ($query) use ($materialId) {
                $query->where('material_id', $materialId);
            })
            ->selectRaw('SUM(TIMESTAMPDIFF(SECOND, start_time, end_time)) as total_time')
            ->first();

        return response()->json([
            'total_time_for_material' => $userAnswers ? $userAnswers->total_time : 0
        ]);
    }

    // Get the time spent per question
    public function getQuestionTime($userId, $questionId)
    {
        $userAnswer = UserAnswer::where('user_id', $userId)
            ->where('question_id', $questionId)
            ->selectRaw('TIMESTAMPDIFF(SECOND, start_time, end_time) as time_spent')
            ->first();

        return response()->json([
            'time_spent_for_question' => $userAnswer ? $userAnswer->time_spent : 0
        ]);
    }
}
