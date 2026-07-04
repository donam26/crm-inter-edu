<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Cờ chống gửi trùng cho scheduler tasks:dispatch-reminders.
            $table->timestamp('reminded_at')->nullable()->after('remind_at');
            $table->timestamp('overdue_notified_at')->nullable()->after('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['reminded_at', 'overdue_notified_at']);
        });
    }
};
