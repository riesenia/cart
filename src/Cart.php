<?php
namespace Riesenia\Cart;

use Litipk\BigNumbers\Decimal;

class Cart
{
    /**
     * Cart items
     *
     * @var array
     */
    protected $_items = [];

    /**
     * Context data
     *
     * @var mixed
     */
    protected $_context;

    /**
     * If prices are listed as gross
     *
     * @var bool
     */
    protected $_pricesWithVat;

    /**
     * Rounding decimals
     *
     * @var int
     */
    protected $_roundingDecimals;

    /**
     * Totals
     *
     * @var array
     */
    protected $_totals;

    /**
     * Sorting by type
     *
     * @var array
     */
    protected $_sortByType;

    /**
     * Constructor
     *
     * @param mixed context data (passed to cart items for custom price logic)
     * @param bool true if prices are listed as gross
     * @param int number of decimals used for rounding
     */
    public function __construct($context = null, $pricesWithVat = true, $roundingDecimals = 2)
    {
        $this->setContext($context);
        $this->setPricesWithVat($pricesWithVat);
        $this->setRoundingDecimals($roundingDecimals);
    }

    /**
     * Set context
     *
     * @param mixed context data (passed to cart items for custom price logic)
     * @return void
     */
    public function setContext($context)
    {
        $this->_context = $context;
        $this->_totals = null;
    }

    /**
     * Set prices with VAT
     *
     * @param bool true if prices are listed as gross
     * @return void
     */
    public function setPricesWithVat($pricesWithVat)
    {
        $this->_pricesWithVat = (bool)$pricesWithVat;
        $this->_totals = null;
    }

    /**
     * Set rounding decimals
     *
     * @param bool true if prices are listed as gross
     * @return void
     */
    public function setRoundingDecimals($roundingDecimals)
    {
        $roundingDecimals = (int)$roundingDecimals;

        if ($roundingDecimals < 0) {
            throw new \RangeException('Invalid value for rounding decimals.');
        }

        $this->_roundingDecimals = $roundingDecimals;
        $this->_totals = null;
    }

    /**
     * Set sorting by type
     *
     * @param array
     * @return void
     */
    public function sortByType($sorting)
    {
        $this->_sortByType = array_flip($sorting);
        uasort($this->_items, [$this, '_sortCompare']);
    }

    /**
     * Check if cart is empty
     *
     * @return bool
     */
    public function isEmpty()
    {
        return !count($this->_items);
    }

    /**
     * Check if cart is empty by tyoe
     *
     * @param string type
     * @return bool
     */
    public function isEmptyByType($type)
    {
        return !count($this->getItemsByType($type));
    }

    /**
     * Get items count
     *
     * @return int
     */
    public function countItems()
    {
        return count($this->_items);
    }

    /**
     * Get items count by type
     *
     * @param string type
     * @return int
     */
    public function countItemsByType($type)
    {
        return count($this->getItemsByType($type));
    }

    /**
     * Get items
     *
     * @return array
     */
    public function getItems()
    {
        return $this->_items;
    }

    /**
     * Get items by type
     *
     * @param string type
     * @return array
     */
    public function getItemsByType($type)
    {
        $items = [];

        foreach ($this->getItems() as $id => $item) {
            if ($this->_typeCondition($item->getCartType(), $type)) {
                $items[$id] = $item;
            }
        }

        return $items;
    }

    /**
     * Check if cart has item with id
     *
     * @return bool
     */
    public function hasItem($cartId)
    {
        return isset($this->_items[$cartId]);
    }

    /**
     * Get item by cart id
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
     * Add item to cart
     *
     * @param CartItemInterface item
     * @param int quantity
     * @return void
     */
    public function addItem(CartItemInterface $item, $quantity = 1)
    {
        if (isset($this->_items[$item->getCartId()])) {
            $quantity += $this->_items[$item->getCartId()]->getCartQuantity();
        }

        $item->setCartQuantity($quantity);
        $item->setCartContext($this->_context);
        $this->_items[$item->getCartId()] = $item;

        $this->_totals = null;
        uasort($this->_items, [$this, '_sortCompare']);
    }

