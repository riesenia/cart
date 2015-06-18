<?php
namespace Riesenia\Cart;

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
        $this->_context = $context;
        $this->_pricesWithVat = $pricesWithVat;
        $this->_roundingDecimals = $roundingDecimals;
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
     * Get item by cart id
     *
     * @return CartItemInterface
     */
    public function getItem($cartId)
    {
        return isset($this->_items[$cartId]) ? $this->_items[$cartId] : null;
    }

    /**
     * Get subtotal
     *
     * @return float
     */
    public function getSubtotal()
    {
        if (is_null($this->_totals)) {
            $this->_calculate();
        }

        return $this->_totals['subtotal'];
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
            $price = $item->getUnitPrice();

            // when listed as gross
            if ($this->_pricesWithVat) {
                $price *= 1 + $item->getTaxRate() / 100;
            }

            // count price
            $price = round($price * $item->getCartQuantity(), $this->_roundingDecimals);

            if (!isset($totals[$item->getTaxRate()])) {
                $totals[$item->getTaxRate()] = 0;
            }

            $totals[$item->getTaxRate()] += $price;
        }

        $this->_totals = ['subtotal' => 0, 'taxes' => [], 'total' => 0];

        $this->_totals[$this->_pricesWithVat ? 'total' : 'subtotal'] = array_sum($totals);

        foreach ($totals as $taxRate => $amount) {
            $this->_totals['taxes'][$taxRate] = round($this->_pricesWithVat ? $amount * (1 - 1 / (1 + $taxRate / 100)) : $amount * $taxRate / 100, $this->_roundingDecimals);
        }

        if ($this->_pricesWithVat) {
            $this->_totals['subtotal'] = $this->_totals['total'] - array_sum($this->_totals['taxes']);
        } else {
            $this->_totals['total'] = $this->_totals['subtotal'] + array_sum($this->_totals['taxes']);
        }
    }
}
