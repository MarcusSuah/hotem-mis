<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A room stores only its operational and housekeeping condition. Whether it
     * is reserved or occupied is derived from reservation nights for a date.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->string('number', 16);
            $table->string('slug')->unique();
            $table->smallInteger('floor');
            $table->string('building', 64)->nullable();
            $table->text('description')->nullable();
            $table->string('operational_status', 32)->default('in_service');
            $table->string('housekeeping_status', 32)->default('clean');
            $table->boolean('is_accessible')->default(false);
            $table->string('accessibility_notes', 500)->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['property_id', 'number']);
            $table->index(['room_type_id', 'operational_status']);
            $table->index(['housekeeping_status']);
            $table->index(['building', 'floor']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