    /**
     * Set cart items
     *
     * @param array|Traversable items
     * @return void
     */
    public function setItems($items)
    {
        if (!is_array($items) && !$items instanceof \Traversable) {
            throw new \InvalidArgumentException('Only an array or Traversable is allowed for setItems.');
        }

        $this->clear();

        foreach ($items as $item) {
            if (!$item instanceof CartItemInterface) {
                throw new \InvalidArgumentException('All items have to implement CartItemInterface.');
            }

            $this->addItem($item, $item->getCartQuantity());
        }
    }

    /**
     * Remove item by cart id
     *
     * @param mixed cart id
     * @return void
     */
    public function removeItem($cartId)
    {
        if (!$this->hasItem($cartId)) {
            throw new \OutOfBoundsException('Requested cart item does not exist.');
        }

        unset($this->_items[$cartId]);
        $this->_totals = null;
    }

    /**
     * Set item quantity by cart id
     *
     * @param mixed cart id
     * @param int quantity
     * @return void
     */
    public function setItemQuantity($cartId, $quantity)
    {
        if (!$this->hasItem($cartId)) {
            throw new \OutOfBoundsException('Requested cart item does not exist.');
        }

        $quantity = (int)$quantity;

        if ($quantity <= 0) {
            $this->removeItem($cartId);

            return;
        }

        $this->getItem($cartId)->setCartQuantity($quantity);
        $this->_totals = null;
    }

    /**
     * Get item price (with or without VAT based on _pricesWithVat setting)
     *
     * @param CartItemInterface item
     * @param int quantity (null to use item quantity)
     * @return \Litipk\BigNumbers\Decimal
     */
    public function getItemPrice(CartItemInterface $item, $quantity = null)
    {
        $item->setCartContext($this->_context);

        return $this->countPrice($item->getUnitPrice(), $item->getTaxRate(), $quantity ?: $item->getCartQuantity());
    }

    /**
     * Count price
     *
     * @param float unit price (without VAT)
     * @param float tax rate
     * @param int quantity
     * @param bool count price with VAT (null to use cart default)
     * @param int rounding decimals (null to use cart default)
     * @return \Litipk\BigNumbers\Decimal
     */
    public function countPrice($unitPrice, $taxRate, $quantity = 1, $pricesWithVat = null, $roundingDecimals = null)
    {
        if (is_null($pricesWithVat)) {
            $pricesWithVat = $this->_pricesWithVat;
        }

        if (is_null($roundingDecimals)) {
            $roundingDecimals = $this->_roundingDecimals;
        }

        $price = Decimal::fromFloat((float)$unitPrice);

        if ($pricesWithVat) {
            $price = $price->mul(Decimal::fromFloat(1 + (float)$taxRate / 100));
        }

        return $price->round($roundingDecimals)->mul(Decimal::fromInteger((int)$quantity));
    }

    /**
     * Clear cart contents
     *
     * @return void
     */
    public function clear()
    {
        $this->_items = [];
        $this->_totals = null;
    }

    /**
     * Get subtotal
     *
     * @param string type
     * @return \Litipk\BigNumbers\Decimal
     */
    public function getSubtotal($type = '~')
    {
        if (!isset($this->_totals[$type])) {
            $this->_calculate($type);
        }

        $subtotal = Decimal::fromInteger(0);

        foreach ($this->_totals[$type]['subtotals'] as $item) {
            $subtotal = $subtotal->add($item);
        }

        return $subtotal;
    }

    /**
     * Get total
     *
     * @param string type
     * @return \Litipk\BigNumbers\Decimal
     */
    public function getTotal($type = '~')
    {
        if (!isset($this->_totals[$type])) {
            $this->_calculate($type);
        }

        $total = Decimal::fromInteger(0);

        foreach ($this->_totals[$type]['totals'] as $item) {
            $total = $total->add($item);
        }

        return $total;
    }

