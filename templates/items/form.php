<?php
/** @var array|null $item @var array<string, string> $values @var array<string, string> $errors */
$isEdit = $item !== null;
$hasPhoto = $isEdit && $item['image_path'] !== null;
$action = $isEdit ? '/item/' . (int) $item['id'] : '/item';
$cancel = $isEdit ? '/item/' . (int) $item['id'] : '/';
$error = static fn (string $key): string => isset($errors[$key]) ? '<p class="field-error">' . e($errors[$key]) . '</p>' : '';
?>
<h1><?= $isEdit ? 'Modifier le produit' : 'Ajouter un produit' ?></h1>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="form" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<?php if ($errors !== []): ?>
    <p class="form-error" role="alert">Le formulaire contient des erreurs : corrigez les champs signalés.</p>
<?php endif ?>
    <div class="form-grid">
        <div class="form-main">
            <label class="field">
                <span>Titre *</span>
                <input type="text" name="title" value="<?= e($values['title']) ?>" maxlength="200" required<?= $isEdit ? '' : ' autofocus' ?>>
                <?= $error('title') ?>
            </label>
            <label class="field">
                <span>Lien vers le produit</span>
                <input type="url" name="url" value="<?= e($values['url']) ?>" maxlength="2048" placeholder="https://…">
                <?= $error('url') ?>
            </label>
            <div class="row">
                <label class="field">
                    <span>Magasin</span>
                    <input type="text" name="store" value="<?= e($values['store']) ?>" maxlength="100" placeholder="IKEA, Amazon…">
                    <?= $error('store') ?>
                </label>
                <label class="field">
                    <span>Prix unitaire estimé (€)</span>
                    <input type="text" name="price_estimated" value="<?= e($values['price_estimated']) ?>" inputmode="decimal" placeholder="12,50">
                    <?= $error('price_estimated') ?>
                </label>
                <label class="field">
                    <span>Quantité</span>
                    <input type="number" name="quantity" value="<?= e($values['quantity']) ?>" min="1" max="999" step="1">
                    <?= $error('quantity') ?>
                </label>
            </div>
            <fieldset>
                <legend>Priorité</legend>
                <div class="radio-group">
<?php foreach (['high', 'none', 'low'] as $priority): ?>
                    <label class="check">
                        <input type="radio" name="priority" value="<?= $priority ?>"<?= $values['priority'] === $priority ? ' checked' : '' ?>>
                        <span><?= e(priority_label($priority)) ?></span>
                    </label>
<?php endforeach ?>
                </div>
                <?= $error('priority') ?>
            </fieldset>
            <label class="field">
                <span>Tags</span>
                <input type="text" name="tags" value="<?= e($values['tags']) ?>" placeholder="meuble, salon…" autocomplete="off" data-tags>
                <span class="hint">Séparés par des virgules ; un nouveau tag est créé automatiquement.</span>
                <?= $error('tags') ?>
            </label>
            <label class="field">
                <span>Description</span>
                <textarea name="description" rows="6" maxlength="10000"><?= e($values['description']) ?></textarea>
                <?= $error('description') ?>
            </label>
        </div>
        <div class="form-side">
            <fieldset class="stack">
                <legend>Photo</legend>
<?php if ($hasPhoto): ?>
                <img class="photo-current" src="<?= e(media_url($item['image_path'], true)) ?>" alt="Photo actuelle">
                <label class="check">
                    <input type="checkbox" name="remove_photo" value="1"<?= $values['remove_photo'] === '1' ? ' checked' : '' ?>>
                    <span>Supprimer la photo actuelle</span>
                </label>
<?php endif ?>
                <label class="field">
                    <span><?= $hasPhoto ? 'Remplacer par un fichier' : 'Envoyer un fichier' ?></span>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="photo-preview">
                </label>
                <img id="photo-preview" class="photo-preview" alt="Aperçu de la photo" hidden>
                <label class="field">
                    <span>… ou adresse (URL) d'une image</span>
                    <input type="url" name="image_url" value="<?= e($values['image_url']) ?>" placeholder="https://…/image.jpg">
                </label>
                <p class="hint">JPEG, PNG, WebP ou GIF — 8 Mo maximum (5 Mo par URL).</p>
                <?= $error('photo') ?>
                <?= $error('image_url') ?>
            </fieldset>
        </div>
    </div>
    <div class="actions">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
        <a class="btn btn-ghost" href="<?= e($cancel) ?>">Annuler</a>
    </div>
</form>
