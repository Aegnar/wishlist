<?php
declare(strict_types=1);

namespace App;

use App\Repository\UserRepository;
use PDO;

final class Auth
{
    public const MAX_ATTEMPTS = 5;
    public const WINDOW_MINUTES = 15;

    private ?array $user = null;
    private bool $loaded = false;
    private static ?string $dummyHash = null;

    public function __construct(private PDO $db, private UserRepository $users)
    {
    }

    public function isRateLimited(string $ip): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > NOW() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE'
        );
        $stmt->execute([$ip]);
        return (int) $stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    /** Vérifie les identifiants sans ouvrir de session. */
    public function attempt(string $username, string $password, string $ip): ?array
    {
        $this->db->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
        if ($this->isRateLimited($ip)) {
            return null;
        }
        $user = $this->users->findByUsername($username);
        // Hash factice si l'utilisateur n'existe pas : même temps de réponse dans tous les cas.
        $hash = $user['password_hash'] ?? (self::$dummyHash ??= password_hash('dummy-password', PASSWORD_DEFAULT));
        $valid = password_verify($password, $hash) && $user !== null && (int) $user['is_active'] === 1;

        if (!$valid) {
            $this->db
                ->prepare('INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?, ?, NOW())')
                ->execute([$ip, mb_substr($username, 0, 50)]);
            return null;
        }
        $this->db->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->updatePassword((int) $user['id'], $password);
        }
        $this->users->touchLogin((int) $user['id']);
        return $user;
    }

    public function login(array $user, bool $remember): void
    {
        Session::regenerate($remember);
        Csrf::rotate();
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['remember'] = $remember;
        $_SESSION['last_seen'] = time();
        $this->user = $user;
        $this->loaded = true;
    }

    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;
        $id = $_SESSION['user_id'] ?? null;
        if (!is_int($id)) {
            return null;
        }
        $remember = !empty($_SESSION['remember']);
        $idle = $remember ? Session::REMEMBER_LIFETIME : Session::DEFAULT_IDLE;
        if (time() - (int) ($_SESSION['last_seen'] ?? 0) > $idle) {
            $this->logout();
            return null;
        }
        $user = $this->users->find($id);
        if ($user === null || (int) $user['is_active'] !== 1) {
            $this->logout();
            return null;
        }
        $_SESSION['last_seen'] = time();
        if ($remember) {
            Session::refreshRememberCookie();
        }
        return $this->user = $user;
    }

    public function isAdmin(): bool
    {
        return ($this->user()['role'] ?? null) === 'admin';
    }

    public function logout(): void
    {
        Session::destroy();
        $this->user = null;
        $this->loaded = true;
    }
}
