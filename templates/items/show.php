<?php
/** @var array $item @var list<array> $comments @var array $purchase @var array $purchaseErrors @var string $today */
$id = (int) $item['id'];
$isAdmin = $user['role'] === 'admin';
$listUrl = $item['is_purchased'] ? '/purchased' : '/';
$lineTotal = $item['price_estimated'] !== null ? (float) $item['price_estimated'] * (int) $item['quantity'] : null;
?>
<article class="item-detail">
    <p class="back"><a href="<?= $listUrl ?>">← Retour à la liste</a></p>
    <div class="item-detail-grid">
        <div class="item-photo">
<?php if ($item['image_path'] !== null): ?>
            <a href="<?= e(media_url($item['image_path'])) ?>"><img src="<?= e(media_url($item['image_path'])) ?>" alt="Photo : <?= e($item['title']) ?>"></a>
<?php else: ?>
            <span class="thumb-empty" aria-hidden="true">🛍️</span>
<?php endif ?>
        </div>
        <div class="item-info">
            <h1><?= e($item['title']) ?></h1>
            <div class="item-badges">
<?php if ($item['is_purchased']): ?>
                <span class="badge badge-done">Acheté</span>
<?php endif ?>
<?php if ($item['priority'] !== 'none'): ?>
                <span class="badge badge-<?= e($item['priority']) ?>">Priorité <?= e(mb_strtolower(priority_label($item['priority']))) ?></span>
<?php endif ?>
            </div>
            <dl class="facts">
                <dt>Prix estimé</dt>
                <dd>
                    <?= e(format_price($item['price_estimated'])) ?>
<?php if ($item['quantity'] > 1 && $lineTotal !== null): ?>
                    × <?= (int) $item['quantity'] ?> = <strong><?= e(format_price($lineTotal)) ?></strong>
<?php endif ?>
                </dd>
                <dt>Quantité</dt>
                <dd><?= (int) $item['quantity'] ?></dd>
<?php if ($item['store'] !== null): ?>
                <dt>Magasin</dt>
                <dd><?= e($item['store']) ?></dd>
<?php endif ?>
<?php if ($item['url'] !== null): ?>
                <dt>Lien</dt>
                <dd><a href="<?= e($item['url']) ?>" target="_blank" rel="noopener noreferrer">Voir le produit ↗</a></dd>
<?php endif ?>
<?php if ($item['tags'] !== []): ?>
                <dt>Tags</dt>
                <dd>
                    <ul class="tags">
<?php foreach ($item['tags'] as $tag): ?>
                        <li><a class="tag" href="<?= e(url($listUrl, ['tag' => [$tag]])) ?>"><?= e($tag) ?></a></li>
<?php endforeach ?>
                    </ul>
                </dd>
<?php endif ?>
<?php if ($item['is_purchased']): ?>
                <dt>Achat</dt>
                <dd>
                    le <?= e(format_date($item['purchased_at'])) ?>
<?php if ($item['purchased_by_name'] !== null): ?>
                    par <?= e($item['purchased_by_name']) ?>
<?php endif ?>
<?php if ($item['price_paid'] !== null): ?>
                    pour <strong><?= e(format_price($item['price_paid'])) ?></strong>
<?php endif ?>
                </dd>
<?php endif ?>
            </dl>
<?php if ($item['description'] !== null): ?>
            <p class="description prewrap"><?= e($item['description']) ?></p>
<?php endif ?>
            <p class="meta muted">Ajouté par <?= e($item['created_by_name']) ?> le <?= e(format_date($item['created_at'])) ?></p>
            <div class="actions">
<?php if (!$item['is_purchased']): ?>
                <button type="button" class="btn btn-primary" data-dialog-open="purchase-dialog">Marquer acheté</button>
<?php else: ?>
                <form method="post" action="/item/<?= $id ?>/unpurchase" class="inline">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button type="submit" class="btn">Remettre à acheter</button>
                </form>
<?php endif ?>
                <a class="btn" href="/item/<?= $id ?>/edit">Modifier</a>
                <form method="post" action="/item/<?= $id ?>/delete" class="inline" data-confirm="Supprimer définitivement ce produit, sa photo et ses commentaires ?">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>

<?php if (!$item['is_purchased']): ?>
    <dialog id="purchase-dialog" class="dialog"<?= $purchaseErrors !== [] ? ' data-open-on-load' : '' ?>>
        <form method="post" action="/item/<?= $id ?>/purchase" class="form">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <h2>Marquer comme acheté</h2>
            <label class="field">
                <span>Date d'achat</span>
                <input type="date" name="purchased_at" value="<?= e($purchase['purchased_at']) ?>" max="<?= e($today) ?>" required>
<?php if (isset($purchaseErrors['purchased_at'])): ?>
                <p class="field-error"><?= e($purchaseErrors['purchased_at']) ?></p>
<?php endif ?>
            </label>
            <label class="field">
                <span>Prix payé au total (€)</span>
                <input type="text" name="price_paid" value="<?= e($purchase['price_paid']) ?>" inputmode="decimal" placeholder="optionnel">
<?php if (isset($purchaseErrors['price_paid'])): ?>
                <p class="field-error"><?= e($purchaseErrors['price_paid']) ?></p>
<?php endif ?>
            </label>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Valider l'achat</button>
                <button type="button" class="btn btn-ghost" data-dialog-close>Annuler</button>
            </div>
        </form>
    </dialog>
<?php endif ?>

    <section class="comments" id="comments" aria-labelledby="comments-title">
        <h2 id="comments-title">Commentaires <span class="count" data-comment-count><?= count($comments) ?></span></h2>
        <ul class="comment-list" data-comment-list>
<?php foreach ($comments as $comment): ?>
            <li class="comment" id="comment-<?= (int) $comment['id'] ?>">
                <div class="comment-head">
                    <strong><?= e($comment['author_name']) ?></strong>
                    <time datetime="<?= e($comment['created_at']) ?>"><?= e(format_datetime($comment['created_at'])) ?></time>
<?php if ($comment['user_id'] === $user['id'] || $isAdmin): ?>
                    <form method="post" action="/comments/<?= (int) $comment['id'] ?>/delete" class="inline" data-confirm="Supprimer ce commentaire ?">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <button type="submit" class="link link-danger">Supprimer</button>
                    </form>
<?php endif ?>
                </div>
                <p class="comment-body"><?= e($comment['body']) ?></p>
            </li>
<?php endforeach ?>
        </ul>
        <p class="empty" data-comment-empty<?= $comments !== [] ? ' hidden' : '' ?>>Aucun commentaire pour l'instant.</p>
        <form method="post" action="/item/<?= $id ?>/comments" class="comment-form" data-comment-form>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="sr-only" for="comment-body">Nouveau commentaire</label>
            <textarea id="comment-body" name="body" rows="3" maxlength="2000" required placeholder="Ajouter un commentaire…"></textarea>
            <p class="form-error" data-comment-error hidden></p>
            <button type="submit" class="btn btn-primary">Commenter</button>
        </form>
    </section>
</article>
