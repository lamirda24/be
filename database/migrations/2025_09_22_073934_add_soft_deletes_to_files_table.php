<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('files', function (Blueprint $t) {
            $t->softDeletes(); // adds nullable deleted_at
        });
    }
    public function down(): void
    {
        Schema::table('files', function (Blueprint $t) {
            $t->dropSoftDeletes();
        });
    }
};
