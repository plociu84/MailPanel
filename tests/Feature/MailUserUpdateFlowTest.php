<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminAuth;
use App\Services\MailServerService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

/**
 * Kontrakt formularza edycji użytkownika poczty ("Edytuj użytkownika & Zmień hasło").
 *
 * Modal w resources/views/mail-users.blade.php wysyła pola, które muszą trafić do
 * walidacji MailUserController@update — login, imie, nazwisko, email_pl,
 * email_com_pl, mail_host oraz opcjonalny password.
 */
class MailUserUpdateFlowTest extends TestCase
{
    private const USER_ID = 7;

    /**
     * Middleware pomijane w testach: logowanie admina oraz CSRF.
     *
     * CSRF musi być pomijany jawnie, bo bootstrap/cache/config.php (cache produkcyjny)
     * zamraża APP_ENV=production — Laravel nie wykrywa wtedy środowiska testowego.
     */
    private const BYPASSED_MIDDLEWARE = [AdminAuth::class, PreventRequestForgery::class];

    /**
     * Ładunek wysyłany przez formularz edycji (pola użytkownika + nowe hasło).
     *
     * @var array<string, string>
     */
    private const EDIT_FORM_PAYLOAD = [
        'login' => 'jan.kowalski',
        'imie' => 'Jan',
        'nazwisko' => 'Kowalski',
        'email_pl' => 'jan.kowalski@telmor.pl',
        'email_com_pl' => 'jan.kowalski@telmor.com.pl',
        'mail_host' => 'localhost',
        'password' => 'NoweHaslo2026!',
    ];

    public function test_update_accepts_payload_submitted_by_edit_modal(): void
    {
        $mailService = Mockery::mock(MailServerService::class);
        $mailService->shouldReceive('updateRoundcubeUser')
            ->once()
            ->with(
                self::USER_ID,
                'jan.kowalski',
                'Jan',
                'Kowalski',
                'jan.kowalski@telmor.pl',
                'jan.kowalski@telmor.com.pl',
                'localhost',
                'NoweHaslo2026!'
            )
            ->andReturn('log serwera');

        $this->app->instance(MailServerService::class, $mailService);

        $response = $this->withoutMiddleware(self::BYPASSED_MIDDLEWARE)
            ->put('/mail-users/'.self::USER_ID, self::EDIT_FORM_PAYLOAD);

        $response->assertRedirect('/mail-users');
        $response->assertSessionHasNoErrors();
    }

    public function test_update_without_new_password_is_accepted(): void
    {
        $mailService = Mockery::mock(MailServerService::class);
        $mailService->shouldReceive('updateRoundcubeUser')
            ->once()
            ->with(
                self::USER_ID,
                'jan.kowalski',
                'Jan',
                'Kowalski',
                'jan.kowalski@telmor.pl',
                'jan.kowalski@telmor.com.pl',
                'localhost',
                ''
            )
            ->andReturn('log serwera');

        $this->app->instance(MailServerService::class, $mailService);

        $payload = self::EDIT_FORM_PAYLOAD;
        $payload['password'] = '';

        $response = $this->withoutMiddleware(self::BYPASSED_MIDDLEWARE)
            ->put('/mail-users/'.self::USER_ID, $payload);

        $response->assertRedirect('/mail-users');
        $response->assertSessionHasNoErrors();
    }

    public function test_update_rejects_password_with_control_characters(): void
    {
        // Brak oczekiwań — gdyby walidacja przepuściła żądanie, mock zgłosi błąd
        // zamiast uruchomić prawdziwe polecenia na serwerze pocztowym.
        $this->app->instance(MailServerService::class, Mockery::mock(MailServerService::class));

        $payload = self::EDIT_FORM_PAYLOAD;
        $payload['password'] = "Nowe\nHaslo";

        $response = $this->from('/mail-users')
            ->withoutMiddleware(self::BYPASSED_MIDDLEWARE)
            ->put('/mail-users/'.self::USER_ID, $payload);

        $response->assertSessionHasErrors('password');
    }

    public function test_edit_modal_submits_exactly_the_fields_from_the_payload_contract(): void
    {
        preg_match_all('/name="([a-z_]+)"/', $this->editModalMarkup(), $matches);

        $submittedFields = array_values(array_unique($matches[1]));
        $expectedFields = array_keys(self::EDIT_FORM_PAYLOAD);

        sort($submittedFields);
        sort($expectedFields);

        $this->assertSame(
            $expectedFields,
            $submittedFields,
            'Zmienił się zestaw pól formularza edycji — zaktualizuj EDIT_FORM_PAYLOAD w tym teście.'
        );
    }

    public function test_edit_modal_hidden_fields_are_populated_by_javascript(): void
    {
        preg_match_all('/id="(edit_[a-z_]+_hidden)"/', $this->editModalMarkup(), $matches);

        $hiddenIds = array_values(array_unique($matches[1]));

        $this->assertNotEmpty($hiddenIds, 'Formularz edycji nie ma pól ukrytych.');

        $javaScript = $this->javaScriptSources();

        foreach ($hiddenIds as $hiddenId) {
            $getElement = '/let\s+(\w+)\s*=\s*document\.getElementById\(\''.$hiddenId.'\'\);/';

            $this->assertMatchesRegularExpression(
                $getElement,
                $javaScript,
                "JavaScript nie pobiera pola {$hiddenId} — formularz wyśle pustą wartość."
            );

            preg_match($getElement, $javaScript, $variable);

            $this->assertMatchesRegularExpression(
                '/if\s*\(\s*'.preg_quote($variable[1], '/').'\s*\)\s*'.preg_quote($variable[1], '/').'\.value\s*=/',
                $javaScript,
                "JavaScript nie wypełnia pola {$hiddenId} — formularz wyśle pustą wartość."
            );
        }
    }

    /**
     * Zwraca znaczniki formularza edycji użytkownika z widoku mail-users.
     */
    private function editModalMarkup(): string
    {
        $view = file_get_contents(resource_path('views/mail-users.blade.php'));

        preg_match('/<form id="editUserForm".*?<\/form>/s', (string) $view, $matches);

        $this->assertNotEmpty($matches, 'Nie znaleziono formularza edycji użytkownika.');

        return $matches[0];
    }

    /**
     * Sklejone źródła wszystkich widoków oraz wydzielonych plików JS
     * z public/assets/js — część modali wypełniana jest globalnym skryptem
     * panelu (assets/js/panel.js), a nie kodem inline w widoku.
     */
    private function javaScriptSources(): string
    {
        $source = '';

        foreach (File::allFiles(resource_path('views')) as $file) {
            $source .= file_get_contents($file->getPathname());
        }

        foreach (File::allFiles(public_path('assets/js')) as $file) {
            $source .= file_get_contents($file->getPathname());
        }

        return $source;
    }
}
