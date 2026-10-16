<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminAuth;
use App\Services\MailServerService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Mockery;
use Tests\TestCase;

/**
 * Walidacja wpisów map Postfixa: aliasy, uprawnienia nadawców, blokady odbiorców.
 *
 * Klucz i wartość wpisu trafiają do pliku mapy, który jest zapisywany w całości,
 * dlatego żądanie z nową linią musi zostać odrzucone, zanim cokolwiek zostanie
 * wykonane na serwerze pocztowym.
 */
class MapEntryValidationTest extends TestCase
{
    /**
     * Middleware pomijane w testach: logowanie admina oraz CSRF.
     *
     * CSRF musi być pomijany jawnie, bo bootstrap/cache/config.php (cache produkcyjny)
     * zamraża APP_ENV=production — Laravel nie wykrywa wtedy środowiska testowego.
     */
    private const BYPASSED_MIDDLEWARE = [AdminAuth::class, PreventRequestForgery::class];

    /**
     * Nazwy pól identyfikujących istniejący wpis w żądaniach aktualizacji.
     *
     * @var array<string, string>
     */
    private const UPDATE_KEY_FIELD = [
        '/aliases' => 'old_alias',
        '/sender-rules' => 'old_sender',
        '/internal-rcpts' => 'old_recipient',
    ];

    public function test_map_entry_with_control_characters_is_rejected(): void
    {
        // Mock bez oczekiwań: gdyby walidacja przepuściła żądanie, mock zgłosiłby
        // błąd zamiast uruchomić prawdziwe polecenia na serwerze pocztowym.
        $this->app->instance(MailServerService::class, Mockery::mock(MailServerService::class));

        foreach (self::hostilePayloads() as [$uri, $payload, $field]) {
            $response = $this->from($uri)
                ->withoutMiddleware(self::BYPASSED_MIDDLEWARE)
                ->post($uri, $payload);

            $response->assertSessionHasErrors($field);
        }
    }

    public function test_map_entry_update_with_control_characters_is_rejected(): void
    {
        $this->app->instance(MailServerService::class, Mockery::mock(MailServerService::class));

        foreach (self::hostilePayloads() as [$uri, $payload, $field]) {
            $payload[self::UPDATE_KEY_FIELD[$uri]] = 'biuro';

            $response = $this->from($uri)
                ->withoutMiddleware(self::BYPASSED_MIDDLEWARE)
                ->put($uri, $payload);

            $response->assertSessionHasErrors($field);
        }
    }

    public function test_map_entry_destroy_with_control_characters_is_rejected(): void
    {
        $this->app->instance(MailServerService::class, Mockery::mock(MailServerService::class));

        $cases = [
            '/aliases' => ['alias', "biuro\n  attacker@example.com"],
            '/sender-rules' => ['sender', "biuro@telmor.pl\n  attacker@example.com"],
            '/internal-rcpts' => ['recipient', "pomoc@\n  attacker@example.com"],
        ];

        foreach ($cases as $uri => [$field, $hostile]) {
            $response = $this->from($uri)
                ->withoutMiddleware(self::BYPASSED_MIDDLEWARE)
                ->delete($uri, [$field => $hostile]);

            $response->assertSessionHasErrors($field);
        }
    }

    public function test_legit_map_entries_are_still_accepted(): void
    {
        $mailService = Mockery::mock(MailServerService::class);
        $mailService->shouldReceive('addAlias')
            ->once()
            ->with('rekrutacja', 'rafalbielawski, kadrytelmor')
            ->andReturn('log serwera');
        $mailService->shouldReceive('addSenderRule')
            ->once()
            ->with('logistyka@telmor.pl', 'joannabielawska, kadrytelmor')
            ->andReturn('log serwera');
        $mailService->shouldReceive('addInternalRcpt')
            ->once()
            ->with('pomoc@', 'banned_from_world')
            ->andReturn('log serwera');

        $this->app->instance(MailServerService::class, $mailService);

        $this->withoutMiddleware(self::BYPASSED_MIDDLEWARE)
            ->post('/aliases', ['alias' => 'rekrutacja', 'destination' => 'rafalbielawski, kadrytelmor'])
            ->assertRedirect('/aliases')
            ->assertSessionHasNoErrors();

        $this->withoutMiddleware(self::BYPASSED_MIDDLEWARE)
            ->post('/sender-rules', ['sender' => 'logistyka@telmor.pl', 'allowed_logins' => 'joannabielawska, kadrytelmor'])
            ->assertRedirect('/sender-rules')
            ->assertSessionHasNoErrors();

        $this->withoutMiddleware(self::BYPASSED_MIDDLEWARE)
            ->post('/internal-rcpts', ['recipient' => 'pomoc@', 'action' => 'banned_from_world'])
            ->assertRedirect('/internal-rcpts')
            ->assertSessionHasNoErrors();
    }

    /**
     * Żądania z niedozwolonymi znakami w kluczu lub wartości wpisu: adres URL,
     * ładunek formularza i pole, które ma zwrócić błąd walidacji.
     *
     * @return array<string, array{0: string, 1: array<string, string>, 2: string}>
     */
    private static function hostilePayloads(): array
    {
        return [
            'alias z nową linią' => ['/aliases', ['alias' => "biuro\n  attacker@example.com", 'destination' => 'kontakt@telmor.pl'], 'alias'],
            'alias z odstępem' => ['/aliases', ['alias' => 'evil key', 'destination' => 'kontakt@telmor.pl'], 'alias'],
            'destination z nową linią' => ['/aliases', ['alias' => 'biuro', 'destination' => "kontakt@telmor.pl\n  attacker@example.com"], 'destination'],
            'sender z nową linią' => ['/sender-rules', ['sender' => "biuro@telmor.pl\n  attacker@example.com", 'allowed_logins' => 'jan.kowalski'], 'sender'],
            'allowed_logins z nową linią' => ['/sender-rules', ['sender' => 'biuro@telmor.pl', 'allowed_logins' => "jan.kowalski\n  attacker@example.com"], 'allowed_logins'],
            'recipient z nową linią' => ['/internal-rcpts', ['recipient' => "pomoc@\n  attacker@example.com", 'action' => 'banned_from_world'], 'recipient'],
            'action z nową linią' => ['/internal-rcpts', ['recipient' => 'pomoc@', 'action' => "banned_from_world\n  attacker@example.com  OK"], 'action'],
        ];
    }
}
