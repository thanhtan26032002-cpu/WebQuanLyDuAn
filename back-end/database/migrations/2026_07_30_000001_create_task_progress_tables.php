<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_checklists', function (Blueprint $table) {
            $table->string('checklist_code', 50)->primary();
            $table->string('checklist_task_code', 50);
            $table->string('checklist_text');
            $table->boolean('checklist_is_completed')->default(false);
            $table->timestamp('checklist_created_at')->nullable();
            $table->timestamp('checklist_updated_at')->nullable();

            $table->foreign('checklist_task_code')
                ->references('task_code')
                ->on('tasks')
                ->cascadeOnDelete();
        });

        Schema::create('task_work_logs', function (Blueprint $table) {
            $table->string('worklog_code', 50)->primary();
            $table->string('worklog_task_code', 50);
            $table->string('worklog_reporter_code', 50)->nullable();
            $table->string('worklog_time', 5);
            $table->text('worklog_note')->nullable();
            $table->date('worklog_date');
            $table->json('worklog_completed_items')->nullable();
            $table->json('worklog_files')->nullable();
            $table->timestamp('worklog_created_at')->nullable();
            $table->timestamp('worklog_updated_at')->nullable();

            $table->foreign('worklog_task_code')
                ->references('task_code')
                ->on('tasks')
                ->cascadeOnDelete();
            $table->foreign('worklog_reporter_code')
                ->references('member_code')
                ->on('members')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_work_logs');
        Schema::dropIfExists('task_checklists');
    }
};
