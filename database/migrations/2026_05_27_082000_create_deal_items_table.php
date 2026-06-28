<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_items', function (Blueprint $table) {
            $table->id();

            // Denormalize branch_id từ Deal cha (để BranchScope hoạt động + index nhanh).
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            $table->foreignId('deal_id')
                ->constrained('deals')
                ->cascadeOnDelete();

            // Tham chiếu catalog. Cho phép null (line free-text), restrictOnDelete để
            // không xoá Product đang dùng.
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->restrictOnDelete();

            // Snapshot tên sản phẩm tại thời điểm thêm line (để giữ nguyên
            // khi catalog đổi tên về sau).
            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);

            // Discount per-line (VND, không phải %).
            $table->unsignedBigInteger('discount_amount')->default(0);

            // Tax rate per-line: phần trăm (0..100), cho phép số nguyên (8, 10).
            $table->unsignedTinyInteger('tax_rate')->default(0);

            // Subtotal trước thuế = qty * unit_price - discount.
            $table->unsignedBigInteger('line_subtotal')->default(0);
            // Tax amount = round(line_subtotal * tax_rate / 100).
            $table->unsignedBigInteger('line_tax_amount')->default(0);
            // Tổng line đã có thuế = line_subtotal + line_tax_amount.
            $table->unsignedBigInteger('line_total')->default(0);

            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['deal_id', 'position']);
            $table->index(['branch_id']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_items');
    }
};
