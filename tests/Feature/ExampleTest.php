<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The site root forwards to the dashboard, which is itself behind
     * authentication, so an anonymous visitor ends up at the sign-in screen.
     */
    public function test_the_application_sends_anonymous_visitors_to_sign_in(): void
    {
        $this->get('/')
            ->assertRedirect('/dashboard');

        $this->get('/dashboard')
            ->assertRedirect('/login');
    }
}
