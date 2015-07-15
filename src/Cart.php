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
     * Check if cart is empty
     *
     * @return bool
     */
    public function isEmpty()
    {
        return !count($this->_items);
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
        return array_filter($this->_items, function ($item) use ($type) {
            return $item->getCartType() == $type;
        });
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
            throw new InvalidArgumentException('Only an array or Traversable is allowed for setItems.');
        }

        $this->clear();

        foreach ($items as $item) {
            if (is_subclass_of($item, 'CartItemInterface')) {
                throw new InvalidArgumentException('All items have to implement CartItemInterface.');
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
     * @return void
     */
    public function getItemPrice(CartItemInterface $item, $quantity = null)
    {
        $price = Decimal::create($item->getUnitPrice());

        // when listed as gross
        if ($this->_pricesWithVat) {
            $price = $price->mul(Decimal::fromFloat(1 + (float)$item->getTaxRate() / 100));
        }

        return $price->mul(Decimal::fromInteger(is_null($quantity) ? $item->getCartQuantity() : (int)$quantity))->round($this->_roundingDecimals);
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

        return $this->_totals[$type]['subtotal'];
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

        return $this->_totals[$type]['total'];
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
     * Calculate totals
     *
     * @param string type
     * @return void
     */
    protected function _calculate($type)
    {
        $totals = [];

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
        }

        $this->_totals[$type] = ['subtotal' => Decimal::fromInteger(0), 'taxes' => [], 'total' => Decimal::fromInteger(0)];

        foreach ($totals as $taxRate => $amount) {
            if ($this->_pricesWithVat) {
                $this->_totals[$type]['total'] = $this->_totals[$type]['total']->add($amount);
                $this->_totals[$type]['taxes'][$taxRate] = $amount->mul(Decimal::fromFloat(1 - 1 / (1 + (float)$taxRate / 100)))->round($this->_roundingDecimals);
                $this->_totals[$type]['subtotal'] = $this->_totals[$type]['subtotal']->add($amount)->sub($this->_totals[$type]['taxes'][$taxRate]);
            } else {
                $this->_totals[$type]['subtotal'] = $this->_totals[$type]['subtotal']->add($amount);
                $this->_totals[$type]['taxes'][$taxRate] = $amount->mul(Decimal::fromFloat((float)$taxRate / 100))->round($this->_roundingDecimals);
                $this->_totals[$type]['total'] = $this->_totals[$type]['total']->add($amount)->add($this->_totals[$type]['taxes'][$taxRate]);
            }
        }
    }

    protected function _typeCondition($itemType, $type)
    {
        if (strpos($type, '~') === 0) {
            $type = explode(',', substr($type, 1));

            return !in_array($itemType, $type);
        }

        $type = explode(',', $type);

        return in_array($itemType, $type);
    }
}
