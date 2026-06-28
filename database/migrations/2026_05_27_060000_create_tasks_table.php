<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            // Task có thể gắn với một Lead (optional) hoặc đứng độc lập.
            $table->foreignId('lead_id')
                ->nullable()
                ->constrained('leads')
                ->cascadeOnDelete();

            // Người được phân công thực hiện task.
            $table->foreignId('assigned_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Người tạo task (audit).
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            // Người đánh dấu hoàn thành (nullable cho task chưa hoàn thành).
            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('other');
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending');

            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();

            $table->boolean('reminder_enabled')->default(false);
            $table->timestamp('remind_at')->nullable();

            $table->timestamps();

            // Index hỗ trợ filter danh sách: theo branch + assignee, theo
            // status + due_at (overdue/upcoming), theo lead.
            $table->index(['branch_id', 'assigned_user_id']);
            $table->index(['branch_id', 'status', 'due_at']);
            $table->index(['lead_id']);
            $table->index(['due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
