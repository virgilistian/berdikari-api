<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('user_id')->nullable()
                ->comment('Akun pengguna terkait (nullable: karyawan tanpa akses aplikasi)');
            $table->string('name');
            $table->string('position')->nullable()->comment('Jabatan, mis. Kasir / Staf Dapur');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->date('hired_at')->nullable()->comment('Tanggal mulai bekerja');
            $table->string('status')->default('active')->comment('active | inactive');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
