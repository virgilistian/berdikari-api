<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The realtime stock system is keyed by business_id + product_id. The
     * legacy branch_id column is not wired to any branch concept yet, so it
     * must be nullable to allow business-scoped stock records.
     */
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->uuid('branch_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->uuid('branch_id')->nullable(false)->change();
        });
    }
};
