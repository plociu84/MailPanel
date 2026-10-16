<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Panel nie ma strony publicznej — niezalogowany gość trafia na ekran logowania.
     */
    public function test_guest_is_redirected_to_the_login_screen(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login.form'));
    }
}
