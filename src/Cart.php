<?php
namespace Riesenia\Cart;

use Litipk\BigNumbers\Decimal;

class Cart
{
    /**
     * Cart items.
     *
     * @var array
     */
    protected $_items = [];

    /**
     * Cart promotions.
     *
     * @var array
     */
    protected $_promotions = [];

    /**
     * Context data.
     *
     * @var mixed
     */
    protected $_context;

    /**
     * If prices are listed as gross.
     *
     * @var bool
     */
    protected $_pricesWithVat;

    /**
     * Rounding decimals.
     *
     * @var int
     */
    protected $_roundingDecimals;

    /**
     * Totals.
     *
     * @var array
     */
    protected $_totals;

    /**
     * Bindings.
     *
     * @var array
     */
    protected $_bindings;

    /**
     * Active _cartModified callback.
     *
     * @var bool
     */
    protected $_cartModifiedCallback = true;

    /**
     * Constructor.
     *
     * @param mixed|null $context          data passed to cart items for custom price logic
     * @param bool       $pricesWithVat    true if prices are listed as gross
     * @param int        $roundingDecimals
     */
    public function __construct($context = null, $pricesWithVat = true, $roundingDecimals = 2)
    {
        $this->setContext($context);
        $this->setPricesWithVat($pricesWithVat);
        $this->setRoundingDecimals($roundingDecimals);
    }

    /**
     * Set context.
     *
     * @param mixed $context data passed to cart items for custom price logic
     */
    public function setContext($context)
    {
        $this->_context = $context;

        if ($this->_items) {
            $this->_cartModified();
        }
    }

    /**
     * Get context.
     *
     * @return mixed context data
     */
    public function getContext()
    {
        return $this->_context;
    }

    /**
     * Set prices with VAT.
     *
     * @param bool $pricesWithVat true if prices are listed as gross
     */
    public function setPricesWithVat($pricesWithVat)
    {
        $this->_pricesWithVat = (bool) $pricesWithVat;

        if ($this->_items) {
            $this->_cartModified();
        }
    }

    /**
     * Get prices with VAT.
     *
     * @return bool
     */
    public function getPricesWithVat()
    {
        return $this->_pricesWithVat;
    }

