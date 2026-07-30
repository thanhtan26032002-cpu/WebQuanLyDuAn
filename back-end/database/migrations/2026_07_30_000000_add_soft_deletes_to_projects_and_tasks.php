<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('project_deleted_at')->nullable()->index();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('task_deleted_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('task_deleted_at');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('project_deleted_at');
        });
    }
};
