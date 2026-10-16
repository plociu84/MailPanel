<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminAuth;
use App\Services\MailServerService;
use Exception;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Mockery;
use Tests\TestCase;

/**
 * Kontrakt usuwania użytkownika poczty (trasa DELETE /mail-users/{id}).
 *
 * Przycisk „Usuń" w resources/views/mail-users.blade.php wysyła formularz na
 * mail-users.destroy; kontroler musi wywołać usługę, obsłużyć jej błąd i wrócić
 * na listę użytkowników.
 */
class MailUserDeleteFlowTest extends TestCase
{
    private const USER_ID = 7;

    /**
     * Middleware pomijane w testach: logowanie admina oraz CSRF.
     *
     * CSRF musi być pomijany jawnie, bo bootstrap/cache/config.php (cache produkcyjny)
     * zamraża APP_ENV=production — Laravel nie wykrywa wtedy środowiska testowego.
     */
    private const BYPASSED_MIDDLEWARE = [AdminAuth::class, PreventRequestForgery::class];

    public function test_delete_calls_service_and_redirects_with_server_log(): void
    {
        $mailService = Mockery::mock(MailServerService::class);
        $mailService->shouldReceive('deleteRoundcubeUser')
            ->once()
            ->with(self::USER_ID)
            ->andReturn('log serwera');

        $this->app->instance(MailServerService::class, $mailService);

        $response = $this->withoutMiddleware(self::BYPASSED_MIDDLEWARE)
            ->delete('/mail-users/'.self::USER_ID);

        $response->assertRedirect('/mail-users');
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Użytkownik został usunięty.');
        $response->assertSessionHas('server_log', 'log serwera');
    }

    public function test_delete_reports_service_failure_as_form_error(): void
    {
        $mailService = Mockery::mock(MailServerService::class);
        $mailService->shouldReceive('deleteRoundcubeUser')
            ->once()
            ->with(self::USER_ID)
            ->andThrow(new Exception('Nie znaleziono użytkownika o ID: 7'));

        $this->app->instance(MailServerService::class, $mailService);

        $response = $this->from('/mail-users')
            ->withoutMiddleware(self::BYPASSED_MIDDLEWARE)
            ->delete('/mail-users/'.self::USER_ID);

        $response->assertRedirect('/mail-users');
        $response->assertSessionHasErrors(['error' => 'Nie znaleziono użytkownika o ID: 7']);
    }

    public function test_delete_rejects_non_numeric_id(): void
    {
        // Brak oczekiwań — gdyby trasa przepuściła żądanie, mock zgłosiłby błąd
        // zamiast uruchomić prawdziwe polecenia na serwerze pocztowym.
        $this->app->instance(MailServerService::class, Mockery::mock(MailServerService::class));

        $this->withoutMiddleware(self::BYPASSED_MIDDLEWARE)
            ->delete('/mail-users/abc')
            ->assertNotFound();
    }

    public function test_delete_button_posts_to_destroy_route_with_spoofed_method(): void
    {
        $markup = $this->deleteFormMarkup();

        $this->assertStringContainsString('action="/mail-users/{{ $userId }}"', $markup);
        $this->assertStringContainsString('@method(\'DELETE\')', $markup);
        $this->assertStringContainsString('@csrf', $markup);
    }

    /**
     * Zwraca znaczniki formularza usuwania użytkownika z widoku mail-users.
     */
    private function deleteFormMarkup(): string
    {
        $view = file_get_contents(resource_path('views/mail-users.blade.php'));

        preg_match('/<form action="\/mail-users\/\{\{ \$userId \}\}".*?<\/form>/s', (string) $view, $matches);

        $this->assertNotEmpty($matches, 'Nie znaleziono formularza usuwania użytkownika.');

        return $matches[0];
    }
}
