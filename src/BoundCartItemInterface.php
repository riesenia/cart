<?php
namespace Riesenia\Cart;

/**
 * Iterface for items in cart bound to other cart item.
 *
 * @author Tomas Saghy <segy@riesenia.com>
 */
interface BoundCartItemInterface extends CartItemInterface
{
    /**
     * Get bound item cart id.
     *
     * @return mixed
     */
    public function getBoundItemCartId();

    /**
     * Update quantity automatically.
     *
     * @return bool
     */
    public function updateCartQuantityAutomatically();
}
