<?php
declare(strict_types=1);

namespace Tests\Http;

use App\View;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wl-view-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/parts', 0777, true);
        file_put_contents($this->dir . '/layout.php', '<main><?= $content ?></main><?= e($site) ?>');
        file_put_contents($this->dir . '/page.php', '<h1><?= e($title) ?></h1><?= $this->partial("parts/x", ["n" => 2]) ?>');
        file_put_contents($this->dir . '/parts/x.php', '<i><?= $n ?><?= e($site) ?></i>');
    }

    protected function tearDown(): void
    {
        unlink($this->dir . '/parts/x.php');
        rmdir($this->dir . '/parts');
        unlink($this->dir . '/layout.php');
        unlink($this->dir . '/page.php');
        rmdir($this->dir);
    }

    public function testRendersTemplateInsideLayoutWithSharedData(): void
    {
        $view = new View($this->dir);
        $view->share('site', 'S');

        self::assertSame('<main><h1>&lt;T&gt;</h1><i>2S</i></main>S', $view->render('page', ['title' => '<T>']));
        self::assertSame('<h1>x</h1><i>2S</i>', $view->render('page', ['title' => 'x'], null));
    }

    public function testMissingTemplateThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        (new View($this->dir))->render('absent');
    }
}
