<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            // Người tổ chức / chủ trì lịch hẹn (bắt buộc).
            $table->foreignId('organizer_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Người tạo lịch (audit).
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            // Có thể gắn với Lead (lịch hẹn khách hàng) hoặc đứng độc lập
            // (họp nội bộ).
            $table->foreignId('lead_id')
                ->nullable()
                ->constrained('leads')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('type')->default('meeting');
            $table->string('status')->default('scheduled');

            $table->string('location')->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('online_url', 1024)->nullable();

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('all_day')->default(false);

            $table->timestamp('reminder_at')->nullable();

            $table->timestamps();

            // Index hỗ trợ truy vấn lịch theo branch + thời gian, theo
            // organizer (lịch của tôi), theo Lead (timeline lead).
            $table->index(['branch_id', 'starts_at']);
            $table->index(['organizer_user_id', 'starts_at']);
            $table->index(['lead_id']);
            $table->index(['status', 'starts_at']);
        });

        // Pivot mời nhiều attendee (user nội bộ).
        Schema::create('event_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Trạng thái xác nhận của attendee.
            $table->string('response')->default('pending'); // pending | accepted | declined | tentative
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            // Một user chỉ xuất hiện 1 lần cho cùng 1 event.
            $table->unique(['event_id', 'user_id']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_user');
        Schema::dropIfExists('events');
    }
};
