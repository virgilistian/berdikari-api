<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds cost and purchase pricing alongside the existing selling `price`.
     * - `purchase_price`: harga beli (what we pay the supplier), used for stock valuation.
     * - `cost_price`: harga pokok / HPP (cost of goods sold), used for profit calculation.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('purchase_price', 15, 2)->default(0)->after('price')
                ->comment('Harga beli dari pemasok (untuk valuasi stok)');
            $table->decimal('cost_price', 15, 2)->default(0)->after('purchase_price')
                ->comment('Harga pokok / HPP (untuk hitung laba)');
            $table->boolean('is_active')->default(true)->after('cost_price')
                ->comment('Produk aktif dijual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'cost_price', 'is_active']);
        });
    }
};
