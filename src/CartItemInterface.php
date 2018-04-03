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
     *
     * @return string
     */
    public function getCartId(): string;

    /**
     * Get type of the item.
     *
     * @return string
     */
    public function getCartType(): string;

    /**
     * Get name of the item.
     *
     * @return string
     */
    public function getCartName(): string;

    /**
     * Set cart context.
     *
     * @param array $context
     */
    public function setCartContext(array $context);

    /**
     * Set cart quantity.
     *
     * @param float $quantity
     */
    public function setCartQuantity(float $quantity);

    /**
     * Get cart quantity.
     *
     * @return float
     */
    public function getCartQuantity(): float;

    /**
     * Get unit price based on quantity and context.
     *
     * @return float
     */
    public function getUnitPrice(): float;

    /**
     * Get tax rate percentage.
     *
     * @return float
     */
    public function getTaxRate(): float;
}
