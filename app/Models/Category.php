<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
  use HasFactory;

  protected $table = 'categories';

  // Hanya kolom 'name'
  protected $fillable = ['name'];

  // app/Models/Category.php
  protected $visible = ['id', 'name'];

  // Relasi: satu kategori punya banyak file
  public function files(): HasMany
  {
    return $this->hasMany(File::class, 'category_id');
  }
}
