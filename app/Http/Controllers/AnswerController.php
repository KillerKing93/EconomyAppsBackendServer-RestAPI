<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnswerController extends Controller
{
    public function index()
    {
        Log::info('Fetching all answers with their questions');
        $answers = Answer::with('question')->get();
        return response()->json($answers);
    }

    public function getAnswersByQuestion($questionId)
    {
        $userId    = auth()->id();
        $ip        = request()->ip();
        $userAgent = request()->header('User-Agent');
        $timestamp = now()->toDateTimeString();
    
        Log::info('getAnswersByQuestion called', [
            'timestamp'   => $timestamp,
            'question_id' => $questionId,
            'user_id'     => $userId,
            'ip'          => $ip,
            'user_agent'  => $userAgent,
        ]);
    
        $answers = \App\Models\Answer::where('question_id', $questionId)->get();
    
        Log::info('Answers retrieved for question', [
            'timestamp'   => now()->toDateTimeString(),
            'question_id' => $questionId,
            'count'       => $answers->count(),
        ]);
    
        return response()->json([
            'status' => 'success',
            'data'   => $answers,
        ]);
    }    
    
    public function store(Request $request)
    {
        try {
            Log::info('Store answer request received', ['request' => $request->all()]);

            // Validate the input, including the file if provided
            $validated = $request->validate([
                'question_id' => 'required|exists:questions,id',
                'answer'      => 'required|string',
                'logo_path'   => 'nullable|file|mimes:jpeg,jpg,png|max:2048',
            ]);

            $logoPath = null;
            if ($request->hasFile('logo_path')) {
                $logoFile = $request->file('logo_path');
                $logoPath = $logoFile->store('answers/logo', 'public');
                Log::info('Answer image stored successfully', ['logoPath' => $logoPath]);
            } else {
                Log::info('No image file provided for the answer');
            }

            $data = [
                'question_id' => $validated['question_id'],
                'answer'      => $validated['answer'],
                'logo_path'   => $logoPath,
            ];

            $answer = Answer::create($data);
            Log::info('Answer created successfully', ['answer_id' => $answer->id]);

            return response()->json($answer, 201);
        } catch (\Exception $e) {
            Log::error('Error creating answer', ['error' => $e->getMessage()]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Error creating answer'
            ], 500);
        }
    }

    public function show(Answer $answer)
    {
        Log::info('Showing answer', ['answer_id' => $answer->id]);
        return response()->json($answer->load('question'));
    }

    public function update(Request $request, Answer $answer)
    {
        try {
            Log::info('Update answer request received', [
                'answer_id' => $answer->id,
                'request'   => $request->all()
            ]);

            $validated = $request->validate([
                'question_id' => 'required|exists:questions,id',
                'answer'      => 'required|string',
                'logo_path'   => 'nullable|file|mimes:jpeg,jpg,png|max:2048',
            ]);

            $updateData = [
                'question_id' => $validated['question_id'],
                'answer'      => $validated['answer'],
            ];

            if ($request->hasFile('logo_path')) {
                // Delete the old image if it exists
                if ($answer->logo_path) {
                    Storage::disk('public')->delete($answer->logo_path);
                    Log::info('Old answer image deleted', ['old_logo_path' => $answer->logo_path]);
                }
                $logoFile = $request->file('logo_path');
                $logoPath = $logoFile->store('answers/logo', 'public');
                $updateData['logo_path'] = $logoPath;
                Log::info('New answer image stored', ['logoPath' => $logoPath]);
            } else {
                // Retain the existing logo_path if no new file is provided
                $updateData['logo_path'] = $answer->logo_path;
            }

            $answer->update($updateData);
            Log::info('Answer updated successfully', ['answer_id' => $answer->id]);

            return response()->json($answer);
        } catch (\Exception $e) {
            Log::error('Error updating answer', [
                'error'     => $e->getMessage(),
                'answer_id' => $answer->id
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Error updating answer'
            ], 500);
        }
    }

    public function destroy(Answer $answer)
    {
        try {
            Log::info('Delete answer request received', ['answer_id' => $answer->id]);
            if ($answer->logo_path) {
                Storage::disk('public')->delete($answer->logo_path);
                Log::info('Answer image deleted', ['logo_path' => $answer->logo_path]);
            }
            $answer->delete();
            Log::info('Answer deleted successfully', ['answer_id' => $answer->id]);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Error deleting answer', [
                'error'     => $e->getMessage(),
                'answer_id' => $answer->id
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Error deleting answer'
            ], 500);
        }
    }
}
