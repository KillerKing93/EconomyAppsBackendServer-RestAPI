<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'question_id',
        'answer_id',
        'start_time',
        'end_time',
    ];

    // Define the relationship to the User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Define the relationship to the Question model
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    // Define the relationship to the Answer model
    public function answer()
    {
        return $this->belongsTo(Answer::class);
    }

    // Get the time spent answering the question
    public function getTimeSpentAttribute()
    {
        return $this->start_time && $this->end_time
            ? $this->end_time->diffInSeconds($this->start_time)
            : 0;
    }
}
