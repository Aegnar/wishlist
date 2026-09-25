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

    public function testNormalizeIp(): void
    {
        self::assertSame('1.2.3.4', Auth::normalizeIp('1.2.3.4'));
        self::assertSame('2001:db8:1:2::/64', Auth::normalizeIp('2001:db8:1:2:aaaa:bbbb:cccc:dddd'));
        self::assertSame('2001:db8:1:2::/64', Auth::normalizeIp('2001:DB8:1:2::1'));
        self::assertSame('1.2.3.4', Auth::normalizeIp('::ffff:1.2.3.4'), 'IPv4 mappée : traitée comme IPv4');
        self::assertSame('pas-une-ip', Auth::normalizeIp('pas-une-ip'));
    }

    public function testIpv6AddressesInSameSlash64ShareTheCounter(): void
    {
        self::insertUser('alice');
        for ($i = 0; $i < Auth::MAX_ATTEMPTS; $i++) {
            $this->auth->attempt('alice', 'mauvais', '2001:db8:1:2::' . dechex($i + 1));
        }

        self::assertTrue($this->auth->isRateLimited('2001:db8:1:2:ffff::42'));
        self::assertNull($this->auth->attempt('alice', 'motdepasse-solide', '2001:db8:1:2::99'));
        self::assertSame(
            ['2001:db8:1:2::/64'],
            self::pdo()->query('SELECT DISTINCT ip FROM login_attempts')->fetchAll(\PDO::FETCH_COLUMN),
            'le préfixe /64 est stocké, pas l\'adresse complète',
        );
    }

    public function testIpv6AddressesInDifferentSlash64AreCountedSeparately(): void
    {
        self::insertUser('alice');
        for ($i = 0; $i < Auth::MAX_ATTEMPTS; $i++) {
            $this->auth->attempt('alice', 'mauvais', '2001:db8:1:2::1');
        }

        self::assertFalse($this->auth->isRateLimited('2001:db8:1:3::1'));
        self::assertNotNull($this->auth->attempt('alice', 'motdepasse-solide', '2001:db8:1:3::1'));
    }

    public function testUsernameCeilingAcrossManyIps(): void
    {
        self::insertUser('alice');
        self::insertUser('bob');
        for ($i = 0; $i < Auth::MAX_USERNAME_ATTEMPTS; $i++) {
            $this->auth->attempt($i % 2 === 0 ? 'alice' : 'ALICE', 'mauvais', '10.0.' . intdiv($i, 4) . '.' . $i);
        }

        self::assertFalse($this->auth->isRateLimited('10.9.9.9'), 'aucune IP n\'a atteint sa propre limite');
        self::assertTrue($this->auth->isRateLimited('10.9.9.9', 'Alice'));
        self::assertNull($this->auth->attempt('alice', 'motdepasse-solide', '10.9.9.9'), 'même le bon mot de passe est refusé');
        self::assertNotNull($this->auth->attempt('bob', 'motdepasse-solide', '10.9.9.9'), 'les autres comptes ne sont pas concernés');
    }

    public function testPasswordChangeInvalidatesExistingSessions(): void
    {
        $id = self::insertUser('alice');
        $this->auth->login($this->users->find($id), true);

        $this->users->updatePassword($id, 'nouveau-mot-de-passe');

        self::assertNull((new Auth(self::pdo(), $this->users))->user());
        self::assertSame([], $_SESSION, 'la session est vidée');
    }

    public function testRefreshAfterPasswordChangeKeepsCurrentSession(): void
    {
        $id = self::insertUser('alice');
        $this->auth->login($this->users->find($id), false);

        $this->users->updatePassword($id, 'nouveau-mot-de-passe');
        $this->auth->refreshAfterPasswordChange($id);

        self::assertSame($id, (new Auth(self::pdo(), $this->users))->user()['id']);
    }

    public function testRehashAtLoginKeepsFingerprintValid(): void
    {
        $id = self::insertUser('alice');
        self::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash('motdepasse-solide', PASSWORD_BCRYPT, ['cost' => 4]), $id]);

        $user = $this->auth->attempt('alice', 'motdepasse-solide', '1.2.3.4');
        $this->auth->login($user, false);

        self::assertFalse(password_needs_rehash($this->users->find($id)['password_hash'], PASSWORD_DEFAULT), 'hash mis à niveau');
        self::assertSame($id, (new Auth(self::pdo(), $this->users))->user()['id']);
    }
}
