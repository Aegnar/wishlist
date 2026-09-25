<?php
declare(strict_types=1);

namespace Tests\Http;

use App\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testScrubReplacesInvalidUtf8Recursively(): void
    {
        $scrubbed = Request::scrub([
            'title' => "Caf\xE9",
            'ok' => 'Déjà vu',
            'tags' => ['a', "b\xFF\xFEc", ['deep' => "\xC3"]],
        ]);

        self::assertSame('Caf?', $scrubbed['title']);
        self::assertSame('Déjà vu', $scrubbed['ok']);
        self::assertSame(['a', 'b??c', ['deep' => '?']], $scrubbed['tags']);
        array_walk_recursive($scrubbed, static fn (string $v) => self::assertTrue(mb_check_encoding($v, 'UTF-8')));
    }
}
