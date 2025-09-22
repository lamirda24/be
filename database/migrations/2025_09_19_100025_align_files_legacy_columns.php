<?php
// php artisan make:migration align_files_legacy_columns
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // butuh doctrine/dbal untuk renameColumn
        // composer require --dev doctrine/dbal

        Schema::table('files', function (Blueprint $t) {
            if (Schema::hasColumn('files', 'name') && !Schema::hasColumn('files', 'title')) {
                $t->renameColumn('name', 'title');
            }
            if (Schema::hasColumn('files', 'file_path') && !Schema::hasColumn('files', 'storage_path')) {
                $t->renameColumn('file_path', 'storage_path');
            }
            if (Schema::hasColumn('files', 'file_size') && !Schema::hasColumn('files', 'size_bytes')) {
                $t->renameColumn('file_size', 'size_bytes');
            }
            if (Schema::hasColumn('files', 'file_type') && !Schema::hasColumn('files', 'mime_type')) {
                $t->renameColumn('file_type', 'mime_type');
            }
            if (Schema::hasColumn('files', 'original_filename') && !Schema::hasColumn('files', 'original_name')) {
                $t->renameColumn('original_filename', 'original_name');
            }
            if (Schema::hasColumn('files', 'is_visible') && !Schema::hasColumn('files', 'is_published')) {
                $t->renameColumn('is_visible', 'is_published');
            }

            if (!Schema::hasColumn('files', 'slug')) {
                $t->string('slug')->unique()->after('title');
            }

            // pastikan download_count ada
            if (!Schema::hasColumn('files', 'download_count')) {
                $t->unsignedBigInteger('download_count')->default(0)->after('is_published');
            }
        });

        // pastikan default & NOT NULL utk is_published
        DB::statement("UPDATE `files` SET `is_published` = 1 WHERE `is_published` IS NULL");
        DB::statement("ALTER TABLE `files` MODIFY `is_published` TINYINT(1) NOT NULL DEFAULT 1");
    }

    public function down(): void
    {
        // optional rollback rename; biasanya tidak perlu dibalikin
    }
};
