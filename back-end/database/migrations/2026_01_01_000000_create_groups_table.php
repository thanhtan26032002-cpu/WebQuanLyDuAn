<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->string('group_code', 50)->primary();
            $table->string('group_name');
            $table->text('group_description')->nullable();
            $table->string('group_icon', 20)->nullable();
            $table->string('group_color', 30)->default('violet');
            $table->json('group_member_ids')->nullable();
            $table->timestamp('group_created_at')->nullable();
            $table->timestamp('group_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
