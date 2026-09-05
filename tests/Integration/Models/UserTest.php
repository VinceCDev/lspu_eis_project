<?php

namespace Tests\Integration\Models;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Ported from tests/Integration/Models/UserTest.php. */
class UserTest extends TestCase
{
    private User $model;
    private string $email;
    private string $secondaryEmail;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new User();
        $this->email = 'phpunit-user-'.uniqid().'@example.test';
        $this->secondaryEmail = 'phpunit-secondary-'.uniqid().'@example.test';
        $this->userId = $this->model->create($this->email, $this->secondaryEmail, password_hash('OriginalPass123!', PASSWORD_DEFAULT), 'alumni');
    }

    protected function tearDown(): void
    {
        DB::delete('DELETE FROM user WHERE user_id = ?', [$this->userId]);
        parent::tearDown();
    }

    public function testEmailExistsIsTrueForARegisteredEmailAndFalseForAnUnknownOne(): void
    {
        $this->assertTrue($this->model->emailExists($this->email));
        $this->assertFalse($this->model->emailExists('phpunit-unknown-'.uniqid().'@example.test'));
    }

    public function testFindByEmailOrSecondaryMatchesEitherAddress(): void
    {
        $byPrimary = $this->model->findByEmailOrSecondary($this->email);
        $bySecondary = $this->model->findByEmailOrSecondary($this->secondaryEmail);

        $this->assertNotNull($byPrimary);
        $this->assertSame($this->userId, (int) $byPrimary['user_id']);
        $this->assertNotNull($bySecondary);
        $this->assertSame($this->userId, (int) $bySecondary['user_id']);
    }

    public function testFindByEmailOrSecondaryReturnsNullForAnUnknownEmail(): void
    {
        $this->assertNull($this->model->findByEmailOrSecondary('phpunit-unknown-'.uniqid().'@example.test'));
    }

    public function testFindByResetTokenOnlyMatchesTheExactToken(): void
    {
        $this->model->setResetToken($this->userId, 'correct-token', date('Y-m-d H:i:s', strtotime('+1 hour')));

        $this->assertNotNull($this->model->findByResetToken($this->email, 'correct-token'));
        $this->assertNull($this->model->findByResetToken($this->email, 'wrong-token'));
    }

    public function testFindByResetTokenAlsoMatchesViaTheSecondaryEmail(): void
    {
        $this->model->setResetToken($this->userId, 'correct-token', date('Y-m-d H:i:s', strtotime('+1 hour')));

        $this->assertNotNull($this->model->findByResetToken($this->secondaryEmail, 'correct-token'));
    }

    public function testUpdatePasswordChangesWhatPasswordHashByIdReturns(): void
    {
        $newHash = password_hash('NewPass456!', PASSWORD_DEFAULT);

        $this->model->updatePassword($this->userId, $newHash);

        $stored = $this->model->passwordHashById($this->userId);
        $this->assertTrue(password_verify('NewPass456!', $stored));
        $this->assertFalse(password_verify('OriginalPass123!', $stored), 'the old password must stop working');
    }
}
