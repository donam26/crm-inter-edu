<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('school_name');
            $table->string('school_level');
            $table->unsignedInteger('student_size')->default(0);
            $table->string('address')->nullable();
            $table->string('status')->default('new');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
