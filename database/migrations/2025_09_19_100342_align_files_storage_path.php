<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Kalau TIDAK ada storage_path tapi ADA file_path -> rename
        if (!Schema::hasColumn('files', 'storage_path') && Schema::hasColumn('files', 'file_path')) {
            Schema::table('files', function (Blueprint $t) {
                $t->renameColumn('file_path', 'storage_path'); // butuh doctrine/dbal
            });
        }

        // 2) Kalau KEDUANYA ada -> backfill lalu drop file_path
        if (Schema::hasColumn('files', 'storage_path') && Schema::hasColumn('files', 'file_path')) {
            // backfill nilai storage_path dari file_path jika storage_path masih NULL
            DB::statement("
                UPDATE `files`
                SET `storage_path` = `file_path`
                WHERE `storage_path` IS NULL AND `file_path` IS NOT NULL
            ");

            // hapus kolom lama biar tidak ganggu insert
            Schema::table('files', function (Blueprint $t) {
                $t->dropColumn('file_path');
            });
        }

        // 3) Pastikan storage_path ada & NOT NULL
        if (!Schema::hasColumn('files', 'storage_path')) {
            Schema::table('files', function (Blueprint $t) {
                $t->string('storage_path')->after('category_id');
            });
        }

        // jaga-jaga: kalau ada NULL tersisa, isi string kosong (atau path default)
        DB::table('files')->whereNull('storage_path')->update(['storage_path' => '']);

        // (opsional) kunci NOT NULL di MySQL
        DB::statement("ALTER TABLE `files` MODIFY `storage_path` VARCHAR(255) NOT NULL");
    }

    public function down(): void
    {
        // Tidak mengembalikan kolom file_path, biarkan tetap standar baru
        // (Jika perlu rollback penuh, tambahkan kembali file_path sebagai nullable)
        // Schema::table('files', function (Blueprint $t) {
        //     $t->string('file_path')->nullable();
        // });
    }
};
