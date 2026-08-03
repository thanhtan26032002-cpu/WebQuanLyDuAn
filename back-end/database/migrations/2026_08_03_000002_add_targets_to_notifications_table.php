<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notif_target_type', 30)->nullable()->after('notif_type');
            $table->string('notif_target_code', 50)->nullable()->after('notif_target_type');
            $table->index(
                ['notif_user_code', 'notif_target_type', 'notif_target_code'],
                'notifications_user_target_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_target_index');
            $table->dropColumn(['notif_target_type', 'notif_target_code']);
        });
    }
};
