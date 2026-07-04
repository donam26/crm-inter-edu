<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // M4: index hỗ trợ scheduler quét nhắc hạn (khỏi full-scan mỗi 15').
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['reminder_enabled', 'remind_at', 'reminded_at'], 'tasks_reminder_scan_idx');
        });

        // M5: chống "burst" thông báo ở lần chạy scheduler ĐẦU TIÊN sau khi
        // triển khai — coi như đã nhắc / đã báo quá hạn cho task hiện hữu, để chỉ
        // các mốc phát sinh SAU deploy mới bắn.
        $open = ['pending', 'in_progress'];

        DB::table('tasks')
            ->whereIn('status', $open)
            ->where('reminder_enabled', true)
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', now())
            ->whereNull('reminded_at')
            ->update(['reminded_at' => now()]);

        DB::table('tasks')
            ->whereIn('status', $open)
            ->where('due_at', '<', now())
            ->whereNull('overdue_notified_at')
            ->update(['overdue_notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_reminder_scan_idx');
        });
    }
};
