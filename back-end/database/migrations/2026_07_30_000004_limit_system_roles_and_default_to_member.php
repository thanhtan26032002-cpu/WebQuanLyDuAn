<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotIn('user_role', ['admin', 'project_manager', 'member'])
            ->update(['user_role' => 'member']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('user_role')->default('member')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_role')->default('admin')->change();
        });
    }
};