    /**
     * Get taxes
     *
     * @param string type
     * @return array
     */
    public function getTaxes($type = '~')
    {
        if (!isset($this->_totals[$type])) {
            $this->_calculate($type);
        }

        return $this->_totals[$type]['taxes'];
    }

    /**
     * Get tax bases
     *
     * @param string type
     * @return array
     */
    public function getTaxBases($type = '~')
    {
        if (!isset($this->_totals[$type])) {
            $this->_calculate($type);
        }

        return $this->_totals[$type]['subtotals'];
    }

    /**
     * Get tax totals
     *
     * @param string type
     * @return array
     */
    public function getTaxTotals($type = '~')
    {
        if (!isset($this->_totals[$type])) {
            $this->_calculate($type);
        }

        return $this->_totals[$type]['totals'];
    }

    /**
     * Get weight
     *
     * @param string type
     * @return \Litipk\BigNumbers\Decimal
     */
    public function getWeight($type = '~')
    {
        if (!isset($this->_totals[$type])) {
            $this->_calculate($type);
        }

        return $this->_totals[$type]['weight'];
    }

    /**
     * Calculate totals
     *
     * @param string type
     * @return void
     */
    protected function _calculate($type)
    {
        $totals = [];
        $weight = Decimal::fromInteger(0);

        foreach ($this->_items as $item) {
            // test type
            if (!$this->_typeCondition($item->getCartType(), $type)) {
                continue;
            }

            $price = $this->getItemPrice($item);

            if (!isset($totals[$item->getTaxRate()])) {
                $totals[$item->getTaxRate()] = Decimal::fromInteger(0);
            }

            $totals[$item->getTaxRate()] = $totals[$item->getTaxRate()]->add($price);

            // weight
            if ($item instanceof WeightedCartItemInterface) {
                $itemWeight = Decimal::fromFloat((float)$item->getWeight());
                $itemWeight = $itemWeight->mul(Decimal::fromInteger((int)$item->getCartQuantity()));
                $weight = $weight->add($itemWeight);
            }
        }

        $this->_totals[$type] = ['subtotals' => [], 'taxes' => [], 'totals' => [], 'weight' => $weight->round(6)];

        foreach ($totals as $taxRate => $amount) {
            if ($this->_pricesWithVat) {
                $this->_totals[$type]['totals'][$taxRate] = $amount;
                $this->_totals[$type]['taxes'][$taxRate] = $amount->mul(Decimal::fromFloat(1 - 1 / (1 + (float)$taxRate / 100)))->round($this->_roundingDecimals);
                $this->_totals[$type]['subtotals'][$taxRate] = $amount->sub($this->_totals[$type]['taxes'][$taxRate]);
            } else {
                $this->_totals[$type]['subtotals'][$taxRate] = $amount;
                $this->_totals[$type]['taxes'][$taxRate] = $amount->mul(Decimal::fromFloat((float)$taxRate / 100))->round($this->_roundingDecimals);
                $this->_totals[$type]['totals'][$taxRate] = $amount->add($this->_totals[$type]['taxes'][$taxRate]);
            }
        }
    }

    /**
     * Validate item type against condition
     *
     * @param string item type
     * @param string type condition
     */
    protected function _typeCondition($itemType, $type)
    {
        if (strpos($type, '~') === 0) {
            $type = explode(',', substr($type, 1));

            return !in_array($itemType, $type);
        }

        $type = explode(',', $type);

        return in_array($itemType, $type);
    }

    /**
     * Sort items compare function
     *
     * @param CartItemInterface
     * @param CartItemInterface
     * @return int
     */
    protected function _sortCompare(CartItemInterface $a, CartItemInterface $b)
    {
        $aSort = isset($this->_sortByType[$a->getCartType()]) ? $this->_sortByType[$a->getCartType()] : 1000;
        $bSort = isset($this->_sortByType[$b->getCartType()]) ? $this->_sortByType[$b->getCartType()] : 1000;

        return ($aSort < $bSort) ? -1 : 1;
    }
}
