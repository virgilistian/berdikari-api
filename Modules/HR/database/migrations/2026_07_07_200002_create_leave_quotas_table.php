<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leave quota tracking per employee per year.
     * Tracks annual leave allowance, usage, and carryover.
     */
    public function up(): void
    {
        Schema::create('leave_quotas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('employee_id');
            $table->integer('year');
            $table->string('type')->default('annual')->comment('annual | sick | other');
            $table->integer('quota_days')->default(12)->comment('Jatah cuti (hari)');
            $table->integer('used_days')->default(0)->comment('Hari cuti terpakai');
            $table->integer('pending_days')->default(0)->comment('Hari cuti menunggu persetujuan');
            $table->integer('carryover_days')->default(0)->comment('Sisa cuti dari tahun lalu');
            $table->timestamp('expires_at')->nullable()->comment('Masa berlaku sisa cuti');
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'type'], 'leave_quotas_employee_year_type_unique');
            $table->index(['business_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_quotas');
    }
};
