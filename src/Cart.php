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

class Cart
{
    /** @var CartItemInterface[] */
    protected $_items = [];

    /** @var PromotionInterface[] */
    protected $_promotions = [];

    /** @var array */
    protected $_context = [];

    /** @var bool */
    protected $_pricesWithVat;

    /** @var int */
    protected $_roundingDecimals;

    /** @var array */
    protected $_totals;

    /** @var array */
    protected $_bindings;

    /** @var bool */
    protected $_cartModifiedCallback = true;

    /**
     * Constructor.
     *
     * @param array $context
     * @param bool  $pricesWithVat
     * @param int   $roundingDecimals
     */
    public function __construct(array $context = [], bool $pricesWithVat = true, int $roundingDecimals = 2)
    {
        $this->setContext($context);
        $this->setPricesWithVat($pricesWithVat);
        $this->setRoundingDecimals($roundingDecimals);
    }

    /**
     * Set context. Context is passed to cart items (i.e. for custom price logic).
     *
     * @param array $context
     */
    public function setContext(array $context)
    {
        $this->_context = $context;

        if ($this->_items) {
            // reset context on items
            foreach ($this->_items as $item) {
                $item->setCartContext($context);
            }

            $this->_cartModified();
        }
    }

    /**
     * Get context.
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->_context;
    }

    /**
     * Set prices with VAT.
     *
     * @param bool $pricesWithVat
     */
    public function setPricesWithVat(bool $pricesWithVat)
    {
        $this->_pricesWithVat = $pricesWithVat;

        if ($this->_items) {
            $this->_cartModified();
        }
    }

    /**
     * Get prices with VAT.
     *
     * @return bool
     */
    public function getPricesWithVat(): bool
    {
        return $this->_pricesWithVat;
    }

    /**
     * Set rounding decimals.
     *
     * @param int $roundingDecimals
     */
    public function setRoundingDecimals(int $roundingDecimals)
    {
        if ($roundingDecimals < 0) {
            throw new \RangeException('Invalid value for rounding decimals.');
        }

        $this->_roundingDecimals = $roundingDecimals;

        if ($this->_items) {
            $this->_cartModified();
        }
    }

    /**
     * Get rounding decimals.
     *
     * @return int
     */
    public function getRoundingDecimals(): int
    {
        return $this->_roundingDecimals;
    }

    /**
     * Set promotions.
     *
     * @param PromotionInterface[] $promotions
     */
    public function setPromotions(array $promotions)
    {
        $this->_promotions = $promotions;
    }

    /**
     * Get promotions.
     *
     * @return PromotionInterface[]
     */
    public function getPromotions(): array
    {
        return $this->_promotions;
    }

    /**
     * Set sorting by type.
     *
     * @param array $sorting
     */
    public function sortByType(array $sorting)
    {
        $sorting = array_flip($sorting);

        uasort($this->_items, function (CartItemInterface $a, CartItemInterface $b) use ($sorting) {
            $aSort = $sorting[$a->getCartType()] ?? 1000;
            $bSort = $sorting[$b->getCartType()] ?? 1000;

            return $aSort <=> $bSort;
        });
    }

    /**
     * Get items.
     *
     * @param callable|null $filter
     *
     * @return CartItemInterface[]
     */
    public function getItems(callable $filter = null): array
    {
        return $filter ? array_filter($this->_items, $filter) : $this->_items;
    }

    /**
     * Get items by type.
     *
     * @param string $type
     *
     * @return CartItemInterface[]
     */
    public function getItemsByType(string $type): array
    {
        return $this->getItems($this->_getTypeCondition($type));
    }

    /**
     * Get items count.
     *
     * @param callable|null $filter
     *
     * @return int
     */
    public function countItems(callable $filter = null): int
    {
        return count($this->getItems($filter));
    }

    /**
     * Get items count by type.
     *
     * @param string $type
     *
     * @return int
     */
    public function countItemsByType(string $type): int
    {
        return $this->countItems($this->_getTypeCondition($type));
    }

    /**
     * Check if cart is empty.
     *
     * @param callable|null $filter
     *
     * @return bool
     */
    public function isEmpty(callable $filter = null): bool
    {
        return !$this->countItems($filter);
    }

