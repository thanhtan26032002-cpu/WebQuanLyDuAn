<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->string('activity_code', 50)->primary();
            $table->string('activity_user_code', 50);
            $table->string('activity_action');
            $table->string('activity_target_type');
            $table->string('activity_target_code', 50);
            $table->text('activity_detail')->nullable();
            $table->timestamp('activity_created_at')->nullable();
            $table->timestamp('activity_updated_at')->nullable();

            $table->foreign('activity_user_code')
                ->references('user_code')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
