<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `amount_minor`/`currency` is what the guest actually paid. `applied_minor`
     * is that amount in the folio currency, using the recorded exchange rate.
     * No card numbers or authentication data are ever stored.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('folio_id')->constrained()->restrictOnDelete();
            $table->string('method', 32);
            $table->string('status', 16);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->bigInteger('applied_minor');
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->string('external_reference', 128)->nullable();
            $table->string('gateway', 32)->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['status', 'received_at']);
            $table->index(['method', 'received_at']);
            $table->index(['gateway', 'external_reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
