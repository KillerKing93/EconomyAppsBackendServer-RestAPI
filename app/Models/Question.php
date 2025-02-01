<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;
    
    protected $fillable = ['material_id', 'question', 'logo_path', 'points', 'answer_id'];

    public function material()
    {
        return $this->belongsTo(Material::class);
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
