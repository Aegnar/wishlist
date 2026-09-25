<?php /** @var array $item */ ?>
        <li class="item-card prio-<?= e($item['priority']) ?>">
            <a class="item-card-link" href="/item/<?= (int) $item['id'] ?>">
                <div class="thumb">
<?php if ($item['image_path'] !== null): ?>
                    <img src="<?= e(media_url($item['image_path'], true)) ?>" alt="" loading="lazy">
<?php else: ?>
                    <span class="thumb-empty" aria-hidden="true">🛍️</span>
<?php endif ?>
                </div>
                <div class="item-card-body">
                    <h3><?= e($item['title']) ?></h3>
                    <div class="item-card-meta">
<?php if ($item['is_purchased']): ?>
                        <span class="price"><?= e(format_price($item['price_paid'])) ?></span>
                        <span class="muted">le <?= e(format_date($item['purchased_at'])) ?></span>
<?php else: ?>
                        <span class="price"><?= e(format_price($item['price_estimated'])) ?><?= $item['quantity'] > 1 ? ' × ' . (int) $item['quantity'] : '' ?></span>
<?php endif ?>
<?php if ($item['priority'] !== 'none'): ?>
                        <span class="badge badge-<?= e($item['priority']) ?>"><?= e(priority_label($item['priority'])) ?></span>
<?php endif ?>
<?php if ($item['store'] !== null): ?>
                        <span class="muted"><?= e($item['store']) ?></span>
<?php endif ?>
                    </div>
<?php if ($item['tags'] !== []): ?>
                    <ul class="tags">
<?php foreach ($item['tags'] as $tag): ?>
                        <li class="tag"><?= e($tag) ?></li>
<?php endforeach ?>
                    </ul>
<?php endif ?>
                </div>
            </a>
        </li>
