<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;
    
    // Ganti 'material_id' menjadi 'challenge_id'
    protected $fillable = ['challenge_id', 'question', 'logo_path', 'points', 'answer_id'];

    // Relasi: Question milik Challenge
    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }

    public function correctAnswer()
    {
        return $this->belongsTo(Answer::class, 'answer_id');
    }
    
    public function userAnswers()
    {
        return $this->hasMany(UserAnswer::class);
    }
}
