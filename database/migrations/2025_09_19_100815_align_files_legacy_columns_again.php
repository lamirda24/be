<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ===== name -> title =====
        if (Schema::hasColumn('files', 'name') && !Schema::hasColumn('files', 'title')) {
            Schema::table('files', fn(Blueprint $t) => $t->renameColumn('name', 'title'));
        }

        // ===== slug (pastikan ada) =====
        if (!Schema::hasColumn('files', 'slug')) {
            Schema::table('files', fn(Blueprint $t) => $t->string('slug')->unique()->after('title'));
        }

        // ===== file_path -> storage_path =====
        if (!Schema::hasColumn('files', 'storage_path') && Schema::hasColumn('files', 'file_path')) {
            Schema::table('files', fn(Blueprint $t) => $t->renameColumn('file_path', 'storage_path'));
        } elseif (Schema::hasColumn('files', 'storage_path') && Schema::hasColumn('files', 'file_path')) {
            DB::statement("UPDATE `files` SET `storage_path` = COALESCE(`storage_path`,`file_path`)");
            Schema::table('files', fn(Blueprint $t) => $t->dropColumn('file_path'));
        }
        // pastikan NOT NULL
        if (Schema::hasColumn('files', 'storage_path')) {
            DB::table('files')->whereNull('storage_path')->update(['storage_path' => '']);
            DB::statement("ALTER TABLE `files` MODIFY `storage_path` VARCHAR(255) NOT NULL");
        }

        // ===== file_size -> size_bytes =====
        if (!Schema::hasColumn('files', 'size_bytes') && Schema::hasColumn('files', 'file_size')) {
            Schema::table('files', fn(Blueprint $t) => $t->renameColumn('file_size', 'size_bytes'));
        } elseif (Schema::hasColumn('files', 'size_bytes') && Schema::hasColumn('files', 'file_size')) {
            DB::statement("UPDATE `files` SET `size_bytes` = COALESCE(`size_bytes`,`file_size`)");
            Schema::table('files', fn(Blueprint $t) => $t->dropColumn('file_size'));
        }
        if (Schema::hasColumn('files', 'size_bytes')) {
            DB::statement("ALTER TABLE `files` MODIFY `size_bytes` BIGINT UNSIGNED NOT NULL");
        }

        // ===== file_type -> mime_type =====
        if (!Schema::hasColumn('files', 'mime_type') && Schema::hasColumn('files', 'file_type')) {
            Schema::table('files', fn(Blueprint $t) => $t->renameColumn('file_type', 'mime_type'));
        } elseif (Schema::hasColumn('files', 'mime_type') && Schema::hasColumn('files', 'file_type')) {
            DB::statement("UPDATE `files` SET `mime_type` = COALESCE(`mime_type`,`file_type`)");
            Schema::table('files', fn(Blueprint $t) => $t->dropColumn('file_type'));
        }
        if (Schema::hasColumn('files', 'mime_type')) {
            DB::statement("ALTER TABLE `files` MODIFY `mime_type` VARCHAR(191) NOT NULL");
        }

        // ===== original_filename -> original_name =====
        if (!Schema::hasColumn('files', 'original_name') && Schema::hasColumn('files', 'original_filename')) {
            Schema::table('files', fn(Blueprint $t) => $t->renameColumn('original_filename', 'original_name'));
        } elseif (Schema::hasColumn('files', 'original_name') && Schema::hasColumn('files', 'original_filename')) {
            DB::statement("UPDATE `files` SET `original_name` = COALESCE(`original_name`,`original_filename`)");
            Schema::table('files', fn(Blueprint $t) => $t->dropColumn('original_filename'));
        }
        if (Schema::hasColumn('files', 'original_name')) {
            DB::statement("ALTER TABLE `files` MODIFY `original_name` VARCHAR(255) NOT NULL");
        }

        // ===== is_visible -> is_published (default 1) =====
        if (!Schema::hasColumn('files', 'is_published') && Schema::hasColumn('files', 'is_visible')) {
            Schema::table('files', fn(Blueprint $t) => $t->renameColumn('is_visible', 'is_published'));
        } elseif (Schema::hasColumn('files', 'is_published') && Schema::hasColumn('files', 'is_visible')) {
            DB::statement("UPDATE `files` SET `is_published` = COALESCE(`is_published`,`is_visible`)");
            Schema::table('files', fn(Blueprint $t) => $t->dropColumn('is_visible'));
        }
        if (!Schema::hasColumn('files', 'is_published')) {
            Schema::table('files', fn(Blueprint $t) => $t->boolean('is_published')->default(1)->after('uploaded_by'));
        }
        DB::table('files')->whereNull('is_published')->update(['is_published' => 1]);
        DB::statement("ALTER TABLE `files` MODIFY `is_published` TINYINT(1) NOT NULL DEFAULT 1");

        // ===== download_count pastikan ada =====
        if (!Schema::hasColumn('files', 'download_count')) {
            Schema::table('files', fn(Blueprint $t) => $t->unsignedBigInteger('download_count')->default(0)->after('is_published'));
        }
    }

    public function down(): void
    {
        // No-op: tidak mengembalikan kolom lama
    }
};
