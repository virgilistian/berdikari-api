<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Idempotency key for offline-first POS: the client generates a UUID per
     * transaction; re-submitting the same transaction (e.g. offline queue
     * retry after reconnect) returns the existing order instead of creating
     * a duplicate.
     */
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('order_no')
                ->comment('Idempotency key dari perangkat kasir (offline sync)');
            $table->unique(['business_id', 'client_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
