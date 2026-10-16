<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Liczniki w zakładkach fail2ban pokazują stan po retencji (`fail2ban:prune`),
 * a nie sumę od początku istnienia bazy. Etykiety muszą to mówić wprost,
 * a wartość okna pochodzi z configu `fail2ban.retention_days`.
 */
class Fail2banRetentionLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_headers_and_note_declare_the_retention_window(): void
    {
        config(['fail2ban.retention_days' => 45]);

        $response = $this->actingAs($this->admin(), 'admin')->get('/fail2ban');

        $response->assertOk();

        // Nagłówki kolumn z licznikami per IP (oparte o total_bans)
        $response->assertSee('Razy <span class="text-muted fw-normal">(45 dni)</span>', false);
        $response->assertSee('Razy banowany <span class="text-muted fw-normal">(45 dni)</span>', false);
        $response->assertSee('Pierwszy ban w oknie', false);

        // Filtr min. liczby banów oraz stan zakładki „Lista IP”
        // (sama lista IP leci po `ip::inet`, więc wymaga Postgresa — tu tylko puste stany)
        $response->assertSee('Min. liczba banów <span class="fw-normal">(okno 45 dni)</span>', false);
        $response->assertSee('Podaj minimalną liczbę banów w oknie ostatnich 45 dni', false);

        // Nota pod kartami statystyk — wyjaśnia sens retencji
        $response->assertSee('liczą wyłącznie bany z ostatnich', false);
        $response->assertSee('<strong>45 dni</strong>', false);
        $response->assertSee('fail2ban:prune', false);

        // Pusty stan zakładki „Historia” i modal historii IP
        $response->assertSee('Brak banów w oknie ostatnich 45 dni.', false);
        $response->assertSee('Lista obejmuje bany z okna retencji (45 dni)', false);

        // Okno trafia też do JS (podsumowanie w modalu)
        $response->assertSee('retentionDays: 45', false);
    }

    public function test_labels_follow_config_changes(): void
    {
        config(['fail2ban.retention_days' => 7]);

        $this->actingAs($this->admin(), 'admin')
            ->get('/fail2ban')
            ->assertOk()
            ->assertSee('Razy <span class="text-muted fw-normal">(7 dni)</span>', false)
            ->assertSee('retentionDays: 7', false);
    }

    private function admin(): Admin
    {
        return Admin::create([
            'username' => 'tester',
            'password' => bcrypt('secret'),
        ]);
    }
}
