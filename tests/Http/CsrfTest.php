<?php
declare(strict_types=1);

namespace Tests\Http;

use App\Csrf;
use App\Flash;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTokenIsStableAndValidates(): void
    {
        $token = Csrf::token();

        self::assertSame(64, strlen($token));
        self::assertSame($token, Csrf::token());
        self::assertTrue(Csrf::validate($token));
        self::assertFalse(Csrf::validate('wrong'));
        self::assertFalse(Csrf::validate(null));
        self::assertFalse(Csrf::validate(['array']));
    }

    public function testRotateChangesToken(): void
    {
        $first = Csrf::token();
        Csrf::rotate();

        self::assertNotSame($first, Csrf::token());
        self::assertFalse(Csrf::validate($first));
    }

    public function testValidateFailsWithoutSessionToken(): void
    {
        self::assertFalse(Csrf::validate(str_repeat('a', 64)));
    }

    public function testFlashIsConsumedOnce(): void
    {
        Flash::add('success', 'Enregistré');

        self::assertSame([['type' => 'success', 'message' => 'Enregistré']], Flash::consume());
        self::assertSame([], Flash::consume());
    }
}
