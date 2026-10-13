<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rustdesk_devices', function (Blueprint $table) {
            $table->id();
            $table->string('user_name');       // Użytkownik
            $table->string('computer_number'); // Nr Komputera (np. K-012)
            $table->string('rustdesk_id');     // Nr Rustdesk (ID)
            $table->text('notes')->nullable(); // Opcjonalne uwagi
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rustdesk_devices');
    }
};