<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds operational-expense and stock-reconciliation summary fields, computed
     * when a shift is closed (see CashierShiftService::close()).
     */
    public function up(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->decimal('total_expenses', 15, 2)->default(0)->after('total_sales')
                ->comment('Total pengeluaran operasional selama shift');
            $table->decimal('net_income', 15, 2)->nullable()->after('total_expenses')
                ->comment('Penjualan dikurangi pengeluaran');
            $table->json('stock_summary')->nullable()->after('payment_breakdown')
                ->comment('Rekap stok harian per produk saat shift ditutup: opening/sold/adjustment/closing');
        });
    }

    public function down(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->dropColumn(['total_expenses', 'net_income', 'stock_summary']);
        });
    }
};
