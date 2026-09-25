<div class="login">
    <h1 class="login-title">Wishlist</h1>
    <form method="post" action="/login" class="card form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<?php if (!empty($error)): ?>
        <p class="form-error" role="alert"><?= e($error) ?></p>
<?php endif ?>
        <label class="field">
            <span>Identifiant</span>
            <input type="text" name="username" value="<?= e($username ?? '') ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
        </label>
        <label class="field">
            <span>Mot de passe</span>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <label class="check">
            <input type="checkbox" name="remember" value="1">
            <span>Rester connecté 30 jours</span>
        </label>
        <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
    </form>
</div>