    /**
     * Check if cart is empty by tyoe.
     *
     * @param string $type
     *
     * @return bool
     */
    public function isEmptyByType(string $type): bool
    {
        return !$this->countItemsByType($type);
    }

    /**
     * Check if cart has item with id.
     *
     * @param string $cartId
     *
     * @return bool
     */
    public function hasItem(string $cartId): bool
    {
        return isset($this->_items[$cartId]);
    }

    /**
     * Get item by cart id.
     *
     * @param string $cartId
     *
     * @return CartItemInterface
     */
    public function getItem(string $cartId): CartItemInterface
    {
        if (!$this->hasItem($cartId)) {
            throw new \OutOfBoundsException('Requested cart item does not exist.');
        }

        return $this->_items[$cartId];
    }

    /**
     * Add item to cart.
     *
     * @param CartItemInterface $item
     * @param float             $quantity
     */
    public function addItem(CartItemInterface $item, float $quantity = 1)
    {
        if ($this->hasItem($item->getCartId())) {
            $quantity += $this->getItem($item->getCartId())->getCartQuantity();
        }

        // bound item
        if ($item instanceof BoundCartItemInterface) {
            $this->_addBinding($item->getCartId(), $item->getBoundItemCartId());

            // set quantity automatically
            if ($item->updateCartQuantityAutomatically()) {
                $quantity = $this->getItem($item->getBoundItemCartId())->getCartQuantity();
            }
        }

        // multiple bound item
        if ($item instanceof MultipleBoundCartItemInterface) {
            foreach ($item->getBoundItemCartIds() as $bindingId) {
                $this->_addBinding($item->getCartId(), $bindingId);
            }
        }

        $item->setCartQuantity($quantity);
        $item->setCartContext($this->_context);

        $this->_items[$item->getCartId()] = $item;
        $this->_cartModified();
    }

    /**
     * Set cart items.
     *
     * @param CartItemInterface[] $items
     */
    public function setItems(iterable $items)
    {
        $this->_cartModifiedCallback = false;
        $this->clear();

        foreach ($items as $item) {
            $this->addItem($item, $item->getCartQuantity());
        }

        $this->_cartModifiedCallback = true;
        $this->_cartModified();
    }

    /**
     * Set item quantity by cart id.
     *
     * @param string $cartId
     * @param float  $quantity
     */
    public function setItemQuantity(string $cartId, float $quantity)
    {
        if (!$this->hasItem($cartId)) {
            throw new \OutOfBoundsException('Requested cart item does not exist.');
        }

        if ($quantity <= 0) {
            return $this->removeItem($cartId);
        }

        $item = $this->getItem($cartId);

        if ($item->getCartQuantity() != $quantity) {
            $item->setCartQuantity($quantity);

            // set bound item quantity
            if (isset($this->_bindings[$cartId])) {
                foreach ($this->_bindings[$cartId] as $boundCartId) {
                    $item = $this->getItem($boundCartId);

                    if ($item instanceof BoundCartItemInterface && $item->updateCartQuantityAutomatically()) {
                        $item->setCartQuantity($quantity);
                    }
                }
            }

            $this->_cartModified();
        }
    }

    /**
     * Remove item by cart id.
     *
     * @param string $cartId
     */
    public function removeItem(string $cartId)
    {
        if (!$this->hasItem($cartId)) {
            throw new \OutOfBoundsException('Requested cart item does not exist.');
        }

        // remove bound item
        if (isset($this->_bindings[$cartId])) {
            foreach ($this->_bindings[$cartId] as $boundCartId) {
                $this->removeItem($boundCartId);
            }
        }

        // remove binding
        if ($this->getItem($cartId) instanceof BoundCartItemInterface) {
            $this->_removeBinding($cartId, $this->getItem($cartId)->getBoundItemCartId());
        }

        // remove multiple bindings
        if ($this->getItem($cartId) instanceof MultipleBoundCartItemInterface) {
            foreach ($this->getItem($cartId)->getBoundItemCartIds() as $bindingId) {
                $this->_removeBinding($cartId, $bindingId);
            }
        }

        unset($this->_items[$cartId]);
        $this->_cartModified();
    }

