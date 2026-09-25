<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\ImageException;
use App\ItemFilter;
use App\Validator;

final class ItemController extends Controller
{
    private const EMPTY_VALUES = [
        'title' => '',
        'url' => '',
        'store' => '',
        'price_estimated' => '',
        'quantity' => '1',
        'priority' => 'none',
        'tags' => '',
        'description' => '',
        'image_url' => '',
        'remove_photo' => '',
    ];

    public function index(Request $request, array $params): Response
    {
        return $this->listing($request, false);
    }

    public function purchased(Request $request, array $params): Response
    {
        return $this->listing($request, true);
    }

    public function show(Request $request, array $params): Response
    {
        $item = $this->app->items->find((int) $params['id']);
        return $item === null ? $this->notFound() : $this->renderShow($item);
    }

    public function create(Request $request, array $params): Response
    {
        return $this->renderForm(null, self::EMPTY_VALUES, []);
    }

    public function store(Request $request, array $params): Response
    {
        $result = Validator::item($request->inputAll());
        if ($result['errors'] !== []) {
            return $this->renderForm(null, $this->valuesFromInput($request), $result['errors'], 422);
        }
        try {
            $image = $this->processPhoto($request);
        } catch (ImageException $e) {
            return $this->renderForm(null, $this->valuesFromInput($request), ['photo' => $e->getMessage()], 422);
        }
        $id = $this->app->items->create($result['data'], (int) $this->user()['id']);
        if ($image !== null) {
            $this->app->items->setImage($id, $image);
        }
        $this->flash('success', 'Produit ajouté.');
        return $this->redirect("/item/$id");
    }

    public function edit(Request $request, array $params): Response
    {
        $item = $this->app->items->find((int) $params['id']);
        return $item === null ? $this->notFound() : $this->renderForm($item, $this->valuesFromItem($item), []);
    }

    public function update(Request $request, array $params): Response
    {
        $item = $this->app->items->find((int) $params['id']);
        if ($item === null) {
            return $this->notFound();
        }
        $result = Validator::item($request->inputAll());
        if ($result['errors'] !== []) {
            return $this->renderForm($item, $this->valuesFromInput($request), $result['errors'], 422);
        }
        try {
            $image = $this->processPhoto($request);
        } catch (ImageException $e) {
            return $this->renderForm($item, $this->valuesFromInput($request), ['photo' => $e->getMessage()], 422);
        }
        $id = (int) $item['id'];
        $this->app->items->update($id, $result['data']);
        $oldImage = $item['image_path'];
        if ($image !== null) {
            $this->app->items->setImage($id, $image);
            $this->app->images->delete($oldImage);
        } elseif ($request->input('remove_photo') === '1' && $oldImage !== null) {
            $this->app->items->setImage($id, null);
            $this->app->images->delete($oldImage);
        }
        $this->flash('success', 'Produit modifié.');
        return $this->redirect("/item/$id");
    }

    public function purchase(Request $request, array $params): Response
    {
        $item = $this->app->items->find((int) $params['id']);
        if ($item === null) {
            return $this->notFound();
        }
        $result = Validator::purchase($request->inputAll(), date('Y-m-d'));
        if ($result['errors'] !== []) {
            return $this->renderShow($item, [
                'purchased_at' => $this->text($request, 'purchased_at'),
                'price_paid' => $this->text($request, 'price_paid'),
            ], $result['errors'], 422);
        }
        $id = (int) $item['id'];
        $this->app->items->markPurchased($id, $result['data']['purchased_at'], $result['data']['price_paid'], (int) $this->user()['id']);
        $this->flash('success', 'Produit marqué comme acheté.');
        return $this->redirect("/item/$id");
    }

    public function unpurchase(Request $request, array $params): Response
    {
        $item = $this->app->items->find((int) $params['id']);
        if ($item === null) {
            return $this->notFound();
        }
        $this->app->items->unmarkPurchased((int) $item['id']);
        $this->flash('success', 'Produit remis dans la liste « À acheter ».');
        return $this->redirect('/item/' . (int) $item['id']);
    }

    public function delete(Request $request, array $params): Response
    {
        $item = $this->app->items->find((int) $params['id']);
        if ($item === null) {
            return $this->notFound();
        }
        $this->app->images->delete($this->app->items->delete((int) $item['id']));
        $this->flash('success', 'Produit « ' . $item['title'] . ' » supprimé.');
        return $this->redirect($item['is_purchased'] ? '/purchased' : '/');
    }

    /** @return string|null nom de la nouvelle photo, null si aucune photo fournie */
    private function processPhoto(Request $request): ?string
    {
        $file = $request->file('photo');
        if ($file !== null) {
            return $this->app->images->storeUpload($file);
        }
        $url = trim($this->text($request, 'image_url'));
        return $url === '' ? null : $this->app->images->storeFromUrl($url);
    }

    /** @return array<string, string> */
    private function valuesFromInput(Request $request): array
    {
        $values = [];
        foreach (self::EMPTY_VALUES as $key => $default) {
            $value = $request->input($key, $default);
            $values[$key] = is_string($value) ? $value : $default;
        }
        return $values;
    }

    /** @return array<string, string> */
    private function valuesFromItem(array $item): array
    {
        return [
            'title' => $item['title'],
            'url' => $item['url'] ?? '',
            'store' => $item['store'] ?? '',
            'price_estimated' => format_price_input($item['price_estimated']),
            'quantity' => (string) $item['quantity'],
            'priority' => $item['priority'],
            'tags' => implode(', ', $item['tags']),
            'description' => $item['description'] ?? '',
            'image_url' => '',
            'remove_photo' => '',
        ];
    }

    /** @param array<string, string> $values @param array<string, string> $errors */
    private function renderForm(?array $item, array $values, array $errors, int $status = 200): Response
    {
        return $this->render('items/form', [
            'title' => $item === null ? 'Ajouter un produit' : 'Modifier « ' . $item['title'] . ' »',
            'item' => $item,
            'values' => $values,
            'errors' => $errors,
            'scripts' => ['tags'],
        ], $status);
    }

    private function listing(Request $request, bool $purchased): Response
    {
        $filter = ItemFilter::fromQuery($request->queryAll(), $purchased);
        return $this->render('items/list', [
            'title' => $purchased ? 'Achetés' : 'À acheter',
            'filter' => $filter,
            'items' => $this->app->items->search($filter),
            'totals' => $this->app->items->totals($filter),
            'counts' => $this->app->items->counts(),
            'allTags' => $this->app->tags->allNames(),
        ]);
    }

    /**
     * @param array{purchased_at: string, price_paid: string}|null $purchase valeurs du dialogue d'achat
     * @param array<string, string> $purchaseErrors
     */
    protected function renderShow(array $item, ?array $purchase = null, array $purchaseErrors = [], int $status = 200): Response
    {
        $today = date('Y-m-d');
        $purchase ??= [
            'purchased_at' => $today,
            'price_paid' => $item['price_estimated'] === null
                ? ''
                : format_price_input((float) $item['price_estimated'] * (int) $item['quantity']),
        ];
        return $this->render('items/show', [
            'title' => $item['title'],
            'item' => $item,
            'comments' => $this->app->comments->forItem((int) $item['id']),
            'purchase' => $purchase,
            'purchaseErrors' => $purchaseErrors,
            'today' => $today,
            'scripts' => ['purchase', 'comments'],
        ], $status);
    }
}
