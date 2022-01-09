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
 * Iterface for items in cart.
 *
 * @author Tomas Saghy <segy@riesenia.com>
 */
interface CartItemInterface
{
    /**
     * Get item identifier.
     */
    public function getCartId(): string;

    /**
     * Get type of the item.
     */
    public function getCartType(): string;

    /**
     * Get name of the item.
     */
    public function getCartName(): string;

    /**
     * Set cart context.
     */
    public function setCartContext(CartContext $context): void;

    /**
     * Set cart quantity.
     */
    public function setCartQuantity(float $quantity): void;

    /**
     * Get cart quantity.
     */
    public function getCartQuantity(): float;

    /**
     * Get unit price.
     */
    public function getUnitPrice(): float;

    /**
     * Get tax rate percentage.
     */
    public function getTaxRate(): float;
}
