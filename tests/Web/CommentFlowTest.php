<?php
declare(strict_types=1);

namespace Tests\Web;

use Tests\WebTestCase;

final class CommentFlowTest extends WebTestCase
{
    private const JSON = ['HTTP_ACCEPT' => 'application/json'];

    private array $alice;
    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = $this->createUser('alice');
        $this->loginAs($this->alice);
        $this->itemId = $this->createItem($this->alice['id'], ['title' => 'Lampe']);
    }

    public function testAddCommentAsJson(): void
    {
        $response = $this->request('POST', "/item/$this->itemId/comments", ['body' => "Vu moins cher\nchez X"], headers: self::JSON);

        self::assertSame(201, $response->status);
        $data = json_decode($response->body, true);
        self::assertTrue($data['ok']);
        self::assertSame('Alice', $data['comment']['author_name']);
        self::assertSame("Vu moins cher\nchez X", $data['comment']['body']);
        self::assertSame('/comments/' . $data['comment']['id'] . '/delete', $data['comment']['delete_url']);
        self::assertCount(1, $this->app->comments->forItem($this->itemId));
    }

    public function testAddCommentWithClassicForm(): void
    {
        $response = $this->request('POST', "/item/$this->itemId/comments", ['body' => 'Attendre les soldes']);

        $comment = $this->app->comments->forItem($this->itemId)[0];
        self::assertSame(303, $response->status);
        self::assertSame("/item/$this->itemId#comment-{$comment['id']}", $response->headers['Location']);
    }

    public function testEmptyCommentIsRejected(): void
    {
        $json = $this->request('POST', "/item/$this->itemId/comments", ['body' => '  '], headers: self::JSON);
        self::assertSame(422, $json->status);
        self::assertSame(['ok' => false, 'error' => 'Le commentaire est vide.'], json_decode($json->body, true));

        $form = $this->request('POST', "/item/$this->itemId/comments", ['body' => '']);
        self::assertSame("/item/$this->itemId#comments", $form->headers['Location']);
        self::assertSame('error', $_SESSION['_flash'][0]['type']);
        self::assertSame([], $this->app->comments->forItem($this->itemId));
    }

    public function testCommentOnMissingItem(): void
    {
        self::assertSame(404, $this->request('POST', '/item/9999/comments', ['body' => 'x'], headers: self::JSON)->status);
        self::assertSame(404, $this->request('POST', '/item/9999/comments', ['body' => 'x'])->status);
    }

    public function testCommentsAreShownWithDeleteButtonForAuthorOnly(): void
    {
        $bob = $this->createUser('bob');
        $this->app->comments->create($this->itemId, $this->alice['id'], 'De Alice');
        $this->app->comments->create($this->itemId, $bob['id'], 'De Bob');

        $body = $this->request('GET', "/item/$this->itemId")->body;

        self::assertStringContainsString('De Alice', $body);
        self::assertStringContainsString('De Bob', $body);
        self::assertSame(1, substr_count($body, 'action="/comments/'), 'Alice ne peut supprimer que son commentaire');
    }

    public function testDeleteOwnComment(): void
    {
        $id = $this->app->comments->create($this->itemId, $this->alice['id'], 'À supprimer');

        $response = $this->request('POST', "/comments/$id/delete");

        self::assertSame("/item/$this->itemId#comments", $response->headers['Location']);
        self::assertNull($this->app->comments->find($id));
    }

    public function testCannotDeleteOthersCommentUnlessAdmin(): void
    {
        $bob = $this->createUser('bob');
        $id = $this->app->comments->create($this->itemId, $bob['id'], 'De Bob');

        self::assertSame(403, $this->request('POST', "/comments/$id/delete")->status);
        self::assertNotNull($this->app->comments->find($id));

        $this->loginAs($this->createUser('root', 'admin'));
        self::assertSame(303, $this->request('POST', "/comments/$id/delete")->status);
        self::assertNull($this->app->comments->find($id));
    }

    public function testDeleteMissingCommentIs404(): void
    {
        self::assertSame(404, $this->request('POST', '/comments/9999/delete')->status);
    }
}
