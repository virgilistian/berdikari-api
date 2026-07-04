<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->date('date');
            $table->uuid('product_id');
            $table->string('product_name');
            $table->integer('opening_qty')->default(0);
            $table->integer('sold_qty')->default(0);
            $table->integer('closing_qty')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();

            $table->unique(['business_id', 'date', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stocks');
    }
};
