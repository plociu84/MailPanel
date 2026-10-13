<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fail2ban_jails', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('currently_banned')->default(0);
            $table->integer('bantime')->nullable();
            $table->integer('findtime')->nullable();
            $table->integer('maxretry')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fail2ban_jails');
    }
};
