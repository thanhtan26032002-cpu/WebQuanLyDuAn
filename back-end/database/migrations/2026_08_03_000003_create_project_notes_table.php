<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_notes', function (Blueprint $table) {
            $table->string('note_code', 50)->primary();
            $table->string('note_project_code', 50);
            $table->string('note_author_code', 50);
            $table->string('note_title', 255);
            $table->text('note_content');
            $table->boolean('note_is_pinned')->default(false);
            $table->timestamp('note_created_at')->nullable();
            $table->timestamp('note_updated_at')->nullable();

            $table->foreign('note_project_code')
                ->references('project_code')->on('projects')->cascadeOnDelete();
            $table->foreign('note_author_code')
                ->references('user_code')->on('users')->cascadeOnDelete();
            $table->index(
                ['note_project_code', 'note_is_pinned', 'note_updated_at'],
                'project_notes_project_pinned_updated_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_notes');
    }
};
