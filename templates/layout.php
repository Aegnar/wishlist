<?php
/** @var string $content */
$pageTitle = isset($title) && $title !== '' ? $title . ' · Wishlist' : 'Wishlist';
$current = $currentPath ?? '';
$navCurrent = static fn (bool $active): string => $active ? ' aria-current="page"' : '';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrf ?? '') ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php foreach ($scripts ?? [] as $script): ?>
    <script src="<?= e(asset('js/' . $script . '.js')) ?>" defer></script>
<?php endforeach ?>
</head>
<body>
<?php if (!empty($user)): ?>
<header class="topbar">
    <a class="brand" href="/">Wishlist</a>
    <nav class="topnav" aria-label="Navigation principale">
        <a href="/"<?= $navCurrent($current === '/') ?>>À acheter</a>
        <a href="/purchased"<?= $navCurrent($current === '/purchased') ?>>Achetés</a>
<?php if ($user['role'] === 'admin'): ?>
        <a href="/admin"<?= $navCurrent(str_starts_with($current, '/admin')) ?>>Admin</a>
<?php endif ?>
        <a href="/account"<?= $navCurrent($current === '/account') ?>><?= e($user['display_name']) ?></a>
        <form method="post" action="/logout" class="inline">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button type="submit" class="link">Déconnexion</button>
        </form>
    </nav>
</header>
<?php endif ?>
<main class="container">
<?php foreach (App\Flash::consume() as $flash): ?>
    <p class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
<?php endforeach ?>
<?= $content ?>
</main>
</body>
</html>
