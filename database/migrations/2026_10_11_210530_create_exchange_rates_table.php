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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->timestamp('effective_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->foreign('base_currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('quote_currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['base_currency', 'quote_currency', 'effective_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
