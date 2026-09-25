<?php
declare(strict_types=1);

namespace App;

use App\Repository\CommentRepository;
use App\Repository\ItemRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Schema\Migrator;
use PDO;

/** Conteneur des services de l'application (construit une fois par requête). */
final class App
{
    public readonly PDO $db;
    public readonly View $view;
    public readonly UserRepository $users;
    public readonly TagRepository $tags;
    public readonly ItemRepository $items;
    public readonly CommentRepository $comments;
    public readonly ImageStore $images;
    public readonly Auth $auth;
    public readonly Migrator $migrator;

    public function __construct(public readonly array $config, public readonly string $root)
    {
        date_default_timezone_set((string) $config['app']['timezone']);
        $this->db = Db::connect($config['db']);
        $this->view = new View($root . '/templates');
        $this->users = new UserRepository($this->db);
        $this->tags = new TagRepository($this->db);
        $this->items = new ItemRepository($this->db, $this->tags);
        $this->comments = new CommentRepository($this->db);
        $this->images = new ImageStore($config['paths']['storage'] . '/uploads', new UrlGuard());
        $this->auth = new Auth($this->db, $this->users);
        $this->migrator = new Migrator($this->db, $root . '/migrations');
    }
}
