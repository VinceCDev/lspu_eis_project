<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

/**
 * HTTP-level coverage for App\Http\Middleware\EnsureRole across the app's
 * four roles (alumni, employer, admin, superadmin) — the "authorized /
 * unauthorized / unauthenticated" matrix, plus the ISO 25010 remediation
 * for item 7 (wrong-role now gets a 403 page instead of a silent redirect
 * to /login).
 *
 * This app's auth is custom session-based (App\Services\Auth), not
 * Laravel's default guards, so "logged in as X" here means seeding the
 * same session keys App\Services\Auth::login() sets — no real credentials
 * or DB user rows are needed for these checks since role gating only reads
 * the session.
 */
class RoleAuthorizationTest extends TestCase
{
    private function sessionAs(string $role, ?int $campusId = null): array
    {
        return [
            'user_id' => 999999,
            'email' => 'phpunit-role-check@example.test',
            'user_role' => $role,
            'campus_id' => $campusId,
            'loggedin' => true,
            'last_activity' => time(),
        ];
    }

    public function testUnauthenticatedAccessToAnAlumniPageRedirectsToLogin(): void
    {
        $this->get('/home')->assertRedirect('/login');
    }

    public function testUnauthenticatedAccessToAnAdminPageRedirectsToLogin(): void
    {
        $this->get('/admin_dashboard')->assertRedirect('/login');
    }

    public function testAlumniCanAccessAnAlumniOnlyPage(): void
    {
        $this->withSession($this->sessionAs('alumni'))
            ->get('/home')
            ->assertOk();
    }

    public function testAlumniIsDeniedWithA403OnAnAdminOnlyPage(): void
    {
        $response = $this->withSession($this->sessionAs('alumni'))->get('/admin_dashboard');

        $response->assertStatus(403);
        $response->assertViewIs('errors.403');
        // The actual regression this item fixed: this must NOT be a
        // redirect back to /login (confusing for an already-logged-in user).
        $response->assertHeaderMissing('Location');
    }

    public function testEmployerIsDeniedWithA403OnASuperadminOnlyPage(): void
    {
        $response = $this->withSession($this->sessionAs('employer'))->get('/superadmin_job');

        $response->assertStatus(403);
        $response->assertViewIs('errors.403');
    }

    public function testAdminCanAccessAnAdminOrSuperadminPage(): void
    {
        $this->withSession($this->sessionAs('admin'))
            ->get('/admin_dashboard')
            ->assertOk();
    }

    public function testAdminIsDeniedOnASuperadminOnlyPage(): void
    {
        $this->withSession($this->sessionAs('admin'))
            ->get('/superadmin_job')
            ->assertStatus(403);
    }

    public function testSuperadminCanAccessBothAdminAndSuperadminOnlyPages(): void
    {
        $this->withSession($this->sessionAs('superadmin'))->get('/admin_dashboard')->assertOk();
        $this->withSession($this->sessionAs('superadmin'))->get('/superadmin_job')->assertOk();
    }

    public function testWrongRoleOnAnApiStyleActionGetsJsonForbiddenNotAViewRedirect(): void
    {
        // EnforceSessionTimeout::isApiRequest() treats a ?action= query
        // param as an API-style call — those must keep returning JSON 403,
        // not the HTML error view (a JS fetch() call can't render a Blade
        // view; the JSON branch of EnsureRole was already correct and must
        // stay that way after this fix).
        $response = $this->withSession($this->sessionAs('alumni'))
            ->getJson('/admin_dashboard?action=stats');

        $response->assertStatus(403);
        $response->assertJson(['success' => false, 'message' => 'Forbidden']);
    }
}
