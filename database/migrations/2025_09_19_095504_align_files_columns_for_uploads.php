<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('files', function (Blueprint $t) {
            // pastikan slug ada & unik (skip jika sudah)
            if (!Schema::hasColumn('files', 'slug')) {
                $t->string('slug')->unique()->after('title');
            }

            // kolom yang dibutuhkan controller
            if (!Schema::hasColumn('files', 'category_id')) {
                $t->unsignedBigInteger('category_id')->nullable()->after('description');
                $t->index('category_id');
            }
            if (!Schema::hasColumn('files', 'storage_path')) {
                $t->string('storage_path')->after('category_id');
            }
            if (!Schema::hasColumn('files', 'original_name')) {
                $t->string('original_name')->after('storage_path');
            }
            if (!Schema::hasColumn('files', 'mime_type')) {
                $t->string('mime_type', 191)->after('original_name');
            }
            if (!Schema::hasColumn('files', 'size_bytes')) {
                $t->unsignedBigInteger('size_bytes')->after('mime_type');
            }
            if (!Schema::hasColumn('files', 'uploaded_by')) {
                $t->unsignedBigInteger('uploaded_by')->nullable()->after('size_bytes');
                $t->index('uploaded_by');
            }
            if (!Schema::hasColumn('files', 'is_published')) {
                $t->boolean('is_published')->default(true)->index()->after('uploaded_by');
            }
        });

        // Tambah FK (aman kalau tabel sudah sesuai; abaikan kalau belum butuh)
        Schema::table('files', function (Blueprint $t) {
            // drop dulu kalau sudah ada & berbeda
            try {
                $t->dropForeign(['category_id']);
            } catch (\Throwable $e) {
            }
            try {
                $t->dropForeign(['uploaded_by']);
            } catch (\Throwable $e) {
            }

            if (Schema::hasColumn('files', 'category_id')) {
                $t->foreign('category_id', 'files_category_id_foreign')
                    ->references('id')->on('categories')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete(); // atau ->nullOnDelete() jika boleh null
            }

            if (Schema::hasColumn('files', 'uploaded_by')) {
                $t->foreign('uploaded_by', 'files_uploaded_by_foreign')
                    ->references('id')->on('users')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $t) {
            try {
                $t->dropForeign(['category_id']);
            } catch (\Throwable $e) {
            }
            try {
                $t->dropForeign(['uploaded_by']);
            } catch (\Throwable $e) {
            }

            // rollback kolom (opsional)
            if (Schema::hasColumn('files', 'is_published'))   $t->dropColumn('is_published');
            if (Schema::hasColumn('files', 'uploaded_by'))    $t->dropColumn('uploaded_by');
            if (Schema::hasColumn('files', 'size_bytes'))     $t->dropColumn('size_bytes');
            if (Schema::hasColumn('files', 'mime_type'))      $t->dropColumn('mime_type');
            if (Schema::hasColumn('files', 'original_name'))  $t->dropColumn('original_name');
            if (Schema::hasColumn('files', 'storage_path'))   $t->dropColumn('storage_path');
            // slug biasanya dipertahankan; hapus hanya jika perlu:
            // if (Schema::hasColumn('files','slug')) { $t->dropUnique('files_slug_unique'); $t->dropColumn('slug'); }
            // category_id juga biasanya dipertahankan
        });
    }
};
