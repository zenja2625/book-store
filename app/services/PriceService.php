<?php

namespace App\Services;

class PriceService
{
    public function calculateBestOffer(float $base_price, $discount_results): array
    {
        $best_price = $base_price;
        $best_percent = 0;

        if (!$discount_results) {
            return ['price' => $best_price, 'percent' => $best_percent];
        }

        if ($discount_results->max_pct > 0) {
            $pct_percent = $discount_results->max_pct;
            $savings = round($base_price * ($pct_percent / 100), 0, PHP_ROUND_HALF_EVEN);
            $pct_price = max(0, $base_price - $savings);
            
            if ($pct_price < $best_price) {
                $best_price = $pct_price;
                $best_percent = $pct_percent;
            }
        }

        if ($discount_results->min_price !== null) {
            $fixed_price = max(0, min($base_price, $discount_results->min_price));
            
            if ($fixed_price < $best_price) {
                $best_price = $fixed_price;
                $best_percent = ($base_price > 0) 
                    ? round((1 - ($fixed_price / $base_price)) * 100, 0, PHP_ROUND_HALF_EVEN) 
                    : 0;
            }
        }

        return ['price' => $best_price, 'percent' => $best_percent];
    }
}