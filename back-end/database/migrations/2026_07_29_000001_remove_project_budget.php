<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('projects', 'project_budget')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('project_budget');
            });
        }
    }

    public function down(): void
    {
        // Ngân sách đã được loại bỏ theo yêu cầu nghiệp vụ, không khôi phục khi rollback.
    }
};
