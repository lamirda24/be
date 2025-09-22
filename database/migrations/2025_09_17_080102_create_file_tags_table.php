<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('file_tags', function (Blueprint $table) {
            $table->foreignId('file_id')
                ->constrained('files')
                ->cascadeOnDelete();

            $table->foreignId('tag_id')
                ->constrained('tags')
                ->cascadeOnDelete();

            $table->primary(['file_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_tags');
    }
};
