<?php
declare(strict_types=1);

namespace App;

use App\Repository\UserRepository;
use PDO;

final class Auth
{
    /** Échecs tolérés par IP (préfixe /64 en IPv6) sur la fenêtre. */
    public const MAX_ATTEMPTS = 5;
    /** Plafond plus large par identifiant, toutes IP confondues : freine les attaques distribuées. */
    public const MAX_USERNAME_ATTEMPTS = 20;
    public const WINDOW_MINUTES = 15;

    /** Hash bcrypt (coût 10) d'un mot de passe factice : même temps de réponse si l'utilisateur n'existe pas. */
    private const DUMMY_HASH = '$2y$10$AtTlG/XB3yq24yTdT8BMde5/Q7gG3fo4vSoutTPcf4x/.u1eWX6NK';

    private ?array $user = null;
    private bool $loaded = false;

    public function __construct(private PDO $db, private UserRepository $users)
    {
    }

    /**
     * Clé de limitation d'une IP : IPv4 inchangée, IPv6 réduite à son préfixe /64
     * (un client IPv6 dispose en général de tout un /64 et pourrait sinon changer d'adresse à chaque essai).
     */
    public static function normalizeIp(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return mb_substr($ip, 0, 45);
        }
        // IPv4 mappée (::ffff:a.b.c.d) : traitée comme l'IPv4 qu'elle contient.
        if (str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }
        return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
    }

    /** Limité si l'IP (préfixe) ou, s'il est fourni, l'identifiant a trop d'échecs récents. */
    public function isRateLimited(string $ip, string $username = ''): bool
    {
        $window = 'attempted_at > NOW() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND $window");
        $stmt->execute([self::normalizeIp($ip)]);
        if ((int) $stmt->fetchColumn() >= self::MAX_ATTEMPTS) {
            return true;
        }
        $username = self::usernameKey($username);
        if ($username === '') {
            return false;
        }
        // Collation utf8mb4_unicode_ci : comparaison insensible à la casse.
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND $window");
        $stmt->execute([$username]);
        return (int) $stmt->fetchColumn() >= self::MAX_USERNAME_ATTEMPTS;
    }

    /** Vérifie les identifiants sans ouvrir de session. */
    public function attempt(string $username, string $password, string $ip): ?array
    {
        $this->db->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
        if ($this->isRateLimited($ip, $username)) {
            return null;
        }
        $ip = self::normalizeIp($ip);
        $user = $this->users->findByUsername($username);
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $valid = password_verify($password, $hash) && $user !== null && (int) $user['is_active'] === 1;

        if (!$valid) {
            $this->db
                ->prepare('INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?, ?, NOW())')
                ->execute([$ip, self::usernameKey($username)]);
            return null;
        }
        $this->db->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
        $id = (int) $user['id'];
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->updatePassword($id, $password);
        }
        $this->users->touchLogin($id);
        // Relecture : l'empreinte de session doit porter sur le hash réellement stocké (après rehash éventuel).
        return $this->users->find($id);
    }

    public function login(array $user, bool $remember): void
    {
        Session::regenerate($remember);
        Csrf::rotate();
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['remember'] = $remember;
        $_SESSION['last_seen'] = time();
        $_SESSION['pwd'] = self::fingerprint($user);
        $this->user = $user;
        $this->loaded = true;
    }

    /**
     * Après un changement de son propre mot de passe : les autres sessions de l'utilisateur
     * deviennent invalides (empreinte périmée) ; la session courante reçoit la nouvelle empreinte
     * et un nouvel identifiant, pour que cet appareil reste connecté.
     */
    public function refreshAfterPasswordChange(int $userId): void
    {
        $user = $this->users->find($userId);
        if ($user === null || ($_SESSION['user_id'] ?? null) !== $userId) {
            return;
        }
        Session::regenerate(!empty($_SESSION['remember']));
        $_SESSION['pwd'] = self::fingerprint($user);
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
        $fingerprint = $_SESSION['pwd'] ?? null;
        if (
            $user === null
            || (int) $user['is_active'] !== 1
            // Mot de passe changé depuis l'ouverture de cette session (autre appareil, réinitialisation admin).
            || !is_string($fingerprint)
            || !hash_equals(self::fingerprint($user), $fingerprint)
        ) {
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

    private static function fingerprint(array $user): string
    {
        return hash('sha256', (string) $user['password_hash']);
    }

    private static function usernameKey(string $username): string
    {
        return mb_substr(trim($username), 0, 50);
    }
}
