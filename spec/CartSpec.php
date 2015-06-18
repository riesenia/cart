<?php
namespace spec\Riesenia\Cart;

use PhpSpec\ObjectBehavior;
use Riesenia\Cart\CartItemInterface;

class CartSpec extends ObjectBehavior
{
    public function let(CartItemInterface $item, CartItemInterface $item2)
    {
        // first item
        $item->getCartId()->willReturn('A');
        $item->getCartQuantity()->willReturn(1);
        $item->getUnitPrice()->willReturn(1.24);
        $item->getTaxRate()->willReturn(10);

        $item->setCartQuantity(1)->shouldBeCalled();
        $item->setCartContext(null)->shouldBeCalled();

        $this->addItem($item);

        // second item
        $item2->getCartId()->willReturn('B');
        $item2->getCartQuantity()->willReturn(2);
        $item2->getUnitPrice()->willReturn(0.825);
        $item2->getTaxRate()->willReturn(20);

        $item2->setCartQuantity(2)->shouldBeCalled();
        $item2->setCartContext(null)->shouldBeCalled();

        $this->addItem($item2, 2);
    }

    public function it_can_add_items()
    {
        $this->countItems()->shouldReturn(2);
        $this->getItem('A')->getCartQuantity()->shouldReturn(1);
        $this->getItem('B')->getCartQuantity()->shouldReturn(2);
    }

    public function it_can_increase_item_quantity($item, CartItemInterface $item3)
    {
        // stub
        $item3->getCartId()->willReturn('A');

        $item->getCartQuantity()->shouldBeCalled();
        $item3->setCartQuantity(4)->shouldBeCalled();
        $item3->setCartContext(null)->shouldBeCalled();

        $this->addItem($item3, 3);
    }

    public function it_counts_totals_for_gross_prices_correctly()
    {
        $this->getSubtotal()->shouldReturn(2.89);
        $this->getTaxes()->shouldReturn([10 => 1.36, 20 => 0.33]);
        $this->getSubtotal()->shouldReturn(3.34);
    }
}
