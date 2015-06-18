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
    }

    /**
     * Clear cart contents
     *
     * @return void
     */
    public function clear()
    {
        $this->_items = [];
    }

    /**
     * Get subtotal
     *
     * @return \Litipk\BigNumbers\Decimal
     */
    public function getSubtotal()
    {
        if (is_null($this->_totals)) {
            $this->_calculate();
        }

        return $this->_totals['subtotal'];
    }

    /**
     * Get total
     *
     * @return \Litipk\BigNumbers\Decimal
     */
    public function getTotal()
    {
        if (is_null($this->_totals)) {
            $this->_calculate();
        }

        return $this->_totals['total'];
    }

    /**
     * Get taxes
     *
     * @return array
     */
    public function getTaxes()
    {
        if (is_null($this->_totals)) {
            $this->_calculate();
        }

        return $this->_totals['taxes'];
    }

    /**
     * Calculate totals
     *
     * @return void
     */
    protected function _calculate()
    {
        $totals = [];

        foreach ($this->_items as $item) {
            $price = Decimal::create($item->getUnitPrice());

            // when listed as gross
            if ($this->_pricesWithVat) {
                $price = $price->mul(Decimal::fromFloat(1 + $item->getTaxRate() / 100));
            }

            // count price
            $price = $price->mul(Decimal::fromInteger($item->getCartQuantity()))->round($this->_roundingDecimals);

            if (!isset($totals[$item->getTaxRate()])) {
                $totals[$item->getTaxRate()] = Decimal::fromInteger(0);
            }

            $totals[$item->getTaxRate()] = $totals[$item->getTaxRate()]->add($price);
        }

        $this->_totals = ['subtotal' => Decimal::fromInteger(0), 'taxes' => [], 'total' => Decimal::fromInteger(0)];

        foreach ($totals as $taxRate => $amount) {
            if ($this->_pricesWithVat) {
                $this->_totals['total'] = $this->_totals['total']->add($amount);
                $this->_totals['taxes'][$taxRate] = $amount->mul(Decimal::fromFloat(1 - 1 / (1 + $taxRate / 100)))->round($this->_roundingDecimals);
                $this->_totals['subtotal'] = $this->_totals['subtotal']->add($amount)->sub($this->_totals['taxes'][$taxRate]);
            } else {
                $this->_totals['subtotal'] = $this->_totals['subtotal']->add($amount);
                $this->_totals['taxes'][$taxRate] = $amount->mul(Decimal::fromFloat($taxRate / 100))->round($this->_roundingDecimals);
                $this->_totals['total'] = $this->_totals['total']->add($amount)->add($this->_totals['taxes'][$taxRate]);
            }
        }
    }
}
