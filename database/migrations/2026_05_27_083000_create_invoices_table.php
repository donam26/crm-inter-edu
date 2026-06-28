<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            $table->foreignId('deal_id')
                ->constrained('deals')
                ->cascadeOnDelete();

            // Audit: ai tạo, ai phát hành, ai huỷ.
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('issued_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('voided_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('code')->unique();

            // Snapshot số tiền tại thời điểm phát hành (không thay đổi
            // theo deal sau khi đã issued).
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);

            // Tổng đã thu = sum(payments.amount), được Service đồng bộ.
            $table->unsignedBigInteger('paid_amount')->default(0);

            $table->string('status')->default('draft');

            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['deal_id']);
            $table->index(['issued_at']);
            $table->index(['due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
