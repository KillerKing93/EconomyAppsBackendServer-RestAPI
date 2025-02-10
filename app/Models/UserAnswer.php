<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attempt_id',
        'question_id',
        'answer_id',
        'start_time',
        'end_time',
    ];

    // Tambahkan casting agar start_time dan end_time otomatis menjadi objek Carbon
    protected $casts = [
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
    ];

    // Relasi ke User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke Question
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    // Relasi ke Answer
    public function answer()
    {
        return $this->belongsTo(Answer::class);
    }

    // Accessor untuk menghitung waktu pengerjaan
    public function getTimeSpentAttribute()
    {
        return ($this->start_time && $this->end_time)
            ? $this->end_time->diffInSeconds($this->start_time)
            : 0;
    }
}
