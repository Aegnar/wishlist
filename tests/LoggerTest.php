<?php
declare(strict_types=1);

namespace Tests;

use App\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LoggerTest extends TestCase
{
    public function testInvalidUtf8IsSubstitutedInsteadOfDroppingTheMessage(): void
    {
        $file = sys_get_temp_dir() . '/wl-log-' . bin2hex(random_bytes(4)) . '/app.log';

        (new Logger($file))->error(new RuntimeException("Caf\xE9 introuvable"), ['uri' => "/item/\xFF"]);

        $line = (string) file_get_contents($file);
        unlink($file);
        rmdir(dirname($file));
        self::assertStringContainsString("\"Caf\u{FFFD} introuvable\"", $line);
        self::assertStringContainsString("{\"uri\":\"/item/\u{FFFD}\"}", $line);
    }
}
