<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Amounts are integer minor units in the transaction `currency`. When the
     * transaction currency differs from the base currency, `exchange_rate` holds
     * the snapshot (units of `currency` per one unit of `base_currency`) used to
     * price the stay; `base_total_minor` is the agreed total in base currency.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('booked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('source', 32);
            $table->string('status', 32);
            $table->string('payment_status', 32)->default('unpaid');

            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedSmallInteger('nights');
            $table->unsignedTinyInteger('adults');
            $table->unsignedTinyInteger('children')->default(0);

            $table->string('contact_name');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->text('special_requests')->nullable();

            $table->char('currency', 3);
            $table->char('base_currency', 3);
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->bigInteger('room_total_minor');
            $table->bigInteger('extras_total_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('service_charge_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->bigInteger('base_total_minor');
            $table->bigInteger('deposit_due_minor')->default(0);
            $table->bigInteger('amount_paid_minor')->default(0);
            $table->bigInteger('cancellation_fee_minor')->default(0);
            $table->boolean('is_complimentary')->default(false);

            $table->json('cancellation_policy_snapshot');
            $table->string('cancellation_reason', 500)->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('no_show_at')->nullable();
            $table->timestamps();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('base_currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['status', 'check_in_date']);
            $table->index(['status', 'check_out_date']);
            $table->index(['check_in_date', 'check_out_date']);
            $table->index(['status', 'expires_at']);
            $table->index(['source', 'created_at']);
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
