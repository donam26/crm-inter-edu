<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            // 1 Lead = 1 Deal (one-to-one). Đặt unique trên lead_id để
            // enforce ràng buộc này ở DB level.
            $table->foreignId('lead_id')
                ->unique()
                ->constrained('leads')
                ->cascadeOnDelete();

            // Sales phụ trách deal (thường = lead.assigned_user_id).
            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Người tạo deal (audit).
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('code')->unique();
            $table->string('title');

            // Subtotal = sum(deal_items.line_total) trước thuế (VND).
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            // Tổng tiền thuế (sum line_tax_total).
            $table->unsignedBigInteger('tax_amount')->default(0);
            // Total = subtotal + tax (đã đồng bộ từ items).
            $table->unsignedBigInteger('total_amount')->default(0);

            $table->string('stage')->default('lead');

            $table->date('expected_close_date')->nullable();
            $table->date('actual_close_date')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'stage']);
            $table->index(['owner_user_id']);
            $table->index(['expected_close_date']);
            $table->index(['actual_close_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
