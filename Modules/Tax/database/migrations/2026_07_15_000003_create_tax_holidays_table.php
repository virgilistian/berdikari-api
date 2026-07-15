<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indonesian national holidays, admin-editable without a code deploy.
     * Backs the default DatabaseHolidayProvider (see HolidayProviderInterface).
     */
    public function up(): void
    {
        Schema::create('tax_holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_holidays');
    }
};
