<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add valuation_fee to shipments
        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('valuation_fee', 12, 2)->default(0)->after('shipping_cost');
        });

        // Add COD fee settings to companies
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('cod_fee_rate', 8, 4)->default(0.0150)->after('contact_phone');   // 1.5%
            $table->decimal('cod_vat_rate', 8, 4)->default(0.1200)->after('cod_fee_rate');     // 12%
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('valuation_fee');
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['cod_fee_rate', 'cod_vat_rate']);
        });
    }
};
