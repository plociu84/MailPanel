<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fail2ban_bans', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->index();
            $table->string('jail', 64)->index();
            $table->timestamp('banned_at')->index();
            $table->timestamp('unbanned_at')->nullable();
            $table->integer('ban_duration')->nullable();
            $table->string('unban_reason', 32)->nullable();
            $table->timestamps();

            $table->unique(['ip', 'jail', 'banned_at'], 'fail2ban_bans_unique');
            $table->index(['ip', 'jail', 'unbanned_at'], 'fail2ban_bans_open_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fail2ban_bans');
    }
};
