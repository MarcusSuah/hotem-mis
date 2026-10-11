<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('featured_image_path')->nullable();
            $table->string('featured_image_alt')->nullable();

            $table->unsignedTinyInteger('max_occupancy');
            $table->unsignedTinyInteger('max_adults');
            $table->unsignedTinyInteger('max_children')->default(0);
            $table->unsignedTinyInteger('included_adults')->default(2);
            $table->unsignedTinyInteger('included_children')->default(0);
            $table->string('bed_type', 64);
            $table->unsignedTinyInteger('bed_count')->default(1);
            $table->decimal('size_sqm', 6, 1)->nullable();
            $table->string('bathroom_details')->nullable();

            // Rates are integer minor units of the hotel's base currency.
            $table->char('currency', 3);
            $table->unsignedBigInteger('base_rate_minor');
            $table->unsignedBigInteger('weekend_rate_minor')->nullable();
            $table->unsignedBigInteger('extra_adult_minor')->default(0);
            $table->unsignedBigInteger('extra_child_minor')->default(0);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['is_active', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
