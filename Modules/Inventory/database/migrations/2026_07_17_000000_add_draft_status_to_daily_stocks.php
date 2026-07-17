<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow a 'draft' status for daily stock prepared ahead for a future date.
     *
     * The original column is a Postgres CHECK constraint (Laravel's enum() on
     * pgsql), which supports ALTER ... DROP/ADD CONSTRAINT by name. SQLite
     * (used by the test suite) has no equivalent ALTER syntax for CHECK
     * constraints, so there the column is swapped for a plain string instead —
     * `status` values are only ever written by DailyStockService, never raw
     * user input, so losing the DB-level check there is safe.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('daily_stocks', function (Blueprint $table) {
                $table->string('status_new')->default('open');
            });
            DB::statement('UPDATE daily_stocks SET status_new = status');
            Schema::table('daily_stocks', function (Blueprint $table) {
                $table->dropColumn('status');
            });
            Schema::table('daily_stocks', function (Blueprint $table) {
                $table->renameColumn('status_new', 'status');
            });

            return;
        }

        DB::statement('ALTER TABLE daily_stocks DROP CONSTRAINT daily_stocks_status_check');
        DB::statement("ALTER TABLE daily_stocks ADD CONSTRAINT daily_stocks_status_check CHECK (status IN ('draft', 'open', 'closed'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Values already narrowed at the application layer; nothing to revert.
            return;
        }

        DB::statement('ALTER TABLE daily_stocks DROP CONSTRAINT daily_stocks_status_check');
        DB::statement("ALTER TABLE daily_stocks ADD CONSTRAINT daily_stocks_status_check CHECK (status IN ('open', 'closed'))");
    }
};
