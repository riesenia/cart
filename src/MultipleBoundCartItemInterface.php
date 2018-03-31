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
 * Iterface for items in cart bound to other cart items.
 *
 * @author Tomas Saghy <segy@riesenia.com>
 */
interface MultipleBoundCartItemInterface extends CartItemInterface
{
    /**
     * Get bound item cart ids.
     *
     * @return string[]
     */
    public function getBoundItemCartIds(): array;
}
