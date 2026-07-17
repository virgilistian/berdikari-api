<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguishes the Eid al-Fitr (Lebaran) holiday period from other
     * national holidays, since the weekend/holiday zero-sales validation
     * rule excludes Lebaran dates (businesses legitimately close for the
     * whole cuti bersama + libur nasional stretch).
     */
    public function up(): void
    {
        Schema::table('tax_holidays', function (Blueprint $table) {
            $table->string('type')->default('national')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('tax_holidays', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
