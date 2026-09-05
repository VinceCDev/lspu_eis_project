<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HTTP-level coverage for the alumni "update my profile" workflow — one of
 * the priority workflows named in the ISO 25010 remediation's item 10
 * (Feature test foundation). Exercises Alumni\ProfileController::updateProfile
 * through the real route/middleware stack, not just the model in isolation
 * (already covered at the model level by existing Integration tests).
 */
class AlumniProfileUpdateTest extends TestCase
{
    private int $userId;
    private int $alumniId;
    private string $email;

    protected function setUp(): void
    {
        parent::setUp();

        $this->email = 'phpunit-profile-'.uniqid().'@example.test';
        $userModel = new User();
        $this->userId = $userModel->create($this->email, null, password_hash('irrelevant', PASSWORD_DEFAULT), 'alumni', 'Active');

        $this->alumniId = (int) DB::table('alumni')->insertGetId([
            'user_id' => $this->userId,
            'first_name' => 'Original',
            'middle_name' => '',
            'last_name' => 'Name',
            'birthdate' => '2000-01-01',
            'contact' => '09170000000',
            'gender' => 'Male',
            'civil_status' => 'Single',
            'city' => 'San Pablo',
            'province' => 'Laguna',
            'year_graduated' => 2020,
            'college' => 'College of Computer Studies',
            'course' => 'BS Computer Science',
            'verification_document' => '',
        ]);
    }

    protected function tearDown(): void
    {
        DB::delete('DELETE FROM alumni WHERE alumni_id = ?', [$this->alumniId]);
        DB::delete('DELETE FROM user WHERE user_id = ?', [$this->userId]);
        parent::tearDown();
    }

    private function sessionAsThisAlumni(): array
    {
        return [
            'user_id' => $this->userId,
            'email' => $this->email,
            'user_role' => 'alumni',
            'campus_id' => null,
            'loggedin' => true,
            'last_activity' => time(),
        ];
    }

    private function referer(): array
    {
        return ['Referer' => rtrim(config('app.url'), '/').'/my_profile'];
    }

    public function testUnauthenticatedUpdateAttemptIsBlocked(): void
    {
        $response = $this->withHeaders($this->referer())
            ->postJson('/alumni_profile_data?action=updateProfile', ['first_name' => 'Hacker']);

        $response->assertStatus(401);

        $stillOriginal = DB::selectOne('SELECT first_name FROM alumni WHERE alumni_id = ?', [$this->alumniId]);
        $this->assertSame('Original', $stillOriginal->first_name);
    }

    public function testAuthenticatedAlumniCanUpdateTheirOwnProfile(): void
    {
        $response = $this->withSession($this->sessionAsThisAlumni())
            ->withHeaders($this->referer())
            ->post('/alumni_profile_data?action=updateProfile', [
                'first_name' => 'Updated',
                'middle_name' => '',
                'last_name' => 'Name',
                'birthdate' => '2000-01-01',
                'contact' => '09171234567',
                'gender' => 'Male',
                'civil_status' => 'Single',
                'city' => 'Calamba',
                'province' => 'Laguna',
                'year_graduated' => '2020',
                'college' => 'College of Computer Studies',
                'course' => 'BS Computer Science',
                'campus_id' => '',
                'email' => $this->email,
                'secondary_email' => '',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $updated = DB::selectOne('SELECT first_name, city FROM alumni WHERE alumni_id = ?', [$this->alumniId]);
        $this->assertSame('Updated', $updated->first_name);
        $this->assertSame('Calamba', $updated->city);
    }

    public function testEmployerRoleCannotReachTheAlumniProfileUpdateEndpoint(): void
    {
        $response = $this->withSession([
            'user_id' => $this->userId,
            'email' => $this->email,
            'user_role' => 'employer',
            'campus_id' => null,
            'loggedin' => true,
            'last_activity' => time(),
        ])
            ->withHeaders($this->referer())
            ->postJson('/alumni_profile_data?action=updateProfile', ['first_name' => 'ShouldNotApply']);

        $response->assertStatus(403);

        $stillOriginal = DB::selectOne('SELECT first_name FROM alumni WHERE alumni_id = ?', [$this->alumniId]);
        $this->assertSame('Original', $stillOriginal->first_name);
    }
}
