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
        Schema::table('gallery_images', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_published');
            $table->index(['is_published', 'is_featured', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gallery_images', function (Blueprint $table) {
            $table->dropIndex(['is_published', 'is_featured', 'display_order']);
            $table->dropColumn('is_featured');
        });
    }
};
