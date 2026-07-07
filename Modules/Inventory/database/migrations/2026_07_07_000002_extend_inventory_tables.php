<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends the realtime stock tables to support tenant scoping, low-stock
     * thresholds, stock valuation and a full audit trail of movements.
     */
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->uuid('business_id')->nullable()->after('id')
                ->comment('Bisnis pemilik stok (tenant scope)');
            $table->integer('min_stock')->default(0)->after('quantity')
                ->comment('Ambang batas stok menipis untuk notifikasi');
            $table->index(['business_id', 'product_id']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->uuid('business_id')->nullable()->after('id');
            $table->uuid('product_id')->nullable()->after('inventory_id')
                ->comment('Denormalisasi untuk riwayat per produk');
            $table->decimal('unit_cost', 15, 2)->default(0)->after('quantity')
                ->comment('Harga beli per unit saat pergerakan');
            $table->integer('balance_after')->nullable()->after('unit_cost')
                ->comment('Sisa stok setelah pergerakan ini');
            $table->string('reference_type')->nullable()->after('reason')
                ->comment('Sumber pergerakan, mis. sale_order');
            $table->uuid('reference_id')->nullable()->after('reference_type');
            $table->index(['business_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'product_id']);
            $table->dropColumn(['business_id', 'min_stock']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'product_id']);
            $table->dropColumn([
                'business_id', 'product_id', 'unit_cost',
                'balance_after', 'reference_type', 'reference_id',
            ]);
        });
    }
};
