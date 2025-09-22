<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;   // ⬅️ penting untuk token
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory; // , Notifiable kalau perlu

    protected $table = 'users';

    protected $fillable = [
        'email',
        'password',
        'full_name',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime', // aman walau kolom opsional
    ];

    /**
     * Auto-hash password kalau input bukan hash
     */
    public function setPasswordAttribute($value): void
    {
        if (!empty($value) && !str_starts_with((string) $value, '$2y$')) {
            $this->attributes['password'] = Hash::make($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }

    /**
     * Relasi: file yang diupload user ini
     */
    public function uploadedFiles(): HasMany
    {
        return $this->hasMany(File::class, 'uploaded_by');
    }
}
