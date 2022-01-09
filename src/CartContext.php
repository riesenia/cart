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
 * Cart context.
 *
 * @author Tomas Saghy <segy@riesenia.com>
 */
class CartContext
{
    /** @var Cart */
    protected $cart;

    /** @var mixed[] */
    protected $data;

    /**
     * @param mixed[] $data
     * @param Cart    $cart
     */
    public function __construct(Cart $cart, array $data = [])
    {
        $this->cart = $cart;
        $this->data = $data;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * @return mixed[]
     */
    public function getData(): array
    {
        return $this->data;
    }
}
