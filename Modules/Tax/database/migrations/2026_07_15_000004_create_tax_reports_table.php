<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Monthly tax report header. One row per business + business type + month/year
     * (unique constraint below) — generation upserts this row directly, so the
     * "preview" the user edits, the "save", and "reprint history" are the same row.
     */
    public function up(): void
    {
        Schema::create('tax_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->string('business_type');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->string('status')->default('draft')->comment('draft | final');
            $table->unsignedTinyInteger('holiday_count_in_month')->default(0);
            $table->decimal('monthly_cap', 14, 2);
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->decimal('total_tax', 14, 2)->default(0);
            $table->boolean('was_normalized')->default(false);
            $table->json('config_snapshot')->nullable();
            $table->uuid('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('last_printed_at')->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'business_type', 'period_month', 'period_year'], 'tax_reports_period_unique');
            $table->index(['business_id', 'business_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_reports');
    }
};
