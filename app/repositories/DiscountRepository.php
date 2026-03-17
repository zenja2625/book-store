<?php

namespace App\Repositories;

use Tailor\Models\StreamRecord;
use Tailor\Models\EntryRecord;

class DiscountRepository
{
    private function applyPivotCondition($query, $pivotTable, $discountTable, $fieldName, $value, $isColumn = false)
    {
        $query->orWhereExists(function ($q) use ($pivotTable, $discountTable, $fieldName, $value, $isColumn) {
            $q->from($pivotTable)
                ->whereColumn('parent_id', $discountTable . '.id')
                ->where('field_name', $fieldName);

            if ($isColumn) {
                $q->whereColumn('relation_id', $value);
            } else {
                $q->where('relation_id', $value);
            }
        });
    }

    public function getBestDiscountForBook($bookId, $genreId, $publisherId)
    {
        $discountModel = StreamRecord::inSection('Catalog\Discount')->getModel();
        $discountTable = $discountModel->getTable();
        $pivotTable = $discountModel->books()->getTable();

        return StreamRecord::inSection('Catalog\Discount')
            ->where(function ($query) use ($pivotTable, $discountTable, $bookId, $genreId, $publisherId) {
                if ($bookId)
                    $this->applyPivotCondition($query, $pivotTable, $discountTable, 'books', $bookId);

                if ($genreId)
                    $this->applyPivotCondition($query, $pivotTable, $discountTable, 'genres', $genreId);

                if ($publisherId)
                    $this->applyPivotCondition($query, $pivotTable, $discountTable, 'publishers', $publisherId);

            })
            ->selectRaw("
                MAX(CASE WHEN content_group = 'percentage' THEN discount_pct ELSE NULL END) as max_pct, 
                MIN(CASE WHEN content_group = 'fixed_amount' THEN discount_num ELSE NULL END) as min_price
            ")
            ->reorder()
            ->first();
    }

    public function getAffectedBooksWithDiscounts($discountModel, $bookIds, $genreIds, $publisherIds)
    {
        $discountTable = $discountModel->getTable();
        $pivotTable = $discountModel->books()->getTable();
        $bookTable = EntryRecord::inSection('Catalog\Book')->getModel()->getTable();

        $discountSubquery = StreamRecord::inSection('Catalog\Discount')
            ->where(function ($query) use ($pivotTable, $discountTable, $bookTable) {
                $this->applyPivotCondition($query, $pivotTable, $discountTable, 'books', $bookTable . '.id', true);
                $this->applyPivotCondition($query, $pivotTable, $discountTable, 'genres', $bookTable . '.genre_id', true);
                $this->applyPivotCondition($query, $pivotTable, $discountTable, 'publishers', $bookTable . '.publisher_id', true);
            });

        $affectedBooksQuery = EntryRecord::inSection('Catalog\Book')
            ->select("$bookTable.*")
            ->selectSub(
                (clone $discountSubquery)->where('content_group', 'percentage')->reorder()->selectRaw('MAX(discount_pct)'),
                'max_pct'
            )
            ->selectSub(
                (clone $discountSubquery)->where('content_group', 'fixed_amount')->reorder()->selectRaw('MIN(discount_num)'),
                'min_price'
            )
            ->where(function ($q) use ($bookIds, $genreIds, $publisherIds) {
                if ($bookIds->isNotEmpty())
                    $q->orWhereIn('id', $bookIds);
                if ($genreIds->isNotEmpty())
                    $q->orWhereIn('genre_id', $genreIds);
                if ($publisherIds->isNotEmpty())
                    $q->orWhereIn('publisher_id', $publisherIds);
            });

        return $affectedBooksQuery->get();
    }
}