    /**
     * Get item price (with or without VAT based on _pricesWithVat setting).
     *
     * @param CartItemInterface $item
     * @param float|null        $quantity         null to use item quantity
     * @param bool|null         $pricesWithVat    null to use cart default
     * @param int|null          $roundingDecimals null to use cart default
     *
     * @return Decimal
     */
    public function getItemPrice(CartItemInterface $item, float $quantity = null, bool $pricesWithVat = null, int $roundingDecimals = null): Decimal
    {
        $item->setCartContext($this->_context);

        return $this->countPrice($item->getUnitPrice(), $item->getTaxRate(), $quantity ?: $item->getCartQuantity(), $pricesWithVat, $roundingDecimals);
    }

    /**
     * Count price.
     *
     * @param float     $unitPrice
     * @param float     $taxRate
     * @param float     $quantity
     * @param bool|null $pricesWithVat    null to use cart default
     * @param int|null  $roundingDecimals null to use cart default
     *
     * @return Decimal
     */
    public function countPrice(float $unitPrice, float $taxRate, float $quantity = 1, bool $pricesWithVat = null, int $roundingDecimals = null): Decimal
    {
        if ($pricesWithVat === null) {
            $pricesWithVat = $this->_pricesWithVat;
        }

        if ($roundingDecimals === null) {
            $roundingDecimals = $this->_roundingDecimals;
        }

        $price = Decimal::fromFloat($unitPrice);

        if ($pricesWithVat) {
            $price = $price->mul(Decimal::fromFloat(1 + $taxRate / 100));
        }

        return $price->round($roundingDecimals)->mul(Decimal::fromFloat($quantity));
    }

    /**
     * Clear cart contents.
     */
    public function clear()
    {
        if ($this->_items) {
            $this->_items = [];
            $this->_cartModified();
        }
    }

    /**
     * Get totals using filter.
     * If filter is string, uses _getTypeCondition to build filter function.
     *
     * @param callable|string $filter
     *
     * @return array
     */
    public function getTotals($filter = '~'): array
    {
        $store = false;

        if (is_string($filter)) {
            $store = $filter;

            if (isset($this->_totals[$store])) {
                return $this->_totals[$store];
            }

            $filter = $this->_getTypeCondition($filter);
        }

        if (!is_callable($filter)) {
            throw new \InvalidArgumentException('Filter for getTotals method has to be callable.');
        }

        $totals = $this->_calculateTotals($filter);

        if ($store) {
            $this->_totals[$store] = $totals;
        }

        return $totals;
    }

    /**
     * Get subtotal.
     *
     * @param callable|string $type
     *
     * @return Decimal
     */
    public function getSubtotal($type = '~'): Decimal
    {
        $subtotal = Decimal::fromInteger(0);

        foreach ($this->getTotals($type)['subtotals'] as $item) {
            $subtotal = $subtotal->add($item);
        }

        return $subtotal;
    }

    /**
     * Get total.
     *
     * @param callable|string $type
     *
     * @return Decimal
     */
    public function getTotal($type = '~'): Decimal
    {
        $total = Decimal::fromInteger(0);

        foreach ($this->getTotals($type)['totals'] as $item) {
            $total = $total->add($item);
        }

        return $total;
    }

    /**
     * Get taxes.
     *
     * @param callable|string $type
     *
     * @return Decimal[]
     */
    public function getTaxes($type = '~'): array
    {
        return $this->getTotals($type)['taxes'];
    }

    /**
     * Get tax bases.
     *
     * @param callable|string $type
     *
     * @return Decimal[]
     */
    public function getTaxBases($type = '~'): array
    {
        return $this->getTotals($type)['subtotals'];
    }

    /**
     * Get tax totals.
     *
     * @param callable|string $type
     *
     * @return Decimal[]
     */
    public function getTaxTotals($type = '~'): array
    {
        return $this->getTotals($type)['totals'];
    }

    /**
     * Get weight.
     *
     * @param callable|string $type
     *
     * @return Decimal
     */
    public function getWeight($type = '~'): Decimal
    {
        return $this->getTotals($type)['weight'];
    }

