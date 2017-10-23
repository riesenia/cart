<?php
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
    public function getWeight();
}
