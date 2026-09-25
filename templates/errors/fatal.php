<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($heading) ?> · Wishlist</title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<main class="container">
    <section class="error-page">
        <h1><?= e($heading) ?></h1>
        <p><?= e($message) ?></p>
<?php if (!empty($detail)): ?>
        <pre class="debug"><?= e($detail) ?></pre>
<?php endif ?>
    </section>
</main>
</body>
</html>
