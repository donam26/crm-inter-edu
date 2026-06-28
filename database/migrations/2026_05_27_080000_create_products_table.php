<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Catalog tách theo branch (mỗi chi nhánh có thể có gói riêng).
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();

            // Đơn giá tham khảo (VND, không có decimal — VND không dùng tiền lẻ).
            $table->unsignedBigInteger('unit_price')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Mã sản phẩm unique trong từng branch.
            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
