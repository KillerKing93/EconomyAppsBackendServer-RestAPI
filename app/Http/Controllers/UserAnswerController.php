<?php
// app/Http/Controllers/UserAnswerController.php
namespace App\Http\Controllers;

use App\Models\UserAnswer;
use App\Models\User;
use App\Models\Question;
use App\Models\Challenge; // gunakan model Challenge
use App\Models\Module;
use Illuminate\Http\Request;

class UserAnswerController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer_id'   => 'required|exists:answers,id',
            'start_time'  => 'required|date',
            'end_time'    => 'required|date|after:start_time',
        ]);
    
        $userAnswer = UserAnswer::create([
            'user_id'     => auth()->id(),
            'question_id' => $request->question_id,
            'answer_id'   => $request->answer_id,
            'start_time'  => $request->start_time,
            'end_time'    => $request->end_time,
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
    
        // Mengambil total poin dari semua challenge (asumsi challenge memiliki relasi questions)
        $totalChallengePoints = Challenge::withSum('questions as points_sum', 'points')
            ->get()
            ->sum('points_sum');
    
        return response()->json([
            'total_points'           => $totalPoints,
            'total_time'             => $totalTime,
            'total_challenge_points' => $totalChallengePoints,
        ]);
    }

    // Ganti getMaterialTime menjadi getChallengeTime
    public function getChallengeTime($userId, $challengeId)
    {
        $userAnswers = UserAnswer::where('user_id', $userId)
            ->whereHas('question.challenge', function ($query) use ($challengeId) {
                $query->where('id', $challengeId);
            })
            ->selectRaw('SUM(TIMESTAMPDIFF(SECOND, start_time, end_time)) as total_time')
            ->first();

        return response()->json([
            'total_time_for_challenge' => $userAnswers ? $userAnswers->total_time : 0
        ]);
    }

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

    // Pada checkAnswer, parameter Material diganti dengan Challenge
    public function checkAnswer(Request $request, Challenge $challenge, $attemptId)
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer_id'   => 'required|exists:answers,id',
            'start_time'  => 'required|date',
            'end_time'    => 'required|date|after:start_time',
        ]);

        $user = auth()->user();

        // Pastikan pertanyaan yang dikirim milik challenge yang sedang dikerjakan
        $question = Question::where('id', $request->question_id)
            ->where('challenge_id', $challenge->id)
            ->first();
        if (!$question) {
            return response()->json(['message' => 'Pertanyaan tidak ditemukan dalam challenge ini.'], 404);
        }

        // Cek apakah untuk attempt ini pertanyaan sudah pernah dijawab
        $existing = UserAnswer::where('user_id', $user->id)
            ->where('attempt_id', $attemptId)
            ->where('question_id', $request->question_id)
            ->first();
        if ($existing) {
            return response()->json(['message' => 'Anda sudah menjawab pertanyaan ini untuk attempt ini.'], 400);
        }

        // Cek kebenaran jawaban
        $isCorrect = false;
        if ($question->correctAnswer && $question->correctAnswer->id == $request->answer_id) {
            $isCorrect = true;
        }

        $userAnswer = UserAnswer::create([
            'user_id'     => $user->id,
            'attempt_id'  => $attemptId,
            'question_id' => $request->question_id,
            'answer_id'   => $request->answer_id,
            'start_time'  => $request->start_time,
            'end_time'    => $request->end_time,
        ]);

        return response()->json([
            'correct'    => $isCorrect,
            'userAnswer' => $userAnswer,
        ]);
    }

    public function attemptStatistics(Challenge $challenge, $attemptId)
    {
        $user = auth()->user();

        $answers = UserAnswer::where('user_id', $user->id)
            ->where('attempt_id', $attemptId)
            ->whereHas('question', function($query) use ($challenge) {
                $query->where('challenge_id', $challenge->id);
            })
            ->get();

        $correct = 0;
        $incorrect = 0;
        foreach ($answers as $answer) {
            if ($answer->question->correctAnswer && $answer->question->correctAnswer->id == $answer->answer_id) {
                $correct++;
            } else {
                $incorrect++;
            }
        }

        $totalTime = $answers->sum(function($ans) {
            return \Carbon\Carbon::parse($ans->end_time)
                ->diffInSeconds(\Carbon\Carbon::parse($ans->start_time));
        });

        return response()->json([
            'total_answers'     => $answers->count(),
            'correct_answers'   => $correct,
            'incorrect_answers' => $incorrect,
            'total_time'        => $totalTime,
        ]);
    }
}
