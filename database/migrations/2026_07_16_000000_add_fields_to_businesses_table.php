<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('type')->nullable()->after('name');
            $table->string('code')->nullable()->after('type');
            $table->string('address')->nullable()->after('code');
            $table->string('phone')->nullable()->after('address');
            $table->string('logo_disk')->nullable()->after('phone');
            $table->string('logo_path')->nullable()->after('logo_disk');
            $table->string('status')->default('active')->after('logo_path');
        });

        // Backfill a unique code for existing businesses (name slug + short id
        // suffix to guarantee uniqueness). Stays nullable at the DB level —
        // some fixtures/seeders insert business rows without a code — but the
        // API layer (BusinessController@store/updateOne) requires it.
        foreach (DB::table('businesses')->whereNull('code')->get(['id', 'name']) as $business) {
            DB::table('businesses')->where('id', $business->id)->update([
                'code' => Str::slug($business->name) . '-' . substr($business->id, 0, 8),
            ]);
        }

        Schema::table('businesses', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->change();
        });

        Schema::create('business_user', function (Blueprint $table) {
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
        });

        // Backfill membership: every existing user keeps access to the single
        // business they already belong to, so nothing changes for today's users.
        foreach (DB::table('users')->whereNotNull('business_id')->get(['id', 'business_id']) as $user) {
            DB::table('business_user')->insertOrIgnore([
                'business_id' => $user->business_id,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_user');

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['type', 'code', 'address', 'phone', 'logo_disk', 'logo_path', 'status']);
        });
    }
};
