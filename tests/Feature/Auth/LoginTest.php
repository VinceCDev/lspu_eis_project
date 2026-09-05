<?php

namespace Tests\Feature\Auth;

use App\Models\RateLimiter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HTTP-level coverage for the plain (non-2FA) login/logout flow. Item 10 of
 * the ISO 25010 remediation — "Feature tests contain only the Laravel
 * stub" — starting the prioritized foundation with authentication, since
 * every other workflow depends on it. Complements
 * Tests\Feature\Auth\TwoFactorAuthTest (the 2FA-required branch) and
 * Tests\Feature\Auth\RoleAuthorizationTest (the authorization matrix).
 */
class LoginTest extends TestCase
{
    private const PASSWORD = 'PhpUnitLoginPass!23';

    private int $userId;
    private string $email;

    protected function setUp(): void
    {
        parent::setUp();

        $this->email = 'phpunit-login-'.uniqid().'@example.test';
        $userModel = new User();
        $this->userId = $userModel->create($this->email, null, password_hash(self::PASSWORD, PASSWORD_DEFAULT), 'alumni', 'Active');
        // 2FA only triggers for a brand-new account or one inactive 30+
        // days (see AuthController::login) — back-date last_login so these
        // tests exercise the plain-login branch, not the 2FA branch.
        DB::update('UPDATE user SET last_login = NOW() WHERE user_id = ?', [$this->userId]);
    }

    protected function tearDown(): void
    {
        (new RateLimiter())->clear('2fa_send', (string) $this->userId);
        DB::delete("DELETE FROM login_logs WHERE ip_address = '' OR email = ?", [$this->email]);
        DB::delete('DELETE FROM user WHERE user_id = ?', [$this->userId]);
        parent::tearDown();
    }

    private function referer(): array
    {
        return ['Referer' => rtrim(config('app.url'), '/').'/login'];
    }

    public function testValidCredentialsLogTheUserInAndRedirectByRole(): void
    {
        $response = $this->withHeaders($this->referer())
            ->post('/login?action=login', ['email' => $this->email, 'password' => self::PASSWORD]);

        $response->assertOk()->assertJson(['success' => true, 'redirect' => '/home']);
        $response->assertSessionHas('email', $this->email);
        $response->assertSessionHas('user_role', 'alumni');
    }

    public function testInvalidPasswordIsRejectedWithoutRevealingWhichFieldWasWrong(): void
    {
        $response = $this->withHeaders($this->referer())
            ->post('/login?action=login', ['email' => $this->email, 'password' => 'WrongPassword!']);

        $response->assertOk()->assertJson(['success' => false, 'message' => 'Invalid email or password.']);
        $response->assertSessionMissing('email');
    }

    public function testUnknownEmailIsRejectedWithTheSameGenericMessage(): void
    {
        $response = $this->withHeaders($this->referer())
            ->post('/login?action=login', ['email' => 'phpunit-unknown-'.uniqid().'@example.test', 'password' => 'Whatever!23']);

        $response->assertOk()->assertJson(['success' => false, 'message' => 'Invalid email or password.']);
    }

    public function testInactiveAccountCannotLogInEvenWithCorrectPassword(): void
    {
        DB::update("UPDATE user SET status = 'Inactive' WHERE user_id = ?", [$this->userId]);

        $response = $this->withHeaders($this->referer())
            ->post('/login?action=login', ['email' => $this->email, 'password' => self::PASSWORD]);

        $response->assertOk()->assertJson(['success' => false, 'message' => 'Account not active.']);
    }

    public function testLogoutClearsTheSessionAndRedirectsToLogin(): void
    {
        $this->withHeaders($this->referer())
            ->post('/login?action=login', ['email' => $this->email, 'password' => self::PASSWORD])
            ->assertJson(['success' => true]);

        $response = $this->get('/logout');

        $response->assertRedirect('/login');
        $response->assertSessionMissing('email');
        $response->assertSessionMissing('loggedin');
    }

    public function testASubsequentRequestAfterLogoutIsTreatedAsUnauthenticated(): void
    {
        $this->withHeaders($this->referer())
            ->post('/login?action=login', ['email' => $this->email, 'password' => self::PASSWORD]);
        $this->get('/logout');

        // Session is reset between calls only because Laravel's test client
        // persists cookies across requests within one test by default —
        // this confirms logout actually invalidated it rather than just
        // clearing in-memory state for that one response.
        $this->get('/home')->assertRedirect('/login');
    }
}
