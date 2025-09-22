<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
  use HasFactory, SoftDeletes;

  protected $table = 'files';

  protected $fillable = [
    'title',
    'slug',
    'description',
    'category_id',
    'storage_path',
    'original_name',
    'mime_type',
    'size_bytes',
    'uploaded_by',
    'is_published',
    'download_count',
  ];

  protected $casts = [
    'size_bytes'     => 'integer',
    'is_published'   => 'boolean',
    'download_count' => 'integer',
    'deleted_at'   => 'datetime', // optional but nice


  ];



  protected $appends = ['size_human'];

  /** Relasi: kategori file (wajib satu kategori) */
  public function category(): BelongsTo
  {
    return $this->belongsTo(Category::class, 'category_id');
  }

  /** Relasi: user pengunggah */
  public function uploader(): BelongsTo
  {
    return $this->belongsTo(User::class, 'uploaded_by');
  }

  /** Relasi: log unduhan */
  public function downloadLogs(): HasMany
  {
    return $this->hasMany(DownloadLog::class, 'file_id');
  }

  /** Accessor: ukuran file human readable */
  public function getSizeHumanAttribute(): string
  {
    $size = (int) ($this->size_bytes ?? 0);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
      $size /= 1024;
      $i++;
    }
    return sprintf('%s %s', round($size, 2), $units[$i]);
  }

  /** Helper: catat download + increment counter */
  public function recordDownload(?string $ip = null, ?string $ua = null): void
  {
    $this->downloadLogs()->create([
      'ip_address' => $ip,
      'user_agent' => $ua,
    ]);
    $this->increment('download_count');
  }
}