    /**
     * Set rounding decimals.
     *
     * @param int $roundingDecimals
     */
    public function setRoundingDecimals($roundingDecimals)
    {
        $roundingDecimals = (int) $roundingDecimals;

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
    public function getRoundingDecimals()
    {
        return $this->_roundingDecimals;
    }

    /**
     * Set promotions.
     *
     * @param array $promotions
     */
    public function setPromotions(array $promotions)
    {
        $this->_promotions = $promotions;
    }

    /**
     * Get promotions.
     *
     * @return array
     */
    public function getPromotions()
    {
        return $this->_promotions;
    }

    /**
     * Set sorting by type.
     *
     * @param array $sorting
     */
    public function sortByType($sorting)
    {
        $sorting = array_flip($sorting);

        uasort($this->_items, function (CartItemInterface $a, CartItemInterface $b) use ($sorting) {
            $aSort = isset($sorting[$a->getCartType()]) ? $sorting[$a->getCartType()] : 1000;
            $bSort = isset($sorting[$b->getCartType()]) ? $sorting[$b->getCartType()] : 1000;

            return $aSort <=> $bSort;
        });
    }

    /**
     * Check if cart is empty.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return !count($this->getItems());
    }

    /**
     * Check if cart is empty by tyoe.
     *
     * @param string $type
     *
     * @return bool
     */
    public function isEmptyByType($type)
    {
        return !count($this->getItemsByType($type));
    }

    /**
     * Get items count.
     *
     * @param callable|null $filter
     *
     * @return int
     */
    public function countItems($filter = null)
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
    public function countItemsByType($type)
    {
        return $this->countItems($this->_getTypeCondition($type));
    }

    /**
     * Get items.
     *
     * @param callable|null $filter
     *
     * @return array
     */
    public function getItems($filter = null)
    {
        if ($filter && !is_callable($filter)) {
            throw new \InvalidArgumentException('Filter for getItems method has to be callable.');
        }

        return $filter ? array_filter($this->_items, $filter) : $this->_items;
    }

    /**
     * Get items by type.
     *
     * @param string $type
     *
     * @return array
     */
    public function getItemsByType($type)
    {
        return $this->getItems($this->_getTypeCondition($type));
    }

    /**
     * Check if cart has item with id.
     *
     * @param mixed $cartId
     *
     * @return bool
     */
    public function hasItem($cartId)
    {
        return isset($this->_items[$cartId]);
    }

    /**
     * Get item by cart id.
     *
     * @param mixed $cartId
     *
     * @return CartItemInterface
     */
    public function getItem($cartId)
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
     * @param int               $quantity
     */
    public function addItem(CartItemInterface $item, $quantity = 1)
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
     * @param array|Traversable $items
     */
    public function setItems($items)
    {
        if (!is_array($items) && !$items instanceof \Traversable) {
            throw new \InvalidArgumentException('Only an array or Traversable is allowed for setItems.');
        }

        $this->_cartModifiedCallback = false;
        $this->clear();

        foreach ($items as $item) {
            if (!$item instanceof CartItemInterface) {
                throw new \InvalidArgumentException('All items have to implement CartItemInterface.');
            }

            $this->addItem($item, $item->getCartQuantity());
        }

        $this->_cartModifiedCallback = true;
        $this->_cartModified();
    }

    /**
     * Remove item by cart id.
     *
     * @param mixed $cartId
     */
    public function removeItem($cartId)
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
     * Set item quantity by cart id.
     *
     * @param mixed $cartId
     * @param int   $quantity
     */
    public function setItemQuantity($cartId, $quantity)
    {
        if (!$this->hasItem($cartId)) {
            throw new \OutOfBoundsException('Requested cart item does not exist.');
        }

        $quantity = (int) $quantity;

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
     * Get item price (with or without VAT based on _pricesWithVat setting).
     *
     * @param CartItemInterface $item
     * @param int|null          $quantity         null to use item quantity
     * @param bool|null         $pricesWithVat    null to use cart default
     * @param int|null          $roundingDecimals null to use cart default
     *
     * @return Decimal
     */
    public function getItemPrice(CartItemInterface $item, $quantity = null, $pricesWithVat = null, $roundingDecimals = null)
    {
        $item->setCartContext($this->_context);

        return $this->countPrice($item->getUnitPrice(), $item->getTaxRate(), $quantity ?: $item->getCartQuantity(), $pricesWithVat, $roundingDecimals);
    }

    /**
     * Count price.
     *
     * @param float     $unitPrice        unit price without VAT
     * @param float     $taxRate
     * @param int       $quantity
     * @param bool|null $pricesWithVat    null to use cart default
     * @param int|null  $roundingDecimals null to use cart default
     *
     * @return Decimal
     */
    public function countPrice($unitPrice, $taxRate, $quantity = 1, $pricesWithVat = null, $roundingDecimals = null)
    {
        if ($pricesWithVat === null) {
            $pricesWithVat = $this->_pricesWithVat;
        }

        if ($roundingDecimals === null) {
            $roundingDecimals = $this->_roundingDecimals;
        }

        $price = Decimal::fromFloat((float) $unitPrice);

        if ($pricesWithVat) {
            $price = $price->mul(Decimal::fromFloat(1 + (float) $taxRate / 100));
        }

        return $price->round($roundingDecimals)->mul(Decimal::fromInteger((int) $quantity));
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
    public function getTotals($filter = '~')
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
    public function getSubtotal($type = '~')
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
    public function getTotal($type = '~')
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
     * @return array
     */
    public function getTaxes($type = '~')
    {
        return $this->getTotals($type)['taxes'];
    }

    /**
     * Get tax bases.
     *
     * @param callable|string $type
     *
     * @return array
     */
    public function getTaxBases($type = '~')
    {
        return $this->getTotals($type)['subtotals'];
    }

    /**
     * Get tax totals.
     *
     * @param callable|string $type
     *
     * @return array
     */
    public function getTaxTotals($type = '~')
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
    public function getWeight($type = '~')
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
    protected function _calculateTotals($filter)
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
                $itemWeight = Decimal::fromFloat((float) $item->getWeight());
                $itemWeight = $itemWeight->mul(Decimal::fromInteger((int) $item->getCartQuantity()));
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
     * @return Closure
     */
    protected function _getTypeCondition($type)
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

        $this->_totals = null;
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
     * @param mixed $boundCartId bound item id
     * @param mixed $cartId      target item id
     */
    protected function _addBinding($boundCartId, $cartId)
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
     * @param mixed $boundCartId bound item id
     * @param mixed $cartId      target item id
     */
    protected function _removeBinding($boundCartId, $cartId)
    {
        unset($this->_bindings[$cartId][$boundCartId]);

        if (!count($this->_bindings[$cartId])) {
            unset($this->_bindings[$cartId]);
        }
    }
}
