<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Service charge and tax are priced per night so the folio postings for a
     * stay always add up exactly to the agreed reservation total.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('exchange_rate');
            $table->decimal('service_charge_rate', 5, 2)->default(0)->after('tax_rate');
        });

        Schema::table('reservation_nights', function (Blueprint $table) {
            $table->bigInteger('service_charge_minor')->default(0)->after('rate_minor');
            $table->bigInteger('tax_minor')->default(0)->after('service_charge_minor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservation_nights', function (Blueprint $table) {
            $table->dropColumn(['service_charge_minor', 'tax_minor']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'service_charge_rate']);
        });
    }
};
