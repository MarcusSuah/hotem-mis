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
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->foreignId('additional_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('customer_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->char('currency', 3);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('total_minor');
            $table->timestamp('scheduled_for')->nullable();
            $table->string('status', 16)->default('requested');
            $table->string('settlement', 16)->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->string('payment_reference', 128)->nullable();
            $table->foreignId('folio_charge_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
