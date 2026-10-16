<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Los martwej trasy /mail-users-json: usunięta.
 *
 * Lista użytkowników poczty jest renderowana serwerowo (MailUserController@index),
 * więc JSON-owe API nie miało żadnego konsumenta — ani w widokach/JS, ani w testach.
 * Sąsiednia /system-users-json zostaje, bo realnie pobiera ją widok grup
 * (fetch('/system-users-json') w groups/index.blade.php).
 */
class MailUserJsonEndpointTest extends TestCase
{
    public function test_legacy_mail_users_json_endpoint_is_gone(): void
    {
        $this->get('/mail-users-json')->assertNotFound();
    }

    public function test_system_users_json_endpoint_is_still_registered(): void
    {
        $this->assertContains(
            'system-users-json',
            array_map(static fn ($route): string => $route->uri(), Route::getRoutes()->getRoutes()),
        );
    }
}
