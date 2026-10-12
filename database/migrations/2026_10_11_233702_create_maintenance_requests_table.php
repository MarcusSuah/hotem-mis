<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Repair work. When a request blocks its room, the linked room block takes
     * the room out of sale for the stated dates; maintenance and reservation
     * statuses stay independent.
     */
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->foreignId('room_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('location')->nullable();
            $table->string('category', 32);
            $table->string('priority', 16)->default('normal');
            $table->string('status', 16)->default('open');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->char('currency', 3);
            $table->bigInteger('estimated_cost_minor')->nullable();
            $table->bigInteger('actual_cost_minor')->nullable();
            $table->foreignId('room_block_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('maintenance_schedule_id')->nullable()->index();
            $table->foreignId('housekeeping_task_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['assigned_to', 'status']);
            $table->index(['category', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};
