<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('files', function (Blueprint $t) {
            // Tambah kolom jika belum ada
            if (!Schema::hasColumn('files', 'is_published')) {
                $t->boolean('is_published')->default(1)->index()->after('uploaded_by');
            }
        });

        // Backfill nilai NULL ke 1 (jaga-jaga)
        DB::table('files')->whereNull('is_published')->update(['is_published' => 1]);

        // Pastikan tipe + default (tanpa perlu doctrine/dbal)
        // MySQL/MariaDB
        DB::statement("ALTER TABLE `files` 
            MODIFY `is_published` TINYINT(1) NOT NULL DEFAULT 1");
    }

    public function down(): void
    {
        // (opsional) kembalikan ke nullable tanpa default
        DB::statement("ALTER TABLE `files`
            MODIFY `is_published` TINYINT(1) NULL DEFAULT NULL");

        // atau kalau ingin drop kolom (hati-hati di prod):
        // Schema::table('files', function (Blueprint $t) {
        //     $t->dropColumn('is_published');
        // });
    }
};
