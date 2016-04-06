<?php
namespace spec\Riesenia\Cart;

use PhpSpec\ObjectBehavior;
use Riesenia\Cart\CartItemInterface;
use Riesenia\Cart\WeightedCartItemInterface;

class CartSpec extends ObjectBehavior
{
    public function let(CartItemInterface $item, CartItemInterface $item2)
    {
        // first item
        $item->getCartId()->willReturn('A');
        $item->getCartType()->willReturn('product');
        $item->getCartQuantity()->willReturn(2);
        $item->getUnitPrice()->willReturn(1);
        $item->getTaxRate()->willReturn(10);

        $item->setCartQuantity(2)->shouldBeCalled();
        $item->setCartContext(null)->shouldBeCalled();

        $this->addItem($item, 2);

        // second item
        $item2->getCartId()->willReturn('B');
        $item2->getCartType()->willReturn('product');
        $item2->getCartQuantity()->willReturn(1);
        $item2->getUnitPrice()->willReturn(0.825);
        $item2->getTaxRate()->willReturn(20);

        $item2->setCartQuantity(1)->shouldBeCalled();
        $item2->setCartContext(null)->shouldBeCalled();

        $this->addItem($item2);
    }

    public function it_can_add_items()
    {
        $this->countItems()->shouldReturn(2);
        $this->getItem('A')->getCartQuantity()->shouldReturn(2);
        $this->getItem('B')->getCartQuantity()->shouldReturn(1);
    }

    public function it_can_set_items($item, $item2)
    {
        $items = [$item, $item2];

        $item->getCartQuantity()->willReturn(3);
        $item->setCartQuantity(3)->shouldBeCalled();

        $this->setItems($items);

        $this->getTotal()->__toString()->shouldReturn('4.29');
    }

    public function it_can_change_item_quantity($item)
    {
        $item->setCartQuantity(7)->shouldBeCalled();

        $this->setItemQuantity('A', 7);
    }

    public function it_can_remove_item()
    {
        $this->hasItem('A')->shouldReturn(true);

        $this->removeItem('A');

        $this->hasItem('A')->shouldReturn(false);
    }

    public function it_merges_items_of_same_id($item, CartItemInterface $item3)
    {
        // stub
        $item3->getCartId()->willReturn('A');
        $item3->getCartType()->willReturn('product');

        $item->getCartQuantity()->shouldBeCalled();
        $item3->setCartQuantity(4)->shouldBeCalled();
        $item3->setCartContext(null)->shouldBeCalled();

        $this->addItem($item3, 2);
    }

    public function it_checks_empty_state_correctly()
    {
        $this->isEmpty()->shouldReturn(false);

        $this->clear();

        $this->isEmpty()->shouldReturn(true);
    }

    public function it_can_get_items_by_type($item, $item2, CartItemInterface $item3, WeightedCartItemInterface $item4)
    {
        // stub
        $item3->getCartId()->willReturn('T');
        $item3->getCartType()->willReturn('test');
        $item3->getCartQuantity()->willReturn(1);
        $item3->getUnitPrice()->willReturn(1);
        $item3->getTaxRate()->willReturn(0);

        $item3->setCartQuantity(1)->shouldBeCalled();
        $item3->setCartContext(null)->shouldBeCalled();

        $this->addItem($item3);

        $this->getItemsByType('test')->shouldReturn(['T' => $item3]);
        $this->getItemsByType('product')->shouldReturn(['A' => $item, 'B' => $item2]);

        $this->getTotal('product')->__toString()->shouldReturn('3.19');
        $this->getTotal('test')->__toString()->shouldReturn('1.00');
        $this->getTotal('product,nonexistent,test')->__toString()->shouldReturn('4.19');
        $this->getTotal('~test')->__toString()->shouldReturn('3.19');

        // stub
        $item4->getCartId()->willReturn('W');
        $item4->getCartType()->willReturn('weighted');
        $item4->getCartQuantity()->willReturn(3);
        $item4->getUnitPrice()->willReturn(1);
        $item4->getTaxRate()->willReturn(0);
        $item4->getWeight()->willReturn(0.5);

        $item4->setCartQuantity(3)->shouldBeCalled();
        $item4->setCartContext(null)->shouldBeCalled();

        $this->addItem($item4, 3);

        $this->getWeight()->__toString()->shouldReturn('1.500000');
        $this->getWeight('weighted')->__toString()->shouldReturn('1.500000');
        $this->getWeight('weighted,nonexistent,test')->__toString()->shouldReturn('1.500000');
        $this->getWeight('product,nonexistent,test')->__toString()->shouldReturn('0');
    }

    public function it_counts_totals_for_gross_prices_correctly()
    {
        $this->getSubtotal()->__toString()->shouldReturn('2.82');
        $this->getTotal()->__toString()->shouldReturn('3.19');

        $taxes = $this->getTaxes();
        $taxes[10]->__toString()->shouldReturn('0.20');
        $taxes[20]->__toString()->shouldReturn('0.17');

        $taxBases = $this->getTaxBases();
        $taxBases[10]->__toString()->shouldReturn('2.00');
        $taxBases[20]->__toString()->shouldReturn('0.82');

        $taxTotals = $this->getTaxTotals();
        $taxTotals[10]->__toString()->shouldReturn('2.20');
        $taxTotals[20]->__toString()->shouldReturn('0.99');
    }

    public function it_counts_totals_for_net_prices_correctly()
    {
        $this->setPricesWithVat(false);

        $this->getSubtotal()->__toString()->shouldReturn('2.83');
        $this->getTotal()->__toString()->shouldReturn('3.20');

        $taxes = $this->getTaxes();
        $taxes[10]->__toString()->shouldReturn('0.20');
        $taxes[20]->__toString()->shouldReturn('0.17');

        $taxBases = $this->getTaxBases();
        $taxBases[10]->__toString()->shouldReturn('2.00');
        $taxBases[20]->__toString()->shouldReturn('0.83');

        $taxTotals = $this->getTaxTotals();
        $taxTotals[10]->__toString()->shouldReturn('2.20');
        $taxTotals[20]->__toString()->shouldReturn('1.00');
    }
}
