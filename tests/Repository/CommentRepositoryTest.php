<?php
declare(strict_types=1);

namespace Tests\Repository;

use App\Repository\CommentRepository;
use Tests\DatabaseTestCase;

final class CommentRepositoryTest extends DatabaseTestCase
{
    private CommentRepository $comments;

    public static function setUpBeforeClass(): void
    {
        self::migrateFresh();
    }

    protected function setUp(): void
    {
        self::truncateData();
        $this->comments = new CommentRepository(self::pdo());
    }

    public function testCreateFindListAndDelete(): void
    {
        $alice = self::insertUser('alice');
        $bob = self::insertUser('bob');
        $item = self::insertItem($alice);
        $other = self::insertItem($alice);

        $first = $this->comments->create($item, $alice, 'Vu moins cher ailleurs');
        $second = $this->comments->create($item, $bob, "Attendre\nles soldes");
        $this->comments->create($other, $bob, 'autre');

        $list = $this->comments->forItem($item);
        self::assertSame([$first, $second], array_column($list, 'id'));
        self::assertSame(['Alice', 'Bob'], array_column($list, 'author_name'));
        self::assertSame("Attendre\nles soldes", $list[1]['body']);

        self::assertSame($bob, $this->comments->find($second)['user_id']);
        $this->comments->delete($first);
        self::assertNull($this->comments->find($first));
        self::assertCount(1, $this->comments->forItem($item));
    }
}
