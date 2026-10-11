<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Blocks take a room out of sale for a date range (end_date exclusive):
     * maintenance, out of service, or an administrative hold.
     */
    public function up(): void
    {
        Schema::create('room_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 500);
            $table->unsignedBigInteger('maintenance_request_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['room_id', 'released_at', 'start_date', 'end_date']);
            $table->index(['type', 'start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_blocks');
    }
};
