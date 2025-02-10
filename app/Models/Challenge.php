<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'title',
        'content',
        'logo_path',
        'jumlah_pertanyaan'
    ];

    // Relasi: Challenge milik Material
    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    // Relasi: Challenge memiliki banyak Question
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    // Jika Anda ingin menambahkan fitur progress (misalnya mirip dengan MaterialProgress),
    // Anda bisa membuat model dan migration baru (misalnya ChallengeProgress) dan menambahkan relasi di sini.
}
