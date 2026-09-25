<?php
declare(strict_types=1);

namespace Tests\Repository;

use App\Repository\UserRepository;
use Tests\DatabaseTestCase;

final class UserRepositoryTest extends DatabaseTestCase
{
    private UserRepository $users;

    public static function setUpBeforeClass(): void
    {
        self::migrateFresh();
    }

    protected function setUp(): void
    {
        self::truncateData();
        $this->users = new UserRepository(self::pdo());
    }

    public function testCreateAndFind(): void
    {
        $id = $this->users->create('alice', 'Alice', 'motdepasse-solide', 'admin');

        $user = $this->users->find($id);

        self::assertSame('alice', $user['username']);
        self::assertSame('Alice', $user['display_name']);
        self::assertSame('admin', $user['role']);
        self::assertSame(1, $user['is_active']);
        self::assertTrue(password_verify('motdepasse-solide', $user['password_hash']));
        self::assertNull($this->users->find(9999));
    }

    public function testFindByUsernameIsCaseInsensitive(): void
    {
        $id = $this->users->create('bob', 'Bob', 'motdepasse-solide');

        self::assertSame($id, $this->users->findByUsername('BOB')['id']);
        self::assertNull($this->users->findByUsername('carol'));
    }

    public function testUpdatePasswordSetActiveAndTouchLogin(): void
    {
        $id = $this->users->create('bob', 'Bob', 'ancien-mot-de-passe');

        $this->users->updatePassword($id, 'nouveau-mot-de-passe');
        $this->users->setActive($id, false);
        $this->users->touchLogin($id);

        $user = $this->users->find($id);
        self::assertTrue(password_verify('nouveau-mot-de-passe', $user['password_hash']));
        self::assertSame(0, $user['is_active']);
        self::assertNotNull($user['last_login_at']);
    }

    public function testAllSortedByDisplayName(): void
    {
        $this->users->create('zoe', 'Zoé', 'motdepasse-solide');
        $this->users->create('anna', 'Anna', 'motdepasse-solide');

        self::assertSame(['Anna', 'Zoé'], array_column($this->users->all(), 'display_name'));
    }
}
