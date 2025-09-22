<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadLog extends Model
{
  use HasFactory;

  protected $table = 'download_logs';

  public $timestamps = false; // pakai downloaded_at saja

  protected $fillable = [
    'file_id',
    'ip_address',
    'user_agent',
    'downloaded_at',
  ];

  protected $casts = [
    'downloaded_at' => 'datetime',
  ];

  public function file(): BelongsTo
  {
    return $this->belongsTo(File::class, 'file_id');
  }
}
