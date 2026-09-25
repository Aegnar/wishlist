<?php
/** @var array<string, string> $errors */
$error = static fn (string $key): string => isset($errors[$key]) ? '<p class="field-error">' . e($errors[$key]) . '</p>' : '';
?>
<h1>Mon compte</h1>
<section class="card narrow">
    <p>
        Connecté en tant que <strong><?= e($user['display_name']) ?></strong>
        (identifiant : <?= e($user['username']) ?><?= $user['role'] === 'admin' ? ', administrateur' : '' ?>).
    </p>
    <h2>Changer de mot de passe</h2>
    <form method="post" action="/account" class="form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <label class="field">
            <span>Mot de passe actuel</span>
            <input type="password" name="current_password" autocomplete="current-password" required>
            <?= $error('current_password') ?>
        </label>
        <label class="field">
            <span>Nouveau mot de passe (10 caractères minimum)</span>
            <input type="password" name="new_password" autocomplete="new-password" minlength="10" required>
            <?= $error('new_password') ?>
        </label>
        <label class="field">
            <span>Confirmer le nouveau mot de passe</span>
            <input type="password" name="new_password_confirm" autocomplete="new-password" required>
        </label>
        <div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
    </form>
</section>
