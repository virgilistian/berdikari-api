<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Centralized in-app notification store.
     * One row per notification, scoped to a business and optionally a user.
     * role_target allows role-broadcast (null user_id = broadcast to all matching role).
     */
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('user_id')->nullable()->comment('null = broadcast ke role_target');
            $table->string('role_target')->nullable()->comment('Nama role penerima jika broadcast');
            $table->string('type')->comment('leave_submitted|leave_approved|leave_rejected|attendance_exception|shift_reminder|stock_reminder|approval_required');
            $table->string('title');
            $table->text('body');
            $table->json('meta')->nullable()->comment('Data tambahan (id referensi, dll)');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['business_id', 'role_target', 'read_at']);
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
