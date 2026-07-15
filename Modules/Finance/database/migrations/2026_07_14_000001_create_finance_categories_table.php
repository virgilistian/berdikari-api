<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Manageable pemasukan/pengeluaran categories, scoped per business.
     * `finance_entries.category` stays a free-text column for backward
     * compatibility; this table lets users curate the suggested options.
     */
    public function up(): void
    {
        Schema::create('finance_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->string('name');
            $table->string('type')->comment('income | expense');
            $table->timestamps();

            $table->unique(['business_id', 'type', 'name']);
            $table->index(['business_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_categories');
    }
};
