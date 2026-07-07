<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Unified cash-flow ledger for pemasukan (income) and pengeluaran (expense).
     * POS payments are recorded here automatically as income via events.
     */
    public function up(): void
    {
        Schema::create('finance_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->string('type')->comment('income | expense');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('category')->comment('mis. Penjualan, Belanja Bahan');
            $table->text('note')->nullable();
            $table->string('source_type')->nullable()->comment('mis. sale_order, sale_order_refund, manual');
            $table->uuid('source_id')->nullable()->comment('id transaksi sumber');
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['business_id', 'type']);
            $table->index(['business_id', 'occurred_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_entries');
    }
};
