<?php

namespace App\Repositories;

use Tailor\Models\StreamRecord;
use Tailor\Models\EntryRecord;

class DiscountRepository
{
    private function applyPivotCondition($query, $pivot_table, $discount_table, $field_name, $value, $is_column = false)
    {
        $query->orWhereExists(function ($q) use ($pivot_table, $discount_table, $field_name, $value, $is_column) {
            $q->from($pivot_table)
                ->whereColumn('parent_id', $discount_table . '.id')
                ->where('field_name', $field_name);

            if ($is_column) {
                $q->whereColumn('relation_id', $value);
            } else {
                $q->where('relation_id', $value);
            }
        });
    }

    public function getBestDiscountForBook($book_id, $genre_id, $publisher_id)
    {
        $discount_model = StreamRecord::inSection('Catalog\Discount')->getModel();
        $discount_table = $discount_model->getTable();
        $pivot_table = $discount_model->books()->getTable();

        return StreamRecord::inSection('Catalog\Discount')
            ->where(function ($query) use ($pivot_table, $discount_table, $book_id, $genre_id, $publisher_id) {
                if ($book_id)
                    $this->applyPivotCondition($query, $pivot_table, $discount_table, 'books', $book_id);

                if ($genre_id)
                    $this->applyPivotCondition($query, $pivot_table, $discount_table, 'genres', $genre_id);

                if ($publisher_id)
                    $this->applyPivotCondition($query, $pivot_table, $discount_table, 'publishers', $publisher_id);

            })
            ->selectRaw("
                MAX(CASE WHEN content_group = 'percentage' THEN discount_pct ELSE NULL END) as max_pct, 
                MIN(CASE WHEN content_group = 'fixed_amount' THEN discount_num ELSE NULL END) as min_price
            ")
            ->reorder()
            ->first();
    }

    public function getAffectedBooksWithDiscounts($discount_model, $book_ids, $genre_ids, $publisher_ids)
    {
        $discount_table = $discount_model->getTable();
        $pivot_table = $discount_model->books()->getTable();
        $book_table = EntryRecord::inSection('Catalog\Book')->getModel()->getTable();

        $discount_subquery = StreamRecord::inSection('Catalog\Discount')
            ->where(function ($query) use ($pivot_table, $discount_table, $book_table) {
                $this->applyPivotCondition($query, $pivot_table, $discount_table, 'books', $book_table . '.id', true);
                $this->applyPivotCondition($query, $pivot_table, $discount_table, 'genres', $book_table . '.genre_id', true);
                $this->applyPivotCondition($query, $pivot_table, $discount_table, 'publishers', $book_table . '.publisher_id', true);
            });

        $affected_books_query = EntryRecord::inSection('Catalog\Book')
            ->select("$book_table.*")
            ->selectSub(
                (clone $discount_subquery)->where('content_group', 'percentage')->reorder()->selectRaw('MAX(discount_pct)'),
                'max_pct'
            )
            ->selectSub(
                (clone $discount_subquery)->where('content_group', 'fixed_amount')->reorder()->selectRaw('MIN(discount_num)'),
                'min_price'
            )
            ->where(function ($q) use ($book_ids, $genre_ids, $publisher_ids) {
                if ($book_ids->isNotEmpty())
                    $q->orWhereIn('id', $book_ids);
                if ($genre_ids->isNotEmpty())
                    $q->orWhereIn('genre_id', $genre_ids);
                if ($publisher_ids->isNotEmpty())
                    $q->orWhereIn('publisher_id', $publisher_ids);
            });

        return $affected_books_query->get();
    }
}
