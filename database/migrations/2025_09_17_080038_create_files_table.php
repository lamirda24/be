<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size');
            $table->string('file_type', 100);
            $table->string('original_filename');

            $table->foreignId('category_id')->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->foreignId('uploaded_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('download_count')->default(0);

            $table->timestamps();

            $table->index('category_id', 'idx_files_category');
            $table->index('is_visible', 'idx_files_visible');
            $table->index('created_at', 'idx_files_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
