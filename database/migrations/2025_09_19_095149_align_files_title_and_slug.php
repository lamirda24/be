<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Rename name -> title (jika ada 'name' dan belum ada 'title')
        if (Schema::hasColumn('files', 'name') && !Schema::hasColumn('files', 'title')) {
            Schema::table('files', function (Blueprint $t) {
                $t->renameColumn('name', 'title');
            });
        }

        // 2) Tambah slug unik (jika belum ada)
        if (!Schema::hasColumn('files', 'slug')) {
            Schema::table('files', function (Blueprint $t) {
                $t->string('slug')->unique()->after('title');
            });
        }
    }

    public function down(): void
    {
        // Hapus slug
        if (Schema::hasColumn('files', 'slug')) {
            Schema::table('files', function (Blueprint $t) {
                // index name default: files_slug_unique
                $t->dropUnique('files_slug_unique');
                $t->dropColumn('slug');
            });
        }

        // Balikkan title -> name (opsional)
        if (Schema::hasColumn('files', 'title') && !Schema::hasColumn('files', 'name')) {
            Schema::table('files', function (Blueprint $t) {
                $t->renameColumn('title', 'name');
            });
        }
    }
};
