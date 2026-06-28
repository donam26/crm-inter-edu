<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')
                ->constrained('leads')
                ->cascadeOnDelete();
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('type');
            $table->string('subject');
            $table->text('content')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['lead_id', 'happened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
