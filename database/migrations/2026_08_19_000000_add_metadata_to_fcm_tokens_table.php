<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fcm_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('fcm_tokens', 'platform')) {
                $table->string('platform')->default('web')->after('token');
            }

            if (!Schema::hasColumn('fcm_tokens', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fcm_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('fcm_tokens', 'last_used_at')) {
                $table->dropColumn('last_used_at');
            }

            if (Schema::hasColumn('fcm_tokens', 'platform')) {
                $table->dropColumn('platform');
            }
        });
    }
};
