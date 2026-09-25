<?php
/** @var list<array> $users @var list<array> $tags @var array<string, string> $values @var array<string, string> $errors */
$error = static fn (string $key): string => isset($errors[$key]) ? '<p class="field-error">' . e($errors[$key]) . '</p>' : '';
$csrfField = '<input type="hidden" name="_csrf" value="' . e($csrf) . '">';
?>
<h1>Administration</h1>

<section class="section" id="users">
    <h2>Comptes</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">Identifiant</th>
                    <th scope="col">Rôle</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Dernière connexion</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($users as $account): ?>
<?php $active = (int) $account['is_active'] === 1; ?>
                <tr>
                    <td><?= e($account['display_name']) ?></td>
                    <td><?= e($account['username']) ?></td>
                    <td><?= $account['role'] === 'admin' ? 'Administrateur' : 'Utilisateur' ?></td>
                    <td><?= $active ? 'Actif' : '<span class="muted">Désactivé</span>' ?></td>
                    <td><?= $account['last_login_at'] !== null ? e(format_datetime($account['last_login_at'])) : '<span class="muted">jamais</span>' ?></td>
                    <td>
                        <div class="admin-inline">
                            <details class="dropdown">
                                <summary>Mot de passe</summary>
                                <form method="post" action="/admin/users/<?= (int) $account['id'] ?>/password" class="dropdown-panel">
                                    <?= $csrfField ?>
                                    <label class="field">
                                        <span>Nouveau mot de passe</span>
                                        <input type="password" name="password" autocomplete="new-password" minlength="10" required>
                                    </label>
                                    <label class="field">
                                        <span>Confirmer</span>
                                        <input type="password" name="password_confirm" autocomplete="new-password" required>
                                    </label>
                                    <button type="submit" class="btn btn-small btn-primary">Réinitialiser</button>
                                </form>
                            </details>
<?php if ($account['id'] !== $user['id']): ?>
                            <form method="post" action="/admin/users/<?= (int) $account['id'] ?>/toggle" class="inline"<?= $active ? ' data-confirm="Désactiver ce compte ? La personne ne pourra plus se connecter."' : '' ?>>
                                <?= $csrfField ?>
                                <button type="submit" class="btn btn-small<?= $active ? ' btn-danger' : '' ?>"><?= $active ? 'Désactiver' : 'Réactiver' ?></button>
                            </form>
<?php endif ?>
                        </div>
                    </td>
                </tr>
<?php endforeach ?>
            </tbody>
        </table>
    </div>

    <h3 class="subhead">Créer un compte</h3>
    <form method="post" action="/admin/users" class="card form narrow">
        <?= $csrfField ?>
<?php if ($errors !== []): ?>
        <p class="form-error" role="alert">Le compte n'a pas été créé : corrigez les champs signalés.</p>
<?php endif ?>
        <label class="field">
            <span>Identifiant (minuscules, chiffres, . - _)</span>
            <input type="text" name="username" value="<?= e($values['username']) ?>" autocapitalize="none" spellcheck="false" required>
            <?= $error('username') ?>
        </label>
        <label class="field">
            <span>Nom affiché</span>
            <input type="text" name="display_name" value="<?= e($values['display_name']) ?>" required>
            <?= $error('display_name') ?>
        </label>
        <label class="field">
            <span>Rôle</span>
            <select name="role">
                <option value="user"<?= $values['role'] === 'user' ? ' selected' : '' ?>>Utilisateur</option>
                <option value="admin"<?= $values['role'] === 'admin' ? ' selected' : '' ?>>Administrateur</option>
            </select>
        </label>
        <label class="field">
            <span>Mot de passe provisoire (10 caractères minimum)</span>
            <input type="password" name="password" autocomplete="new-password" minlength="10" required>
            <?= $error('password') ?>
        </label>
        <label class="field">
            <span>Confirmer le mot de passe</span>
            <input type="password" name="password_confirm" autocomplete="new-password" required>
        </label>
        <div>
            <button type="submit" class="btn btn-primary">Créer le compte</button>
        </div>
    </form>
</section>

<section class="section" id="tags">
    <h2>Tags</h2>
<?php if ($tags === []): ?>
    <p class="muted">Aucun tag pour l'instant.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Tag</th>
                    <th scope="col" class="num">Produits</th>
                    <th scope="col">Renommer</th>
                    <th scope="col">Fusionner dans</th>
                    <th scope="col"><span class="sr-only">Supprimer</span></th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($tags as $tag): ?>
                <tr>
                    <td><?= e($tag['name']) ?></td>
                    <td class="num"><?= (int) $tag['usage_count'] ?></td>
                    <td>
                        <form method="post" action="/admin/tags/<?= (int) $tag['id'] ?>/rename" class="admin-inline">
                            <?= $csrfField ?>
                            <label class="sr-only" for="rename-<?= (int) $tag['id'] ?>">Nouveau nom</label>
                            <input type="text" id="rename-<?= (int) $tag['id'] ?>" name="name" value="<?= e($tag['name']) ?>" maxlength="50" required>
                            <button type="submit" class="btn btn-small">Renommer</button>
                        </form>
                    </td>
                    <td>
<?php if (count($tags) > 1): ?>
                        <form method="post" action="/admin/tags/<?= (int) $tag['id'] ?>/merge" class="admin-inline" data-confirm="Fusionner « <?= e($tag['name']) ?> » dans le tag choisi ? Il sera supprimé.">
                            <?= $csrfField ?>
                            <label class="sr-only" for="merge-<?= (int) $tag['id'] ?>">Tag cible</label>
                            <select id="merge-<?= (int) $tag['id'] ?>" name="into">
<?php foreach ($tags as $target): ?>
<?php if ($target['id'] !== $tag['id']): ?>
                                <option value="<?= (int) $target['id'] ?>"><?= e($target['name']) ?></option>
<?php endif ?>
<?php endforeach ?>
                            </select>
                            <button type="submit" class="btn btn-small">Fusionner</button>
                        </form>
<?php endif ?>
                    </td>
                    <td>
<?php if ((int) $tag['usage_count'] === 0): ?>
                        <form method="post" action="/admin/tags/<?= (int) $tag['id'] ?>/delete" class="inline" data-confirm="Supprimer le tag « <?= e($tag['name']) ?> » ?">
                            <?= $csrfField ?>
                            <button type="submit" class="btn btn-small btn-danger">Supprimer</button>
                        </form>
<?php endif ?>
                    </td>
                </tr>
<?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
</section>
