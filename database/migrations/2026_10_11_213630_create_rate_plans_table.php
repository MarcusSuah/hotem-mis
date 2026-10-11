<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rate plans apply to every room type as a percentage adjustment of the
     * nightly room rate (e.g. -10 for a non-refundable saver).
     */
    public function up(): void
    {
        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->text('description')->nullable();
            $table->foreignId('cancellation_policy_id')->constrained()->restrictOnDelete();
            $table->boolean('is_refundable')->default(true);
            $table->decimal('adjustment_percent', 5, 2)->default(0);
            $table->boolean('includes_breakfast')->default(false);
            $table->unsignedTinyInteger('min_nights')->default(1);
            $table->decimal('deposit_percent', 5, 2)->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_plans');
    }
};
