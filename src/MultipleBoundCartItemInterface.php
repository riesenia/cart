<?php
namespace Riesenia\Cart;

/**
 * Iterface for items in cart bound to other cart items.
 *
 * @author Tomas Saghy <segy@riesenia.com>
 */
interface MultipleBoundCartItemInterface extends CartItemInterface
{
    /**
     * Get bound item cart ids.
     *
     * @return array
     */
    public function getBoundItemCartIds();
}
