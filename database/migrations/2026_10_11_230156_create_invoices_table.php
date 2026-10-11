<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Invoices are immutable snapshots of a folio at issue time. A correction
     * voids the invoice (keeping it) and issues a new one.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('folio_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->string('status', 16);
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('service_charge_minor');
            $table->bigInteger('tax_minor');
            $table->bigInteger('discount_minor');
            $table->bigInteger('total_minor');
            $table->bigInteger('paid_minor');
            $table->bigInteger('balance_minor');
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->char('base_currency', 3);
            $table->json('billing_details');
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->foreignId('replaces_invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'issued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
