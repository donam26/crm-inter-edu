<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            // Audit: ai tạo, ai xác nhận (= confirmed_at not null khi xác nhận).
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('code')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('method')->default('bank_transfer');

            $table->date('paid_at');
            $table->timestamp('confirmed_at')->nullable();

            $table->string('reference_no')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['branch_id', 'paid_at']);
            $table->index(['invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
