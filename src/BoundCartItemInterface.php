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
 * Iterface for items in cart bound to other cart item.
 *
 * @author Tomas Saghy <segy@riesenia.com>
 */
interface BoundCartItemInterface extends CartItemInterface
{
    /**
     * Get bound item cart id.
     *
     * @return string
     */
    public function getBoundItemCartId(): string;

    /**
     * Update quantity automatically.
     *
     * @return bool
     */
    public function updateCartQuantityAutomatically(): bool;
}
