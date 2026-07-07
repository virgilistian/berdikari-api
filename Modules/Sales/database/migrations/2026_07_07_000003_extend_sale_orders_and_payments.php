<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends sale orders to support the full POS lifecycle:
     * suspended/saved orders, pay-later (unpaid), partial payments,
     * order numbering, receipts, cancellation and refunds.
     */
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->string('order_no')->nullable()->after('business_id')
                ->comment('Nomor nota untuk struk');
            // status lifecycle: open (disimpan/ditahan), completed, cancelled, refunded
            $table->string('payment_status')->default('paid')->after('status')
                ->comment('unpaid | partial | paid');
            $table->decimal('paid_amount', 15, 2)->default(0)->after('total_amount')
                ->comment('Total pembayaran diterima');
            $table->decimal('change_amount', 15, 2)->default(0)->after('paid_amount')
                ->comment('Kembalian tunai');
            $table->string('customer_name')->nullable()->after('change_amount');
            $table->string('note')->nullable()->after('customer_name');
            $table->timestamp('completed_at')->nullable()->after('note');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            $table->timestamp('refunded_at')->nullable()->after('cancelled_at');

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'payment_status']);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('sale_order_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('method')->default('cash')->comment('cash | qris | transfer | other');
            $table->string('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'sale_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_payments');

        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'status']);
            $table->dropIndex(['business_id', 'payment_status']);
            $table->dropColumn([
                'order_no', 'payment_status', 'paid_amount', 'change_amount',
                'customer_name', 'note', 'completed_at', 'cancelled_at', 'refunded_at',
            ]);
        });
    }
};
