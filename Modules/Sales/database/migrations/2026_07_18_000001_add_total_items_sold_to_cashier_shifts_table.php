<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the total quantity of items sold during a shift, computed alongside
     * transaction_count/total_sales when a shift is closed (see CashierShiftService).
     */
    public function up(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->integer('total_items_sold')->default(0)->after('transaction_count')
                ->comment('Total kuantitas item terjual selama shift');
        });
    }

    public function down(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->dropColumn('total_items_sold');
        });
    }
};
