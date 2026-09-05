<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The default Laravel stub asserted `/` returns 200, but this app's
     * root route (routes/web.php) redirects straight to /landing — this
     * test was never actually failing loudly because tests/Feature/ wasn't
     * wired into phpunit.xml's testsuites until this remediation pass.
     */
    public function test_the_application_redirects_to_landing(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/landing');
    }
}
