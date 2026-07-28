<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comments', function (Blueprint $table) {
            $table->string('comment_code', 50)->primary();
            $table->string('comment_task_code', 50);
            $table->string('comment_user_code', 50);
            $table->text('comment_text');
            $table->string('comment_file_url')->nullable();
            $table->string('comment_file_name')->nullable();
            $table->timestamp('comment_created_at')->nullable();
            $table->timestamp('comment_updated_at')->nullable();

            $table->foreign('comment_task_code')
                ->references('task_code')
                ->on('tasks')
                ->cascadeOnDelete();
            $table->foreign('comment_user_code')
                ->references('user_code')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
    }
};
