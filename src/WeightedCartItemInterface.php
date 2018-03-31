<?php
/**
 * This file is part of riesenia/cart package.
 *
 * Licensed under the MIT License
 * (c) RIESENIA.com
 */

declare(strict_types=1);

namespace Riesenia\Cart;

/**
 * Iterface for items in cart with weight.
 *
 * @author Tomas Saghy <segy@riesenia.com>
 */
interface WeightedCartItemInterface extends CartItemInterface
{
    /**
     * Get unit weight.
     *
     * @return float
     */
    public function getWeight(): float;
}
