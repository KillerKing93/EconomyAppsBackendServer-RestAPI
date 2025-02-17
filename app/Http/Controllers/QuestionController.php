<?php
// app/Http/Controllers/QuestionController.php
namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class QuestionController extends Controller
{
    public function index()
    {
        $userId    = auth()->id();
        $ip        = request()->ip();
        $userAgent = request()->header('User-Agent');
        $timestamp = now()->toDateTimeString();

        Log::info('QuestionController@index called', [
            'timestamp'  => $timestamp,
            'user_id'    => $userId,
            'ip'         => $ip,
            'user_agent' => $userAgent,
        ]);
        
        $questions = Question::with('challenge', 'answers')
            ->when(auth()->user()?->isAdmin(), function ($query) {
                $query->with('correctAnswer');
            })
            ->get();
        
        Log::info('Questions retrieved', [
            'timestamp' => now()->toDateTimeString(),
            'user_id'   => $userId,
            'count'     => $questions->count(),
        ]);
    
        return response()->json($questions);
    }

    // Mengambil pertanyaan berdasarkan Challenge (bukan Material)
    public function getQuestionsByChallenge($challengeId)
    {
        $userId    = auth()->id();
        $ip        = request()->ip();
        $userAgent = request()->header('User-Agent');
        $timestamp = now()->toDateTimeString();

        Log::info('getQuestionsByChallenge called', [
            'timestamp'    => $timestamp,
            'challenge_id' => $challengeId,
            'user_id'      => $userId,
            'ip'           => $ip,
            'user_agent'   => $userAgent,
        ]);
        
        $questions = Question::with('answers')
            ->where('challenge_id', $challengeId)
            ->get();

        Log::info('Questions retrieved for challenge', [
            'timestamp'    => now()->toDateTimeString(),
            'challenge_id' => $challengeId,
            'count'        => $questions->count(),
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $questions,
        ]);
    }

    public function getCorrectAnswer($questionId)
    {
        // Tidak ada perubahan pada fungsi ini
        // (tetap mencari jawaban benar berdasarkan question_id)
        $userId    = auth()->id();
        $ip        = request()->ip();
        $userAgent = request()->header('User-Agent');
        $timestamp = now()->toDateTimeString();

        Log::info('getCorrectAnswer called', [
            'timestamp'   => $timestamp,
            'question_id' => $questionId,
            'user_id'     => $userId,
            'ip'          => $ip,
            'user_agent'  => $userAgent,
        ]);

        $question = Question::with('correctAnswer')->find($questionId);

        if (!$question) {
            Log::warning('Question not found in getCorrectAnswer', [
                'timestamp'   => now()->toDateTimeString(),
                'question_id' => $questionId,
            ]);

            return response()->json([
                'error' => 'Question not found'
            ], 404);
        }

        $correctAnswer = $question->correctAnswer;

        if (!$correctAnswer) {
            Log::info('No correct answer set for question', [
                'timestamp'   => now()->toDateTimeString(),
                'question_id' => $questionId,
            ]);

            return response()->json([
                'status'  => 'success',
                'data'    => null,
                'message' => 'Tidak ada jawaban yang diset sebagai benar'
            ], 200);
        }

        Log::info('Correct answer retrieved for question', [
            'timestamp'         => now()->toDateTimeString(),
            'question_id'       => $questionId,
            'correct_answer_id' => $correctAnswer->id,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $correctAnswer
        ], 200);
    }

    public function setCorrectAnswer(Request $request, $questionId)
    {
        // Sama seperti sebelumnya, tidak perlu perubahan
        $userId    = auth()->id();
        $ip        = $request->ip();
        $userAgent = $request->header('User-Agent');
        $timestamp = now()->toDateTimeString();

        Log::info('setCorrectAnswer called', [
            'timestamp'    => $timestamp,
            'question_id'  => $questionId,
            'user_id'      => $userId,
            'ip'           => $ip,
            'user_agent'   => $userAgent,
            'request_data' => $request->all(),
        ]);

        $validated = $request->validate([
            'answer_id' => [
                'required',
                Rule::exists('answers', 'id')->where('question_id', $questionId),
            ],
        ]);

        $question = Question::find($questionId);
        if (!$question) {
            Log::warning('Question not found in setCorrectAnswer', [
                'timestamp'   => now()->toDateTimeString(),
                'question_id' => $questionId,
            ]);
            return response()->json(['error' => 'Question not found'], 404);
        }

        $question->answer_id = $validated['answer_id'];
        $question->save();

        Log::info('Correct answer set for question', [
            'timestamp'   => now()->toDateTimeString(),
            'question_id' => $questionId,
            'answer_id'   => $question->answer_id,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $question,
        ]);
    }

    public function store(Request $request)
    {
        $userId    = auth()->id();
        $ip        = $request->ip();
        $userAgent = $request->header('User-Agent');
        $timestamp = now()->toDateTimeString();

        Log::info('store method called', [
            'timestamp'    => $timestamp,
            'user_id'      => $userId,
            'ip'           => $ip,
            'user_agent'   => $userAgent,
            'request_data' => $request->all(),
        ]);

        // Ubah validasi: gunakan challenge_id
        $validated = $request->validate([
            'challenge_id' => 'required|exists:challenges,id',
            'question'     => 'required|string',
            'logo_path'    => 'nullable|file|mimes:jpeg,jpg,png|max:8192',
            'points'       => 'required|integer|min:1',
            'answer_id'    => [
                'nullable',
                Rule::exists('answers', 'id')->where('question_id', $request->question_id)
            ],
        ]);
        
        Log::info('Validation passed', [
            'timestamp'      => now()->toDateTimeString(),
            'validated_data' => $validated,
        ]);
        
        if ($request->hasFile('logo_path')) {
            $logoFile = $request->file('logo_path');
            $logoPath = $logoFile->store('questions/logo', 'public');
            $validated['logo_path'] = $logoPath;
            Log::info('Logo file stored successfully', [
                'timestamp' => now()->toDateTimeString(),
                'logo_path' => $logoPath,
            ]);
        } else {
            Log::info('No logo file provided for question', [
                'timestamp' => now()->toDateTimeString()
            ]);
        }

        $question = Question::create($validated);
        Log::info('Question created', [
            'timestamp'     => now()->toDateTimeString(),
            'user_id'       => $userId,
            'question_id'   => $question->id,
            'challenge_id'  => $validated['challenge_id'],
        ]);

        return response()->json($question, 201);
    }

    public function show(Question $question)
    {
        $userId    = auth()->id();
        $ip        = request()->ip();
        $userAgent = request()->header('User-Agent');
        $timestamp = now()->toDateTimeString();

        Log::info('show method called', [
            'timestamp'   => $timestamp,
            'question_id' => $question->id,
            'user_id'     => $userId,
            'ip'          => $ip,
            'user_agent'  => $userAgent,
        ]);
        
        $question->load('challenge', 'answers');
        
        if (auth()->user()?->isAdmin()) {
            $question->load('correctAnswer');
        }
    
        Log::info('Question loaded', [
            'timestamp' => now()->toDateTimeString(),
            'question'  => $question->toArray(),
        ]);
        return response()->json($question);
    }

    public function update(Request $request, Question $question)
    {
        $userId    = auth()->id();
        $ip        = $request->ip();
        $userAgent = $request->header('User-Agent');
        $timestamp = now()->toDateTimeString();

        Log::info('update method called', [
            'timestamp'    => $timestamp,
            'question_id'  => $question->id,
            'user_id'      => $userId,
            'ip'           => $ip,
            'user_agent'   => $userAgent,
            'request_data' => $request->all(),
        ]);
        
        $validated = $request->validate([
            'challenge_id' => 'required|exists:challenges,id',
            'question'     => 'required|string',
            'logo_path'    => 'nullable|file|mimes:jpeg,jpg,png|max:8192',
            'points'       => 'required|integer|min:1',
            'answer_id'    => 'nullable|exists:answers,id',
        ]);
        
        Log::info('Validation passed for update', [
            'timestamp'      => now()->toDateTimeString(),
            'validated_data' => $validated,
        ]);

        if ($request->hasFile('logo_path')) {
            if ($question->logo_path) {
                Storage::disk('public')->delete($question->logo_path);
                Log::info('Old logo file deleted', [
                    'timestamp'     => now()->toDateTimeString(),
                    'old_logo_path' => $question->logo_path,
                ]);
            }
            $logoPath = $request->file('logo_path')->store('questions/logo', 'public');
            $validated['logo_path'] = $logoPath;
            Log::info('New logo file stored successfully', [
                'timestamp' => now()->toDateTimeString(),
                'logo_path' => $logoPath,
            ]);
        } else {
            $validated['logo_path'] = $question->logo_path;
            Log::info('No new logo file provided, keeping existing', [
                'timestamp' => now()->toDateTimeString(),
                'logo_path' => $question->logo_path,
            ]);
        }

        $question->update($validated);
        Log::info('Question updated', [
            'timestamp'    => now()->toDateTimeString(),
            'question_id'  => $question->id,
            'updated_data' => $validated,
        ]);

        return response()->json($question);
    }

    public function destroy(Question $question)
    {
        $userId    = auth()->id();
        $ip        = request()->ip();
        $userAgent = request()->header('User-Agent');
        $timestamp = now()->toDateTimeString();

        Log::info('destroy method called', [
            'timestamp'   => $timestamp,
            'question_id' => $question->id,
            'user_id'     => $userId,
            'ip'          => $ip,
            'user_agent'  => $userAgent,
        ]);
        
        $question->delete();
        Log::info('Question deleted', [
            'timestamp'   => now()->toDateTimeString(),
            'question_id' => $question->id,
        ]);
        
        return response()->json(null, 204);
    }
}
