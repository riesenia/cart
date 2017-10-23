<?php
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
     * @return mixed
     */
    public function getCartId();

    /**
     * Get type of the item.
     *
     * @return string
     */
    public function getCartType();

    /**
     * Get name of the item.
     *
     * @return string
     */
    public function getCartName();

    /**
     * Set cart context.
     *
     * @param mixed $context
     */
    public function setCartContext($context);

    /**
     * Set cart quantity.
     *
     * @param int $quantity
     */
    public function setCartQuantity($quantity);

    /**
     * Get cart quantity.
     *
     * @return int
     */
    public function getCartQuantity();

    /**
     * Get unit price based on quantity and context.
     *
     * @return float
     */
    public function getUnitPrice();

    /**
     * Get tax rate percentage.
     *
     * @return float
     */
    public function getTaxRate();
}
