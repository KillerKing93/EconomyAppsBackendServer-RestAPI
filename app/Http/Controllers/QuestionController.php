<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    public function index()
    {
        $questions = Question::with('material', 'answers')
            ->when(auth()->user()?->isAdmin(), function ($query) {
                $query->with('correctAnswer');
            })
            ->get();
    
        return response()->json($questions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'material_id' => 'required|exists:materials,id',
            'question' => 'required|string',
            'logo_path' => 'nullable|string',
            'points' => 'integer|min:1',
            'answer_id' => [
                'nullable',
                Rule::exists('answers', 'id')->where('question_id', $request->question_id)
            ],
        ]);
        
        $question = Question::create($validated);

        return response()->json($question, 201);
    }

    public function show(Question $question)
    {
        $question->load('material', 'answers');
        
        if (auth()->user()?->isAdmin()) {
            $question->load('correctAnswer');
        }
    
        return response()->json($question);
    }

    public function update(Request $request, Question $question)
    {
        $question->update($request->validate([
            'material_id' => 'required|exists:materials,id',
            'question' => 'required|string',
            'logo_path' => 'nullable|string',
            'points' => 'integer|min:1',
            'answer_id' => 'nullable|exists:answers,id',
        ]));

        return response()->json($question);
    }

    public function destroy(Question $question)
    {
        $question->delete();
        return response()->json(null, 204);
    }
}
