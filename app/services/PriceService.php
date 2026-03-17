<?php

namespace App\Services;

use Illuminate\Support\Collection;

class PriceService
{
    public function calculateBestOffer(float $basePrice, $discountResults): array
    {
        $bestPrice = $basePrice;
        $bestPercent = 0;

        if (!$discountResults) {
            return ['price' => $bestPrice, 'percent' => $bestPercent];
        }

        // Check percentage discount
        if ($discountResults->max_pct > 0) {
            $pctPercent = $discountResults->max_pct;
            $savings = round($basePrice * ($pctPercent / 100), 0, PHP_ROUND_HALF_EVEN);
            $pctPrice = max(0, $basePrice - $savings);
            
            if ($pctPrice < $bestPrice) {
                $bestPrice = $pctPrice;
                $bestPercent = $pctPercent;
            }
        }

        // Check fixed amount discount
        if ($discountResults->min_price !== null) {
            $fixedPrice = max(0, min($basePrice, $discountResults->min_price));
            
            if ($fixedPrice < $bestPrice) {
                $bestPrice = $fixedPrice;
                $bestPercent = ($basePrice > 0) 
                    ? round((1 - ($fixedPrice / $basePrice)) * 100, 0, PHP_ROUND_HALF_EVEN) 
                    : 0;
            }
        }

        return ['price' => $bestPrice, 'percent' => $bestPercent];
    }
}