<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'module_id',
        'title',
        'content',
        'pdf_path',
        'logo_path'];

    protected $appends = ['pdf_url', 'user_progress'];

    public function getPdfUrlAttribute()
    {
        return $this->pdf_path ? asset('storage/'.$this->pdf_path) : null;
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function getUserProgressAttribute()
    {
        if (!auth()->check()) return null;
        
        return $this->materialProgress()
            ->where('user_id', auth()->id())
            ->first();
    }
}
