<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->string('attachment_code', 50)->primary();
            $table->string('attachment_file_name');
            $table->string('attachment_file_path');
            $table->string('attachment_mime_type')->nullable();
            $table->unsignedBigInteger('attachment_size_bytes')->nullable();
            $table->string('attachment_target_type');
            $table->string('attachment_target_code', 50);
            $table->string('attachment_uploaded_by', 50);
            $table->timestamp('attachment_created_at')->nullable();
            $table->timestamp('attachment_updated_at')->nullable();

            $table->foreign('attachment_uploaded_by')
                ->references('user_code')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
