<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admins')) {
            return;
        }

        if (Schema::hasColumn('admins', 'remember_token')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('admins')) {
            return;
        }

        if (!Schema::hasColumn('admins', 'remember_token')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};