    /**
     * Calculate totals.
     *
     * @param callable $filter
     *
     * @return array
     */
    protected function _calculateTotals($filter): array
    {
        if (!is_callable($filter)) {
            throw new \InvalidArgumentException('Filter for _calculateTotals method has to be callable.');
        }

        $taxTotals = [];
        $weight = Decimal::fromInteger(0);

        foreach ($this->getItems($filter) as $item) {
            $price = $this->getItemPrice($item);

            if (!isset($taxTotals[$item->getTaxRate()])) {
                $taxTotals[$item->getTaxRate()] = Decimal::fromInteger(0);
            }

            $taxTotals[$item->getTaxRate()] = $taxTotals[$item->getTaxRate()]->add($price);

            // weight
            if ($item instanceof WeightedCartItemInterface) {
                $itemWeight = Decimal::fromFloat($item->getWeight());
                $itemWeight = $itemWeight->mul(Decimal::fromFloat($item->getCartQuantity()));
                $weight = $weight->add($itemWeight);
            }
        }

        $totals = ['subtotals' => [], 'taxes' => [], 'totals' => [], 'weight' => $weight->round(6)];

        foreach ($taxTotals as $taxRate => $amount) {
            if ($this->_pricesWithVat) {
                $totals['totals'][$taxRate] = $amount;
                $totals['taxes'][$taxRate] = $amount->mul(Decimal::fromFloat(1 - 1 / (1 + (float) $taxRate / 100)))->round($this->_roundingDecimals);
                $totals['subtotals'][$taxRate] = $amount->sub($totals['taxes'][$taxRate]);
            } else {
                $totals['subtotals'][$taxRate] = $amount;
                $totals['taxes'][$taxRate] = $amount->mul(Decimal::fromFloat((float) $taxRate / 100))->round($this->_roundingDecimals);
                $totals['totals'][$taxRate] = $amount->add($totals['taxes'][$taxRate]);
            }
        }

        return $totals;
    }

    /**
     * Build condition for item type.
     *
     * @param string $type
     *
     * @return callable
     */
    protected function _getTypeCondition(string $type): callable
    {
        $negative = false;

        if (strpos($type, '~') === 0) {
            $negative = true;
            $type = substr($type, 1);
        }

        $type = explode(',', $type);

        return function (CartItemInterface $item) use ($type, $negative) {
            return $negative ? !in_array($item->getCartType(), $type) : in_array($item->getCartType(), $type);
        };
    }

    /**
     * Clear cached totals.
     */
    protected function _cartModified()
    {
        if (!$this->_cartModifiedCallback) {
            return;
        }

        $this->_totals = [];
        $this->_cartModifiedCallback = false;
        $this->_processPromotions();
        $this->_cartModifiedCallback = true;
    }

    /**
     * Process promotions.
     */
    protected function _processPromotions()
    {
        $promotions = $this->getPromotions();

        // before apply
        foreach ($promotions as $promotion) {
            $promotion->beforeApply($this);
        }

        // apply
        foreach ($promotions as $promotion) {
            if ($promotion->isEligible($this)) {
                $promotion->apply($this);
            }
        }

        // after apply
        foreach ($promotions as $promotion) {
            $promotion->afterApply($this);
        }
    }

    /**
     * Add binding.
     *
     * @param string $boundCartId bound item id
     * @param string $cartId      target item id
     */
    protected function _addBinding(string $boundCartId, string $cartId)
    {
        if (!$this->hasItem($cartId)) {
            throw new \OutOfBoundsException('Target cart item does not exist.');
        }

        if (!isset($this->_bindings[$cartId])) {
            $this->_bindings[$cartId] = [];
        }

        $this->_bindings[$cartId][$boundCartId] = $boundCartId;
    }

    /**
     * Remove binding.
     *
     * @param string $boundCartId bound item id
     * @param string $cartId      target item id
     */
    protected function _removeBinding(string $boundCartId, string $cartId)
    {
        unset($this->_bindings[$cartId][$boundCartId]);

        if (!count($this->_bindings[$cartId])) {
            unset($this->_bindings[$cartId]);
        }
    }
}
