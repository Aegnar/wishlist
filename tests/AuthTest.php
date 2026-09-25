<?php
declare(strict_types=1);

namespace Tests;

use App\Auth;
use App\Repository\UserRepository;
use App\Session;

final class AuthTest extends DatabaseTestCase
{
    private Auth $auth;
    private UserRepository $users;

    public static function setUpBeforeClass(): void
    {
        self::migrateFresh();
    }

    protected function setUp(): void
    {
        self::truncateData();
        $_SESSION = [];
        $this->users = new UserRepository(self::pdo());
        $this->auth = new Auth(self::pdo(), $this->users);
    }

    public function testAttemptWithValidCredentials(): void
    {
        $id = self::insertUser('alice');

        $user = $this->auth->attempt('Alice', 'motdepasse-solide', '1.2.3.4');

        self::assertSame($id, $user['id']);
        self::assertNotNull($this->users->find($id)['last_login_at']);
    }

    public function testAttemptFailsOnWrongPasswordUnknownUserOrInactiveAccount(): void
    {
        $id = self::insertUser('alice');

        self::assertNull($this->auth->attempt('alice', 'mauvais', '1.2.3.4'));
        self::assertNull($this->auth->attempt('inconnu', 'motdepasse-solide', '1.2.3.4'));
        $this->users->setActive($id, false);
        self::assertNull($this->auth->attempt('alice', 'motdepasse-solide', '1.2.3.4'));
    }

    public function testRateLimitAfterFiveFailuresPerIp(): void
    {
        self::insertUser('alice');
        for ($i = 0; $i < Auth::MAX_ATTEMPTS; $i++) {
            $this->auth->attempt('alice', 'mauvais', '1.2.3.4');
        }

        self::assertTrue($this->auth->isRateLimited('1.2.3.4'));
        self::assertNull($this->auth->attempt('alice', 'motdepasse-solide', '1.2.3.4'), 'même le bon mot de passe est refusé');
        self::assertFalse($this->auth->isRateLimited('5.6.7.8'));
        self::assertNotNull($this->auth->attempt('alice', 'motdepasse-solide', '5.6.7.8'));
    }

    public function testSuccessClearsFailuresForIp(): void
    {
        self::insertUser('alice');
        for ($i = 0; $i < Auth::MAX_ATTEMPTS - 1; $i++) {
            $this->auth->attempt('alice', 'mauvais', '1.2.3.4');
        }
        $this->auth->attempt('alice', 'motdepasse-solide', '1.2.3.4');
        $this->auth->attempt('alice', 'mauvais', '1.2.3.4');

        self::assertFalse($this->auth->isRateLimited('1.2.3.4'));
    }

    public function testLoginStoresSessionAndUserReloads(): void
    {
        $id = self::insertUser('alice', 'admin');
        $this->auth->login($this->users->find($id), true);

        self::assertSame($id, $_SESSION['user_id']);
        self::assertTrue($_SESSION['remember']);

        $fresh = new Auth(self::pdo(), $this->users);
        self::assertSame($id, $fresh->user()['id']);
        self::assertTrue($fresh->isAdmin());
    }

    public function testUserIsNullWhenDeactivatedOrIdleTooLong(): void
    {
        $id = self::insertUser('alice');
        $this->auth->login($this->users->find($id), false);

        $this->users->setActive($id, false);
        self::assertNull((new Auth(self::pdo(), $this->users))->user());
        self::assertSame([], $_SESSION, 'la session est vidée');

        $this->users->setActive($id, true);
        $this->auth->login($this->users->find($id), false);
        $_SESSION['last_seen'] = time() - Session::DEFAULT_IDLE - 1;
        self::assertNull((new Auth(self::pdo(), $this->users))->user());
    }

    public function testRememberedSessionSurvivesLongerIdle(): void
    {
        $id = self::insertUser('alice');
        $this->auth->login($this->users->find($id), true);
        $_SESSION['last_seen'] = time() - Session::DEFAULT_IDLE - 1;

        self::assertNotNull((new Auth(self::pdo(), $this->users))->user());
    }
}
