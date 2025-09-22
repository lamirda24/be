<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
  use HasFactory;

  protected $table = 'tags';

  public $timestamps = false; // di skema hanya created_at

  protected $fillable = [
    'name',
    'created_at',
  ];

  protected $casts = [
    'created_at' => 'datetime',
  ];

  /** Files yang memiliki tag ini */
  public function files(): BelongsToMany
  {
    return $this->belongsToMany(File::class, 'file_tags', 'tag_id', 'file_id');
  }
}
