<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\DownloadLogController;
use App\Http\Controllers\FileSignedController;


use App\Models\File;
// routes/api.php
Route::get('/health', fn() => ['status' => 'ok']);


Route::bind('file', function ($value) {
  return File::where('id', $value)
    ->orWhere('slug', $value)
    ->firstOrFail();
});

// routes/api.php



// 1) FE asks BE for a temporary signed URL (JSON back)
Route::post('/files/{slug}/signed-url', [FileSignedController::class, 'issue'])
  ->name('files.issue');

// 2) The actual signed download endpoint (must be GET and signed)
Route::get('/files/signed-download', [FileSignedController::class, 'download'])
  ->middleware('signed')                     // validates signature & expiry
  ->name('files.signed-download');



Route::prefix('auth')->group(function () {
  Route::post('register', [AuthController::class, 'register']);
  Route::post('login',    [AuthController::class, 'login']);
  Route::middleware('auth:sanctum')->group(function () {
    Route::get('me',      [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
  });
});

// Public reads
Route::get('files', [FileController::class, 'index']);
Route::get('categories', [CategoryController::class, 'index']);
// Route::get('files/{slug}', [FileController::class, 'show']); //toggle visibility
Route::post('files/{slug}/download', [FileController::class, 'url']); // logs + increments

// Admin/protected writes
Route::middleware('auth:sanctum')->group(function () {
  // Route::apiResource('categories', CategoryController::class)->except(['show']);
  Route::apiResource('tags', TagController::class)->only(['index', 'store', 'destroy']);
  Route::apiResource('files', FileController::class)->only(['store', 'update', 'destroy']);
  // extra actions for soft deletes:
  Route::post('files/{idOrSlug}/restore', [FileController::class, 'restore']);
  Route::delete('files/{idOrSlug}/force', [FileController::class, 'forceDestroy']);
  Route::get('files/{id}/logs', [DownloadLogController::class, 'index']);
  Route::get('files/stats', [FileController::class, 'stats']);
});
