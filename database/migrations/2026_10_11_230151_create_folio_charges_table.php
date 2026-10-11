<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only ledger lines in the folio currency. Corrections are posted as
     * a reversing line pointing at the original (reversal_of_id), never edits.
     */
    public function up(): void
    {
        Schema::create('folio_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folio_id')->constrained()->restrictOnDelete();
            $table->string('category', 32);
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('unit_amount_minor');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->date('service_date');
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('folio_charges')->restrictOnDelete();
            $table->nullableMorphs('source');
            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['folio_id', 'service_date']);
            $table->index(['category', 'service_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folio_charges');
    }
};
