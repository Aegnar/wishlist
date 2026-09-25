<?php
declare(strict_types=1);

namespace Tests;

use App\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testStartsOnlyWithSessionCookieOrOnLoginPage(): void
    {
        self::assertTrue(Session::shouldStart(['wl_sess' => 'abc'], '/', 'wl_sess'));
        self::assertTrue(Session::shouldStart(['wl_sess' => 'abc'], '/item/3', 'wl_sess'));
        self::assertTrue(Session::shouldStart([], '/login', 'wl_sess'));

        self::assertFalse(Session::shouldStart([], '/', 'wl_sess'), 'visiteur anonyme : aucune session créée');
        self::assertFalse(Session::shouldStart(['autre' => 'x'], '/item/3', 'wl_sess'));
        self::assertFalse(Session::shouldStart(['wl_sess' => ''], '/', 'wl_sess'), 'cookie vide ignoré');
        self::assertFalse(Session::shouldStart(['wl_sess' => ['x']], '/', 'wl_sess'), 'cookie non textuel ignoré');
        self::assertFalse(Session::shouldStart([], '/login/x', 'wl_sess'));
    }
}
