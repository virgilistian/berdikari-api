<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per business + business type (e.g. a business running both a
     * restaurant and a swimming pool holds two profiles, each with its own
     * NPWPD since Bapenda registers them separately).
     */
    public function up(): void
    {
        Schema::create('tax_business_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->string('business_type')->comment('restaurant | swimming_pool | ...');
            $table->string('npwpd')->nullable();
            $table->string('company_name')->nullable();
            $table->text('company_address')->nullable();
            $table->string('owner_name')->nullable();
            $table->json('config_overrides')->nullable()->comment('merged over config(tax.<type>) defaults');
            $table->timestamps();

            $table->unique(['business_id', 'business_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_business_profiles');
    }
};
