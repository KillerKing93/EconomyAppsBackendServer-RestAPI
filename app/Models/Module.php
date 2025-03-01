<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;
    
    protected $fillable = ['title', 'description', 'logo_path', 'jumlah_pertanyaan'];

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function challenges()
    {
        return $this->hasManyThrough(Challenge::class, Material::class);
    }

}
