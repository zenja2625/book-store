<?php

namespace App\Components;

use Cookie;
use Cms\Classes\ComponentBase;
use Tailor\Models\EntryRecord;

class Cart extends ComponentBase
{
    private const FILTER_OPTIONS = ["options" => ["min_range" => 1]];

    public $cartMap;
    public $cartSum;
    public $books;

    public function componentDetails()
    {
        return [
            'name' => 'Корзина',
            'description' => 'Управляет корзиной (шапка, каталог, страница корзины)'
        ];
    }

    public function init()
    {
        $raw = Cookie::get('cart', []);
        $parsed = \is_string($raw) ? \json_decode($raw, true) : $raw;
        $this->cartMap = \is_array($parsed) ? $parsed : [];
    }

    public function onRun()
    {
        $this->prepareVars();
    }

    public function onAddToCart()
    {
        $id = post('id');

        if (!$this->isValidId($id)) return;

        if (!EntryRecord::inSection('Catalog\Book')->where('id', $id)->exists()) return;

        $quantity = $this->cartMap[$id] ?? 0;
        $this->cartMap[$id] = $quantity + 1;

        return $this->updateCart();
    }

    public function onRemoveOneFromCart()
    {
        $id = post('id');

        if (!$this->isValidId($id)) return;

        $quantity = $this->cartMap[$id] ?? 0;
        if ($quantity > 1) {
            $this->cartMap[$id] = $quantity - 1;
        }

        return $this->updateCart();
    }

    public function onRemoveFromCart()
    {
        $id = post('id');

        if (!$this->isValidId($id)) return;

        if (isset($this->cartMap[$id])) {
            unset($this->cartMap[$id]);
        }

        return $this->updateCart();
    }

    private function updateCart()
    {
        $this->prepareVars();
        Cookie::queue('cart', \json_encode($this->cartMap), 60 * 24 * 30);

        $response = [
            "#cart-widget" => $this->renderPartial('@cart'),
        ];

        if ($this->page->id === 'cart') {
            $response["#cart-items"] = $this->renderPartial('@items');
            $response["#cart-summary"] = $this->renderPartial('@summary');
        } else {
            $id = post('id');
            $response["#add-book-{$id}"] = $this->renderPartial('@book-button', ['id' => $id]);
        }

        return $response;
    }

    private function isValidId($id)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT, self::FILTER_OPTIONS)) {
            \Log::warning('Некорректный ID товара в AJAX-запросе', [
                'input_id' => $id,
                'user_ip' => request()->ip()
            ]);
            return false;
        }

        return true;
    }

    private function prepareVars()
    {
        $ids = array_keys($this->cartMap);

        if (\count($ids) === 0) {
            $this->books = [];
            $this->cartSum = 0;
            return;
        }

        $books = EntryRecord::inSection('Catalog\Book')
            ->whereIn('id', $ids)
            ->get();

        $this->cartSum = $books->reduce(function ($carry, $book) {
            $quantity = $this->cartMap[$book->id] ?? 0;

            return $carry + ($book->current_price * $quantity);
        }, 0);


        if ($this->page->id === 'cart') {
            foreach ($books as $book) {
                $book->qty = $this->cartMap[$book->id];
            }

            $this->books = $books;
        }
    }
}
