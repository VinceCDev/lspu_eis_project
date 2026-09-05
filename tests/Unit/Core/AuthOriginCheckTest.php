<?php

namespace Tests\Unit\Core;

use App\Http\Middleware\RejectCrossOriginPost;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Ported from tests/Unit/Core/AuthOriginCheckTest.php. Covers
 * RejectCrossOriginPost::isCrossOriginPost() — the port of the original
 * Auth::isCrossOriginPost() Origin/Referer-based CSRF mitigation, now
 * applied as global middleware (see bootstrap/app.php) instead of a call
 * inside requireLogin(). A bug here either lets a forged cross-site POST
 * through, or breaks every legitimate POST in the app.
 */
class AuthOriginCheckTest extends TestCase
{
    private const APP_URL = 'http://localhost/lspu_eis';

    private function isCrossOriginPost(array $server, string $appUrl): bool
    {
        return RejectCrossOriginPost::isCrossOriginPost(new Request([], [], [], [], [], $server), $appUrl);
    }

    public function testNonPostRequestsAreNeverConsideredCrossOrigin(): void
    {
        $server = ['REQUEST_METHOD' => 'GET', 'HTTP_ORIGIN' => 'http://evil.example'];

        $this->assertFalse($this->isCrossOriginPost($server, self::APP_URL));
    }

    public function testMatchingOriginIsAllowed(): void
    {
        $server = ['REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'http://localhost'];

        $this->assertFalse($this->isCrossOriginPost($server, self::APP_URL));
    }

    public function testOriginWithMatchingPortIsAllowed(): void
    {
        $server = ['REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'http://localhost:8080'];

        $this->assertFalse($this->isCrossOriginPost($server, 'http://localhost:8080/lspu_eis'));
    }

    public function testOriginWithMismatchedPortIsRejected(): void
    {
        $server = ['REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'http://localhost:9999'];

        $this->assertTrue($this->isCrossOriginPost($server, 'http://localhost:8080/lspu_eis'));
    }

    public function testMismatchedOriginIsRejected(): void
    {
        $server = ['REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'http://evil.example'];

        $this->assertTrue($this->isCrossOriginPost($server, self::APP_URL));
    }

    public function testMatchingRefererIsAllowedWhenOriginIsAbsent(): void
    {
        $server = ['REQUEST_METHOD' => 'POST', 'HTTP_REFERER' => self::APP_URL.'/home'];

        $this->assertFalse($this->isCrossOriginPost($server, self::APP_URL));
    }

    public function testMismatchedRefererIsRejectedWhenOriginIsAbsent(): void
    {
        $server = ['REQUEST_METHOD' => 'POST', 'HTTP_REFERER' => 'http://evil.example/phishing'];

        $this->assertTrue($this->isCrossOriginPost($server, self::APP_URL));
    }

    public function testARefererThatMerelyStartsWithTheAppUrlAsASubstringIsRejected(): void
    {
        $server = ['REQUEST_METHOD' => 'POST', 'HTTP_REFERER' => self::APP_URL.'evil.example'];

        $this->assertTrue($this->isCrossOriginPost($server, self::APP_URL));
    }

    public function testNeitherHeaderPresentIsAllowed(): void
    {
        $server = ['REQUEST_METHOD' => 'POST'];

        $this->assertFalse($this->isCrossOriginPost($server, self::APP_URL));
    }

    public function testDisabledWhenAppUrlIsNotConfigured(): void
    {
        $server = ['REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'http://evil.example'];

        $this->assertFalse($this->isCrossOriginPost($server, ''));
    }

    public function testTrailingSlashOnAppUrlDoesNotCauseAFalseMismatch(): void
    {
        $server = ['REQUEST_METHOD' => 'POST', 'HTTP_ORIGIN' => 'http://localhost'];

        $this->assertFalse($this->isCrossOriginPost($server, self::APP_URL.'/'));
    }
}
