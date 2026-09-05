<?php

namespace Tests\Feature\Auth;

use App\Models\RateLimiter;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HTTP-level coverage for the email 2FA flow (ISO 25010 remediation —
 * item 5: 2FA code generation moved from mt_rand() to random_int()).
 * MailService is mocked throughout: it's instantiated via the container
 * (AuthController now takes it via constructor injection, added alongside
 * this fix specifically so this flow could be tested without hitting the
 * app's real configured SMTP server on every test run).
 */
class TwoFactorAuthTest extends TestCase
{
    private const PASSWORD = 'PhpUnit2faPass!23';

    private int $userId;
    private string $email;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(MailService::class, function ($mock) {
            $mock->shouldReceive('send')->andReturn(true);
        });

        $this->email = 'phpunit-2fa-'.uniqid().'@example.test';
        $userModel = new User();
        $this->userId = $userModel->create($this->email, null, password_hash(self::PASSWORD, PASSWORD_DEFAULT), 'alumni', 'Active');
        DB::update('UPDATE user SET two_factor_enabled = 1 WHERE user_id = ?', [$this->userId]);
    }

    protected function tearDown(): void
    {
        (new RateLimiter())->clear('2fa_send', (string) $this->userId);
        (new RateLimiter())->clear('2fa_verify', (string) $this->userId);
        // LoginLog::isBruteForced() also tracks failed attempts by IP, and
        // $_SERVER['REMOTE_ADDR'] is empty in this CLI/test context — every
        // test run's failed attempts (e.g. testVerificationIsRateLimited...
        // below deliberately creates several) would otherwise pile up in
        // that shared empty-IP bucket across runs and start blocking
        // unrelated later logins. A real browser request always has a
        // populated REMOTE_ADDR, so it's safe to say every ip_address=''
        // row is a test artifact.
        DB::delete("DELETE FROM login_logs WHERE ip_address = '' OR email = ?", [$this->email]);
        DB::delete('DELETE FROM user WHERE user_id = ?', [$this->userId]);
        parent::tearDown();
    }

    private function referer(): array
    {
        return ['Referer' => rtrim(config('app.url'), '/').'/login'];
    }

    private function login(): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->referer())
            ->post('/login?action=login', ['email' => $this->email, 'password' => self::PASSWORD]);
    }

    public function testLoginForA2faEnabledUserRequiresVerificationAndDoesNotLogIn(): void
    {
        $response = $this->login();

        $response->assertOk()->assertJson(['success' => true, 'requires_2fa' => true]);
        // This app uses custom session-based auth (App\Services\Auth), not
        // Laravel's default guards, so "logged in" means these session keys
        // are set — they must NOT be, until 2FA verification succeeds.
        $response->assertSessionMissing('email');
        $response->assertSessionMissing('loggedin');
    }

    public function testGeneratedCodeIsAlwaysASixDigitNumericString(): void
    {
        // random_int(0, 999999) can legitimately produce a code as low as
        // "1" before zero-padding — this is exactly the format bug class a
        // naive mt_rand()->sprintf() swap could reintroduce if the padding
        // were ever dropped, so assert the stored value's shape directly.
        $this->login();

        $stored = (new User())->getTwoFactorCode($this->userId);

        $this->assertNotNull($stored['two_factor_code']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $stored['two_factor_code']);
    }

    public function testCorrectCodeWithinExpiryLogsTheUserIn(): void
    {
        $this->login();
        $code = (new User())->getTwoFactorCode($this->userId)['two_factor_code'];

        $response = $this->withHeaders($this->referer())
            ->post('/login?action=verifyTwoFactor', ['verification_code' => $code]);

        $response->assertOk()->assertJson(['success' => true]);
        $response->assertSessionHas('email', $this->email);
        $response->assertSessionHas('loggedin', true);
    }

    public function testIncorrectCodeIsRejected(): void
    {
        $this->login();

        $response = $this->withHeaders($this->referer())
            ->post('/login?action=verifyTwoFactor', ['verification_code' => '000000']);

        $response->assertOk()->assertJson(['success' => false]);
    }

    public function testExpiredCodeIsRejectedEvenWhenCorrect(): void
    {
        $this->login();
        $userModel = new User();
        $code = $userModel->getTwoFactorCode($this->userId)['two_factor_code'];
        // Force expiry into the past — same code, same user, only the
        // expiry timestamp changes.
        $userModel->setTwoFactorCode($this->userId, $code, date('Y-m-d H:i:s', strtotime('-1 minute')));

        $response = $this->withHeaders($this->referer())
            ->post('/login?action=verifyTwoFactor', ['verification_code' => $code]);

        $response->assertOk()->assertJson(['success' => false, 'message' => 'Invalid or expired verification code.']);
    }

    public function testVerificationIsRateLimitedAfterFiveFailedAttempts(): void
    {
        $this->login();

        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders($this->referer())
                ->post('/login?action=verifyTwoFactor', ['verification_code' => '000000'])
                ->assertJson(['success' => false]);
        }

        // 6th attempt (even with the now-blown-away-anyway code) must be
        // blocked by the rate limiter, not evaluated against the DB.
        $response = $this->withHeaders($this->referer())
            ->post('/login?action=verifyTwoFactor', ['verification_code' => '000000']);

        $response->assertJson(['success' => false, 'message' => 'Too many attempts. Please log in again to receive a new code.']);
    }

    public function testResendIsRateLimitedAfterThreeAttemptsWithinTheWindow(): void
    {
        // Each login() call while 2FA is required re-triggers a send.
        $this->login();
        $this->login();
        $this->login();

        $response = $this->login();

        $response->assertJson(['success' => false, 'message' => 'Too many verification code requests. Please wait a few minutes and try again.']);
    }
}
