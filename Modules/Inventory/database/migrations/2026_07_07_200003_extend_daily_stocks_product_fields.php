<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend daily_stocks with enriched product fields for display
     * and add adjustment support.
     */
    public function up(): void
    {
        Schema::table('daily_stocks', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->nullable()->after('product_name')
                ->comment('Harga jual produk saat hari dibuka');
            $table->string('image_url')->nullable()->after('price')
                ->comment('URL gambar produk');
            $table->integer('adjustment_qty')->default(0)->after('opening_qty')
                ->comment('Penyesuaian stok manual (positif=tambah, negatif=kurang)');
            $table->text('adjustment_note')->nullable()->after('adjustment_qty')
                ->comment('Alasan penyesuaian');
        });
    }

    public function down(): void
    {
        Schema::table('daily_stocks', function (Blueprint $table) {
            $table->dropColumn(['price', 'image_url', 'adjustment_qty', 'adjustment_note']);
        });
    }
};
