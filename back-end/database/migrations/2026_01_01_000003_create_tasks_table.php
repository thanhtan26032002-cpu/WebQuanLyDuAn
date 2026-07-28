<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->string('task_code', 50)->primary();
            $table->string('task_project_code', 50)->nullable();
            $table->string('task_title');
            $table->text('task_description')->nullable();
            $table->string('task_status')->default('todo');
            $table->string('task_priority')->default('medium');
            $table->date('task_due_date')->nullable();
            $table->unsignedInteger('task_progress')->default(0);
            $table->string('task_assignee_code', 50)->nullable();
            $table->string('task_tags', 500)->nullable();
            $table->timestamp('task_created_at')->nullable();
            $table->timestamp('task_updated_at')->nullable();

            $table->foreign('task_project_code')
                ->references('project_code')
                ->on('projects')
                ->cascadeOnDelete();
            $table->foreign('task_assignee_code')
                ->references('member_code')
                ->on('members')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
