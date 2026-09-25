<?php
declare(strict_types=1);

namespace App;

final class Session
{
    /** « Rester connecté » : 30 jours d'inactivité. */
    public const REMEMBER_LIFETIME = 30 * 86400;
    /** Sans « rester connecté » : 24 h d'inactivité (ou fermeture du navigateur). */
    public const DEFAULT_IDLE = 86400;

    public static function start(array $appConfig, string $savePath): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (!is_dir($savePath)) {
            mkdir($savePath, 0700, true);
        }
        // Dossier dédié : le nettoyage de la distribution (gc_maxlifetime court de php.ini) ne s'y applique pas.
        session_save_path($savePath);
        ini_set('session.gc_maxlifetime', (string) self::REMEMBER_LIFETIME);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name((string) $appConfig['session_name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (bool) $appConfig['cookie_secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function regenerate(bool $remember): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        if ($remember) {
            $_SESSION['cookie_refreshed'] = 0;
            self::refreshRememberCookie();
        }
    }

    /** Prolonge le cookie « rester connecté » (au plus une fois par jour). */
    public static function refreshRememberCookie(): void
    {
        if (time() - (int) ($_SESSION['cookie_refreshed'] ?? 0) < 86400) {
            return;
        }
        $_SESSION['cookie_refreshed'] = time();
        self::setCookie(session_id() ?: '', time() + self::REMEMBER_LIFETIME);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::setCookie('', time() - 3600);
            session_destroy();
        }
    }

    private static function setCookie(string $value, int $expires): void
    {
        if (headers_sent() || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $params = session_get_cookie_params();
        setcookie(session_name(), $value, [
            'expires' => $expires,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
