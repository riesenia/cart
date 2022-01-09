<?php
/**
 * This file is part of riesenia/cart package.
 *
 * Licensed under the MIT License
 * (c) RIESENIA.com
 */

declare(strict_types=1);

namespace Riesenia\Cart;

use Litipk\BigNumbers\Decimal;

/**
 * Cart totals.
 *
 * @author Tomas Saghy <segy@riesenia.com>
 */
class CartTotals
{
    /** @var Decimal */
    private $weight;

    /** @var array<float|int,Decimal> */
    private $subtotals = [];

    /** @var array<float|int,Decimal> */
    private $totals = [];

    /** @var array<float|int,Decimal> */
    private $taxes = [];

    public function __construct(Cart $cart, callable $filter)
    {
        $taxTotals = [];
        $this->weight = Decimal::fromInteger(0);

        foreach ($cart->getItems($filter) as $item) {
            $price = $cart->getItemPrice($item);

            if (!isset($taxTotals[$item->getTaxRate()])) {
                $taxTotals[$item->getTaxRate()] = Decimal::fromInteger(0);
            }

            $taxTotals[$item->getTaxRate()] = $taxTotals[$item->getTaxRate()]->add($price);

            // weight
            if ($item instanceof WeightedCartItemInterface) {
                $itemWeight = Decimal::fromFloat($item->getWeight());
                $itemWeight = $itemWeight->mul(Decimal::fromFloat($item->getCartQuantity()));
                $this->weight = $this->weight->add($itemWeight);
            }
        }

        foreach ($taxTotals as $taxRate => $amount) {
            if ($cart->getPricesWithVat()) {
                $this->totals[$taxRate] = $amount;
                $this->taxes[$taxRate] = $amount->mul(Decimal::fromFloat(1 - 1 / (1 + (float) $taxRate / 100)))->round($cart->getRoundingDecimals());
                $this->subtotals[$taxRate] = $amount->sub($this->taxes[$taxRate]);
            } else {
                $this->subtotals[$taxRate] = $amount;
                $this->taxes[$taxRate] = $amount->mul(Decimal::fromFloat((float) $taxRate / 100))->round($cart->getRoundingDecimals());
                $this->totals[$taxRate] = $amount->add($this->taxes[$taxRate]);
            }
        }
    }

    public function getSubtotal(): Decimal
    {
        $subtotal = Decimal::fromInteger(0);

        foreach ($this->subtotals as $item) {
            $subtotal = $subtotal->add($item);
        }

        return $subtotal;
    }

    public function getTotal(): Decimal
    {
        $total = Decimal::fromInteger(0);

        foreach ($this->totals as $item) {
            $total = $total->add($item);
        }

        return $total;
    }

    public function getWeight(): Decimal
    {
        return $this->weight;
    }

    /**
     * @return array<float|int,Decimal>
     */
    public function getSubtotals(): array
    {
        return $this->subtotals;
    }

    /**
     * @return array<float|int,Decimal>
     */
    public function getTotals(): array
    {
        return $this->totals;
    }

    /**
     * @return array<float|int,Decimal>
     */
    public function getTaxes(): array
    {
        return $this->taxes;
    }
}
