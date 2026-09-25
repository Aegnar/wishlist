<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\ItemFilter;

final class ItemController extends Controller
{
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
