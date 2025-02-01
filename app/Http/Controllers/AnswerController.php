<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use Illuminate\Http\Request;

class AnswerController extends Controller
{
    public function index()
    {
        return response()->json(Answer::with('question')->get());
    }

    public function store(Request $request)
    {
        $answer = Answer::create($request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer' => 'required|string',
            'logo_path' => 'nullable|string',
        ]));

        return response()->json($answer, 201);
    }

    public function show(Answer $answer)
    {
        return response()->json($answer->load('question'));
    }

    public function update(Request $request, Answer $answer)
    {
        $answer->update($request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer' => 'required|string',
            'logo_path' => 'nullable|string',
        ]));

        return response()->json($answer);
    }

    public function destroy(Answer $answer)
    {
        $answer->delete();
        return response()->json(null, 204);
    }
}