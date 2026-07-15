<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cashier shift management.
     * One active shift per user (cashier) per business at a time.
     * All sale orders created while a shift is open reference that shift.
     */
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('user_id')->comment('Kasir yang membuka shift');
            $table->string('status')->default('open')->comment('open | closed');
            $table->decimal('opening_cash', 15, 2)->default(0)->comment('Uang kas awal saat buka shift');
            $table->decimal('closing_cash', 15, 2)->nullable()->comment('Uang kas aktual saat tutup shift');
            $table->decimal('expected_cash', 15, 2)->nullable()->comment('Kas yang seharusnya ada (opening + cash sales)');
            $table->decimal('cash_difference', 15, 2)->nullable()->comment('Selisih (actual - expected)');
            $table->integer('transaction_count')->default(0);
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->json('payment_breakdown')->nullable()->comment('Rekapitulasi per metode pembayaran');
            $table->text('closing_note')->nullable()->comment('Catatan kasir saat tutup shift');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'user_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        // Link sale orders to their shift
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->uuid('cashier_shift_id')->nullable()->after('user_id')
                ->comment('Shift kasir saat transaksi dibuat');
            $table->index('cashier_shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropIndex(['cashier_shift_id']);
            $table->dropColumn('cashier_shift_id');
        });

        Schema::dropIfExists('cashier_shifts');
    }
};
