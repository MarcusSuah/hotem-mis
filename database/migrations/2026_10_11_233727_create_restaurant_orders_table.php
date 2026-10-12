<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Restaurant, bar and room-service orders. An order is either charged to an
     * in-house guest's folio (exactly once) or settled at the outlet.
     */
    public function up(): void
    {
        Schema::create('restaurant_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->string('type', 16);
            $table->string('status', 16)->default('open');
            $table->foreignId('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('table_or_room', 32)->nullable();
            $table->string('customer_name')->nullable();
            $table->char('currency', 3);
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->string('settlement', 16)->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->string('payment_reference', 128)->nullable();
            $table->foreignId('folio_charge_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_orders');
    }
};
