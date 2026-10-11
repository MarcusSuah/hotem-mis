<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per room per night held by an active allocation. The unique
     * (room_id, night_date) index is the database-level guarantee that a room
     * can never be sold twice for the same night, even under concurrency.
     * Rows are removed when an allocation is released.
     */
    public function up(): void
    {
        Schema::create('reservation_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->date('night_date');
            $table->bigInteger('rate_minor');
            $table->bigInteger('base_rate_minor');
            $table->timestamps();

            $table->unique(['room_id', 'night_date']);
            $table->index(['night_date', 'room_type_id']);
            $table->index('reservation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_nights');
    }
};
