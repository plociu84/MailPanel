<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fail2ban_ip_stats', function (Blueprint $table) {
            $table->boolean('fortigate_banned')->default(false)->after('currently_banned');
            $table->timestamp('fortigate_banned_at')->nullable()->after('fortigate_banned');
            $table->index('fortigate_banned');
        });
    }

    public function down(): void
    {
        Schema::table('fail2ban_ip_stats', function (Blueprint $table) {
            $table->dropIndex(['fortigate_banned']);
            $table->dropColumn(['fortigate_banned', 'fortigate_banned_at']);
        });
    }
};
