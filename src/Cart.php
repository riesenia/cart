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
    protected $items = [];

    /** @var PromotionInterface[] */
    protected $promotions = [];

    /** @var CartContext */
    protected $context;

    /** @var bool */
    protected $pricesWithVat;

    /** @var int */
    protected $roundingDecimals;

    /** @var CartTotals[] */
    protected $totals = [];

    /** @var callable|null */
    protected $totalRounding;

    /** @var array<string,array<string,string>> */
    protected $_bindings;

    /** @var bool */
    protected $_cartModifiedCallback = true;

    /**
     * @param mixed[] $context
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
     * @param mixed[] $context
     */
    public function setContext(array $context): void
    {
        $this->context = new CartContext($this, $context);

        if ($this->items) {
            // reset context on items
            foreach ($this->items as $item) {
                $item->setCartContext($this->context);
            }

            $this->_cartModified();
        }
    }

    public function getContext(): CartContext
    {
        return $this->context;
    }

    public function setPricesWithVat(bool $pricesWithVat): void
    {
        $this->pricesWithVat = $pricesWithVat;

        if ($this->items) {
            $this->_cartModified();
        }
    }

    public function getPricesWithVat(): bool
    {
        return $this->pricesWithVat;
    }

    public function setRoundingDecimals(int $roundingDecimals): void
    {
        if ($roundingDecimals < 0) {
            throw new \RangeException('Invalid value for rounding decimals.');
        }

        $this->roundingDecimals = $roundingDecimals;

        if ($this->items) {
            $this->_cartModified();
        }
    }

    public function getRoundingDecimals(): int
    {
        return $this->roundingDecimals;
    }

    /**
     * @param iterable<PromotionInterface> $promotions
     */
    public function setPromotions(iterable $promotions): void
    {
        $this->promotions = [];

        foreach ($promotions as $promotion) {
            $this->addPromotion($promotion);
        }
    }

    public function addPromotion(PromotionInterface $promotion): void
    {
        $this->promotions[] = $promotion;
    }

    /**
     * @return PromotionInterface[]
     */
    public function getPromotions(): array
    {
        return $this->promotions;
    }

    /**
     * @param string[] $sorting
     */
    public function sortByType(array $sorting): void
    {
        $sorting = \array_flip($sorting);

        \uasort($this->items, function (CartItemInterface $a, CartItemInterface $b) use ($sorting) {
            $aSort = $sorting[$a->getCartType()] ?? 1000;
            $bSort = $sorting[$b->getCartType()] ?? 1000;

            return $aSort <=> $bSort;
        });
    }

    /**
     * Get items.
     *
     * @param callable|string $filter
     *
     * @return CartItemInterface[]
     */
    public function getItems($filter = '~'): array
    {
        return $filter ? \array_filter($this->items, \is_string($filter) ? $this->buildTypeCondition($filter) : $filter) : $this->items;
    }

    /**
     * Get items count.
     *
     * @param callable|string $filter
     */
    public function countItems($filter = '~'): int
    {
        return \count($this->getItems($filter));
    }

    /**
     * Check if cart is empty.
     *
     * @param callable|string $filter
     */
    public function isEmpty($filter = '~'): bool
    {
        return !$this->countItems($filter);
    }

    /**
     * Check if cart has item with given id.
     */
    public function hasItem(string $cartId): bool
    {
        return isset($this->items[$cartId]);
    }

    /**
     * Get item by cart id.
     */
    public function getItem(string $cartId): CartItemInterface
    {
        if (!$this->hasItem($cartId)) {
            throw new \OutOfBoundsException('Requested cart item does not exist.');
        }

        return $this->items[$cartId];
    }

    /**
     * Add item to cart.
     */
    public function addItem(CartItemInterface $item, float $quantity = 1.0): void
    {
        // if already in cart, only modify quantity
        if ($this->hasItem($item->getCartId())) {
            $this->setItemQuantity($item->getCartId(), $this->getItem($item->getCartId())->getCartQuantity() + $quantity);

            return;
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
        $item->setCartContext($this->context);

        $this->items[$item->getCartId()] = $item;
        $this->_cartModified();
    }

    /**
     * Set cart items.
     *
     * @param iterable<CartItemInterface> $items
     */
    public function setItems(iterable $items): void
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
     */
    public function setItemQuantity(string $cartId, float $quantity): void
    {
        if (!$this->hasItem($cartId)) {
            throw new \OutOfBoundsException('Requested cart item does not exist.');
        }

        if (!$quantity) {
            $this->removeItem($cartId);

            return;
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
     */
    public function removeItem(string $cartId): void
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

        unset($this->items[$cartId]);
        $this->_cartModified();
    }

    /**
     * Get item price (with or without VAT based on pricesWithVat setting).
     */
    public function getItemPrice(CartItemInterface $item, float $quantity = null, bool $pricesWithVat = null, int $roundingDecimals = null): Decimal
    {
        $item->setCartContext($this->context);

        return $this->countPrice($item->getUnitPrice(), $item->getTaxRate(), $quantity ?: $item->getCartQuantity(), $pricesWithVat, $roundingDecimals);
    }

    /**
     * Count price.
     */
    public function countPrice(float $unitPrice, float $taxRate, float $quantity = 1, bool $pricesWithVat = null, int $roundingDecimals = null): Decimal
    {
        if ($pricesWithVat === null) {
            $pricesWithVat = $this->pricesWithVat;
        }

        if ($roundingDecimals === null) {
            $roundingDecimals = $this->roundingDecimals;
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
    public function clear(): void
    {
        if ($this->items) {
            $this->items = [];
            $this->_cartModified();
        }
    }

    /**
     * Set total rounding function.
     */
    public function setTotalRounding(?callable $rounding): void
    {
        $this->totalRounding = $rounding;
    }

    /**
     * Get total rounding function.
     */
    public function getTotalRounding(): ?callable
    {
        return $this->totalRounding;
    }

    /**
     * Get totals using filter. If string, uses buildTypeCondition to build filter function.
     *
     * @param callable|string $filter
     */
    public function getTotals($filter = '~'): CartTotals
    {
        $hash = \is_string($filter) ? $filter : \spl_object_hash((object) $filter);

        if (isset($this->totals[$hash])) {
            return $this->totals[$hash];
        }

        if (\is_string($filter)) {
            $filter = $this->buildTypeCondition($filter);
        }

        if (!\is_callable($filter)) {
            throw new \InvalidArgumentException('Filter for getTotals method has to be callable.');
        }

        return $this->totals[$hash] = new CartTotals($this, $filter);
    }

    /**
     * @param callable|string $filter
     */
    public function getSubtotal($filter = '~'): Decimal
    {
        return $this->getTotals($filter)->getSubtotal();
    }

    /**
     * @param callable|string $filter
     */
    public function getTotal($filter = '~'): Decimal
    {
        return $this->getTotals($filter)->getTotal();
    }

    /**
     * @param callable|string $filter
     */
    public function getRoundingAmount($filter = '~'): Decimal
    {
        return $this->getTotals($filter)->getRounding();
    }

    /**
     * @param callable|string $filter
     *
     * @return array<float|int,Decimal>
     */
    public function getTaxes($filter = '~'): array
    {
        return $this->getTotals($filter)->getTaxes();
    }

    /**
     * @param callable|string $filter
     *
     * @return array<float|int,Decimal>
     */
    public function getTaxBases($filter = '~'): array
    {
        return $this->getTotals($filter)->getSubtotals();
    }

    /**
     * @param callable|string $filter
     *
     * @return array<float|int,Decimal>
     */
    public function getTaxTotals($filter = '~'): array
    {
        return $this->getTotals($filter)->getTotals();
    }

    /**
     * @param callable|string $filter
     */
    public function getWeight($filter = '~'): Decimal
    {
        return $this->getTotals($filter)->getWeight();
    }

    /**
     * Build condition for item type.
     *
     * @param string $type
     */
    protected function buildTypeCondition(string $type): callable
    {
        $negative = false;

        if (\strpos($type, '~') === 0) {
            $negative = true;
            $type = \substr($type, 1);
        }

        $type = \explode(',', $type);

        return function (CartItemInterface $item) use ($type, $negative) {
            return $negative ? !\in_array($item->getCartType(), $type) : \in_array($item->getCartType(), $type);
        };
    }

    /**
     * Clear cached totals.
     */
    protected function _cartModified(): void
    {
        if (!$this->_cartModifiedCallback) {
            return;
        }

        $this->totals = [];
        $this->_cartModifiedCallback = false;
        $this->_processPromotions();
        $this->_cartModifiedCallback = true;
    }

    /**
     * Process promotions.
     */
    protected function _processPromotions(): void
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
    protected function _addBinding(string $boundCartId, string $cartId): void
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
    protected function _removeBinding(string $boundCartId, string $cartId): void
    {
        unset($this->_bindings[$cartId][$boundCartId]);

        if (!\count($this->_bindings[$cartId])) {
            unset($this->_bindings[$cartId]);
        }
    }
}
