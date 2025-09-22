<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('download_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')
                ->constrained('files')
                ->cascadeOnDelete();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('downloaded_at')->useCurrent();

            $table->index('file_id', 'idx_download_logs_file');
            $table->index('downloaded_at', 'idx_download_logs_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_logs');
    }
};
