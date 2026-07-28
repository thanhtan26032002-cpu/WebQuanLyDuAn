<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->string('notif_code', 50)->primary();
            $table->string('notif_user_code', 50);
            $table->string('notif_title');
            $table->text('notif_message');
            $table->string('notif_type')->default('info');
            $table->boolean('notif_is_read')->default(false);
            $table->timestamp('notif_created_at')->nullable();
            $table->timestamp('notif_updated_at')->nullable();

            $table->foreign('notif_user_code')
                ->references('user_code')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
