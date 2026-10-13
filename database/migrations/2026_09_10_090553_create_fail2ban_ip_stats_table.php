<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fail2ban_ip_stats', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->integer('total_bans')->default(0);
            $table->integer('total_unique_jails')->default(0);
            $table->timestamp('first_banned_at')->nullable();
            $table->timestamp('last_banned_at')->nullable()->index();
            $table->timestamp('last_unbanned_at')->nullable();
            $table->boolean('currently_banned')->default(false)->index();
            $table->jsonb('jails')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fail2ban_ip_stats');
    }
};
