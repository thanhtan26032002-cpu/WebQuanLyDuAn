<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->string('project_code', 50)->primary();
            $table->string('project_name');
            $table->text('project_description')->nullable();
            $table->string('project_status')->default('planning');
            $table->date('project_start_date')->nullable();
            $table->date('project_due_date')->nullable();
            $table->unsignedInteger('project_progress')->default(0);
            $table->string('project_created_by', 50);
            $table->timestamp('project_created_at')->nullable();
            $table->timestamp('project_updated_at')->nullable();

            $table->foreign('project_created_by')
                ->references('user_code')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
