<?php

namespace App\Components;

use Cookie;
use Cms\Classes\ComponentBase;
use Tailor\Models\EntryRecord;

class Cart extends ComponentBase
{
    private const FILTER_OPTIONS = ["options" => ["min_range" => 1]];

    public $cart_map;
    public $cart_sum;
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
        $this->cart_map = \is_array($parsed) ? $parsed : [];
    }

    public function onRun()
    {
        $this->prepareVars();
    }

    public function onAddToCart()
    {
        $id = post('id');

        if (!$this->isValidId($id)) return;

        $book = EntryRecord::inSection('Catalog\Book')
            ->where('id', $id)
            ->first(['id', 'title', 'stock_qty']);

        if (!$book) return;

        $quantity = $this->cart_map[$id] ?? 0;

        if (($quantity + 1) > $book->stock_qty) {
            \Flash::error("Нельзя добавить ещё книгу «{$book->title}»");
            return $this->updateCart();
        }

        if ($quantity === 0 && $this->page->id !== 'cart') {
            \Flash::success("Книга «{$book->title}» добавлена в корзину");
        }

        $this->cart_map[$id] = $quantity + 1;

        return $this->updateCart();
    }

    public function onRemoveOneFromCart()
    {
        $id = post('id');

        if (!$this->isValidId($id)) return;

        $quantity = $this->cart_map[$id] ?? 0;
        if ($quantity > 1) {
            $this->cart_map[$id] = $quantity - 1;
        }

        return $this->updateCart();
    }

    public function onRemoveFromCart()
    {
        $id = post('id');

        if (!$this->isValidId($id)) return;

        if (isset($this->cart_map[$id])) {
            unset($this->cart_map[$id]);
        }

        return $this->updateCart();
    }

    private function updateCart()
    {
        $this->prepareVars();
        Cookie::queue('cart', \json_encode($this->cart_map), 60 * 24 * 30);

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
        $ids = array_keys($this->cart_map);

        if (\count($ids) === 0) {
            $this->books = [];
            $this->cart_sum = 0;
            return;
        }

        $books = EntryRecord::inSection('Catalog\Book')
            ->whereIn('id', $ids)
            ->get();

        $this->cart_sum = $books->reduce(function ($carry, $book) {
            $quantity = $this->cart_map[$book->id] ?? 0;

            return $carry + ($book->current_price * $quantity);
        }, 0);


        if ($this->page->id === 'cart') {
            foreach ($books as $book) {
                $book->qty = $this->cart_map[$book->id];
            }

            $this->books = $books;
        }
    }
}
