<?php /** @var list<array> $items @var bool $purchased */ ?>
    <div class="table-wrap items-table">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col"><span class="sr-only">Photo</span></th>
                    <th scope="col">Produit</th>
                    <th scope="col">Priorité</th>
                    <th scope="col">Magasin</th>
                    <th scope="col">Tags</th>
                    <th scope="col" class="num">Qté</th>
                    <th scope="col" class="num"><?= $purchased ? 'Payé' : 'Prix unitaire' ?></th>
<?php if ($purchased): ?>
                    <th scope="col">Acheté le</th>
<?php endif ?>
                </tr>
            </thead>
            <tbody>
<?php foreach ($items as $item): ?>
                <tr>
                    <td>
<?php if ($item['image_path'] !== null): ?>
                        <img class="table-thumb" src="<?= e(media_url($item['image_path'], true)) ?>" alt="" loading="lazy">
<?php endif ?>
                    </td>
                    <td><a href="/item/<?= (int) $item['id'] ?>"><?= e($item['title']) ?></a></td>
                    <td>
<?php if ($item['priority'] !== 'none'): ?>
                        <span class="badge badge-<?= e($item['priority']) ?>"><?= e(priority_label($item['priority'])) ?></span>
<?php endif ?>
                    </td>
                    <td><?= e($item['store'] ?? '') ?></td>
                    <td><?= e(implode(', ', $item['tags'])) ?></td>
                    <td class="num"><?= (int) $item['quantity'] ?></td>
                    <td class="num"><?= e(format_price($purchased ? $item['price_paid'] : $item['price_estimated'])) ?></td>
<?php if ($purchased): ?>
                    <td><?= e(format_date($item['purchased_at'])) ?></td>
<?php endif ?>
                </tr>
<?php endforeach ?>
            </tbody>
        </table>
    </div>
