<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per calendar day of a tax report. Shared schema across all business
     * types: `ticket_qty`/`ticket_price` are pool-only (nullable for restaurant),
     * and `extra` is reserved json for a future business type's unique fields, so
     * a new type rarely needs a migration of its own.
     */
    public function up(): void
    {
        Schema::create('tax_report_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tax_report_id');
            $table->unsignedTinyInteger('day_number');
            $table->string('weekday_name');
            $table->boolean('is_weekend')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->string('holiday_name')->nullable();
            $table->unsignedInteger('ticket_qty')->nullable();
            $table->decimal('ticket_price', 12, 2)->nullable();
            $table->json('extra')->nullable();
            $table->decimal('sales', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->boolean('is_manually_edited')->default(false);
            $table->timestamps();

            $table->foreign('tax_report_id')->references('id')->on('tax_reports')->cascadeOnDelete();
            $table->unique(['tax_report_id', 'day_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_report_entries');
    }
};
