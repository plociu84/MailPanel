<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Panel nie ma strony publicznej: każda trasa administracyjna siedzi za
 * middleware `admin.auth`, a niezalogowany gość trafia na ekran logowania.
 *
 * Middleware loguje cel wizyty (`url.intended`), więc po zalogowaniu admin
 * wraca na stronę, którą chciał otworzyć — to zachowanie też jest tutaj pilnowane.
 */
class GuestAccessTest extends TestCase
{
    /**
     * Reprezentant każdego modułu panelu — sprawdzamy, że żadnej grupy tras
     * nie zostawiono poza middleware `admin.auth`.
     *
     * @var list<string>
     */
    private const PROTECTED_PATHS = [
        '/',
        '/fail2ban',
        '/fail2ban/ip/203.0.113.7',
        '/amavis',
        '/aliases',
        '/sender-rules',
        '/internal-rcpts',
        '/system-users',
        '/system-groups',
        '/rustdesk',
    ];

    public function test_guest_is_redirected_to_the_login_screen(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login.form'));
    }

    public function test_every_module_redirects_guest_to_login_and_remembers_the_target(): void
    {
        foreach (self::PROTECTED_PATHS as $path) {
            $response = $this->get($path);

            $response->assertRedirect(route('login.form'));
            $response->assertSessionHas('url.intended');
        }
    }
}
