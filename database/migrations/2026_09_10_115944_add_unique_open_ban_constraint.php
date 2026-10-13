<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Partial unique index — pozwala na wiele zamkniętych banów,
        // ale tylko JEDEN otwarty ban dla pary (ip, jail)
        DB::statement('
            CREATE UNIQUE INDEX fail2ban_bans_one_open_per_ip_jail
            ON fail2ban_bans (ip, jail)
            WHERE unbanned_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS fail2ban_bans_one_open_per_ip_jail');
    }
};