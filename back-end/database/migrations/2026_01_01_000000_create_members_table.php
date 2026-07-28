<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->string('member_code', 50)->primary();
            $table->string('member_name');
            $table->string('member_email')->unique();
            $table->string('member_avatar')->nullable();
            $table->string('member_role')->default('member');
            $table->string('member_phone', 50)->nullable();
            $table->string('member_department')->nullable();
            $table->date('member_join_date')->nullable();
            $table->text('member_bio')->nullable();
            $table->boolean('member_online')->default(true);
            $table->timestamp('member_created_at')->nullable();
            $table->timestamp('member_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
