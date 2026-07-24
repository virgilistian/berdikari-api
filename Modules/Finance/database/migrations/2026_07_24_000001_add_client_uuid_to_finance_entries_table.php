<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Idempotency key for offline-first Finance entries (mirrors sale_orders):
     * the client generates a UUID per manual entry; re-submitting the same
     * entry (e.g. outbox retry after reconnect) returns the existing row
     * instead of creating a duplicate.
     */
    public function up(): void
    {
        Schema::table('finance_entries', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('id')
                ->comment('Idempotency key dari perangkat (offline sync)');
            $table->unique(['business_id', 'client_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_entries', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
