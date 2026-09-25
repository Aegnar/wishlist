<?php
/** @var App\ItemFilter $filter */
$base = $filter->purchased ? '/purchased' : '/';
$selectedTags = array_map('mb_strtolower', $filter->tags);
?>
<div class="page-head">
    <nav class="tabs" aria-label="Listes">
        <a class="tab<?= $filter->purchased ? '' : ' is-active' ?>" href="/">À acheter<span class="count"><?= (int) $counts['todo'] ?></span></a>
        <a class="tab<?= $filter->purchased ? ' is-active' : '' ?>" href="/purchased">Achetés<span class="count"><?= (int) $counts['purchased'] ?></span></a>
    </nav>
    <a class="btn btn-primary" href="/item/new">+ Ajouter un produit</a>
</div>

<form method="get" action="<?= e($base) ?>" class="filters" data-autosubmit role="search">
    <label class="sr-only" for="filter-q">Rechercher</label>
    <input type="search" id="filter-q" name="q" value="<?= e($filter->q) ?>" placeholder="Rechercher (titre, description, magasin)…">
<?php if ($allTags !== []): ?>
    <details class="dropdown">
        <summary>Tags<?= $filter->tags !== [] ? ' (' . count($filter->tags) . ')' : '' ?></summary>
        <div class="dropdown-panel">
<?php foreach ($allTags as $tagName): ?>
            <label class="check">
                <input type="checkbox" name="tag[]" value="<?= e($tagName) ?>"<?= in_array(mb_strtolower($tagName), $selectedTags, true) ? ' checked' : '' ?>>
                <span><?= e($tagName) ?></span>
            </label>
<?php endforeach ?>
        </div>
    </details>
<?php endif ?>
    <label class="sr-only" for="filter-prio">Priorité</label>
    <select id="filter-prio" name="prio">
        <option value="">Toutes priorités</option>
<?php foreach (['high', 'none', 'low'] as $priority): ?>
        <option value="<?= $priority ?>"<?= $filter->priority === $priority ? ' selected' : '' ?>><?= e(priority_label($priority)) ?></option>
<?php endforeach ?>
    </select>
    <label class="sr-only" for="filter-sort">Tri</label>
    <select id="filter-sort" name="sort">
<?php foreach ($filter->sorts() as $key => $label): ?>
        <option value="<?= e($key) ?>"<?= $filter->sort === $key ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach ?>
    </select>
    <button type="submit" class="btn">Filtrer</button>
<?php if ($filter->isActive()): ?>
    <a class="btn btn-ghost" href="<?= e($base) ?>">Réinitialiser</a>
<?php endif ?>
</form>

<div class="toolbar">
    <p class="totals">
        <?= $filter->purchased ? 'Total dépensé' : 'Total estimé' ?> : <strong><?= e(format_price($totals['total'])) ?></strong>
<?php if ($totals['unpriced'] > 0): ?>
        <span class="muted">(<?= (int) $totals['unpriced'] ?> produit<?= $totals['unpriced'] > 1 ? 's' : '' ?> sans prix)</span>
<?php endif ?>
    </p>
    <div class="view-toggle" role="group" aria-label="Affichage">
        <button type="button" data-view-button="cards" aria-pressed="true">Cartes</button>
        <button type="button" data-view-button="table" aria-pressed="false">Tableau</button>
    </div>
</div>

<?php if ($items === []): ?>
<p class="empty">
    <?= $filter->isActive()
        ? 'Aucun produit ne correspond à ces filtres.'
        : ($filter->purchased ? 'Aucun achat pour le moment.' : 'La liste est vide : ajoutez un premier produit !') ?>
</p>
<?php else: ?>
<div class="items" data-view-root data-view="cards">
    <ul class="cards">
<?php foreach ($items as $item): ?>
<?= $this->partial('items/_card', ['item' => $item]) ?>
<?php endforeach ?>
    </ul>
<?= $this->partial('items/_table', ['items' => $items, 'purchased' => $filter->purchased]) ?>
</div>
<?php endif ?>
