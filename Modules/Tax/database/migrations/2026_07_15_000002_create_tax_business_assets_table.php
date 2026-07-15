<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reusable per-business image assets (signature, stamp; future: logo, qr_code,
     * watermark). One row per business + type — `type` is validated against
     * config('tax.asset_types') at the controller layer, not a DB enum, so
     * registering a new asset type never requires a migration.
     */
    public function up(): void
    {
        Schema::create('tax_business_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->string('type')->comment('signature | stamp | ...');
            $table->string('disk')->default('s3');
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_business_assets');
    }
};
