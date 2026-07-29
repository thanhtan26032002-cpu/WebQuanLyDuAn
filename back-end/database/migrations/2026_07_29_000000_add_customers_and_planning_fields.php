<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->string('customer_code', 50)->primary();
            $table->string('customer_name');
            $table->string('customer_company')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->text('customer_address')->nullable();
            $table->text('customer_notes')->nullable();
            $table->timestamp('customer_created_at')->nullable();
            $table->timestamp('customer_updated_at')->nullable();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('project_customer_code', 50)->nullable()->after('project_code');
            $table->string('project_manager_code', 50)->nullable()->after('project_created_by');
            $table->foreign('project_customer_code')->references('customer_code')->on('customers')->nullOnDelete();
            $table->foreign('project_manager_code')->references('member_code')->on('members')->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->date('task_start_date')->nullable()->after('task_priority');
            $table->string('task_type', 30)->default('task')->after('task_description');
            $table->decimal('task_estimated_hours', 8, 2)->nullable()->after('task_progress');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['task_start_date', 'task_type', 'task_estimated_hours']);
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['project_customer_code']);
            $table->dropForeign(['project_manager_code']);
            $table->dropColumn(['project_customer_code', 'project_manager_code']);
        });
        Schema::dropIfExists('customers');
    }
};
