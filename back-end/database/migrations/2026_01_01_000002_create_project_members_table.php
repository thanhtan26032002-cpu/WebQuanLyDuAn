<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_members', function (Blueprint $table) {
            $table->string('pm_code', 50)->primary();
            $table->string('pm_project_code', 50);
            $table->string('pm_member_code', 50);
            $table->string('pm_role')->default('member');
            $table->timestamp('pm_created_at')->nullable();
            $table->timestamp('pm_updated_at')->nullable();

            $table->unique(['pm_project_code', 'pm_member_code']);
            $table->foreign('pm_project_code')
                ->references('project_code')
                ->on('projects')
                ->cascadeOnDelete();
            $table->foreign('pm_member_code')
                ->references('member_code')
                ->on('members')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};
