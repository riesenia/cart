<?php
namespace spec\Riesenia\Cart;

use Litipk\BigNumbers\Decimal;
use PhpSpec\ObjectBehavior;
use Riesenia\Cart\BoundCartItemInterface;
use Riesenia\Cart\CartItemInterface;
use Riesenia\Cart\MultipleBoundCartItemInterface;
use Riesenia\Cart\PromotionInterface;
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

    public function it_adds_items()
    {
        $this->countItems()->shouldReturn(2);
        $this->getItem('A')->getCartQuantity()->shouldReturn(2);
        $this->getItem('B')->getCartQuantity()->shouldReturn(1);
    }

    public function it_sets_items($item, $item2)
    {
        $items = [$item, $item2];

        $item->getCartQuantity()->willReturn(3);
        $item->setCartQuantity(3)->shouldBeCalled();

        $this->setItems($items);

        $this->getTotal()->equals(Decimal::fromFloat(4.29))->shouldReturn(true);
    }

    public function it_changes_item_quantity($item)
    {
        $item->setCartQuantity(7)->shouldBeCalled();

        $this->setItemQuantity('A', 7);
    }

    public function it_removes_item()
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

    public function it_gets_items_by_type($item, $item2, CartItemInterface $item3, WeightedCartItemInterface $item4)
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
        $this->getItemsByType('~test')->shouldReturn(['A' => $item, 'B' => $item2]);

        $this->getTotal('product')->equals(Decimal::fromFloat(3.19))->shouldReturn(true);
        $this->getTotal('test')->equals(Decimal::fromInteger(1))->shouldReturn(true);
        $this->getTotal('product,nonexistent,test')->equals(Decimal::fromFloat(4.19))->shouldReturn(true);
        $this->getTotal('~test')->equals(Decimal::fromFloat(3.19))->shouldReturn(true);

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

        $this->getWeight()->equals(Decimal::fromFloat(1.5))->shouldReturn(true);
        $this->getWeight('weighted')->equals(Decimal::fromFloat(1.5))->shouldReturn(true);
        $this->getWeight('weighted,nonexistent,test')->equals(Decimal::fromFloat(1.5))->shouldReturn(true);
        $this->getWeight('product,nonexistent,test')->isZero()->shouldReturn(true);
    }

    public function it_counts_totals_for_gross_prices_correctly()
    {
        $this->getSubtotal()->equals(Decimal::fromFloat(2.82))->shouldReturn(true);
        $this->getTotal()->equals(Decimal::fromFloat(3.19))->shouldReturn(true);

        $taxes = $this->getTaxes();
        $taxes[10]->equals(Decimal::fromFloat(0.2))->shouldReturn(true);
        $taxes[20]->equals(Decimal::fromFloat(0.17))->shouldReturn(true);

        $taxBases = $this->getTaxBases();
        $taxBases[10]->equals(Decimal::fromInteger(2))->shouldReturn(true);
        $taxBases[20]->equals(Decimal::fromFloat(0.82))->shouldReturn(true);

        $taxTotals = $this->getTaxTotals();
        $taxTotals[10]->equals(Decimal::fromFloat(2.2))->shouldReturn(true);
        $taxTotals[20]->equals(Decimal::fromFloat(0.99))->shouldReturn(true);
    }

    public function it_counts_totals_for_net_prices_correctly()
    {
        $this->setPricesWithVat(false);

        $this->getSubtotal()->equals(Decimal::fromFloat(2.83))->shouldReturn(true);
        $this->getTotal()->equals(Decimal::fromFloat(3.2))->shouldReturn(true);

        $taxes = $this->getTaxes();
        $taxes[10]->equals(Decimal::fromFloat(0.2))->shouldReturn(true);
        $taxes[20]->equals(Decimal::fromFloat(0.17))->shouldReturn(true);

        $taxBases = $this->getTaxBases();
        $taxBases[10]->equals(Decimal::fromInteger(2))->shouldReturn(true);
        $taxBases[20]->equals(Decimal::fromFloat(0.83))->shouldReturn(true);

        $taxTotals = $this->getTaxTotals();
        $taxTotals[10]->equals(Decimal::fromFloat(2.2))->shouldReturn(true);
        $taxTotals[20]->equals(Decimal::fromInteger(1))->shouldReturn(true);
    }

    public function it_handles_promotions(PromotionInterface $promotion1, PromotionInterface $promotion2)
    {
        // stub
        $promotion1->isEligible($this)->willReturn(true);

        // stub
        $promotion2->isEligible($this)->willReturn(false);

        $promotion1->beforeApply($this)->shouldBeCalled();
        $promotion2->beforeApply($this)->shouldBeCalled();
        $promotion1->isEligible($this)->shouldBeCalled();
        $promotion2->isEligible($this)->shouldBeCalled();
        $promotion1->apply($this)->shouldBeCalled();
        $promotion1->afterApply($this)->shouldBeCalled();
        $promotion2->afterApply($this)->shouldBeCalled();

        $this->setPromotions([$promotion1, $promotion2]);
    }

    public function it_removes_bound_item(BoundCartItemInterface $item3, BoundCartItemInterface $item4)
    {
        // stub
        $item3->getCartId()->willReturn('BOUND');
        $item3->getCartType()->willReturn('bound item');
        $item3->getCartQuantity()->willReturn(1);
        $item3->getUnitPrice()->willReturn(1);
        $item3->getTaxRate()->willReturn(0);
        $item3->getBoundItemCartId()->willReturn('A');
        $item3->updateCartQuantityAutomatically()->willReturn(false);

        $item3->setCartQuantity(1)->shouldBeCalled();
        $item3->setCartContext(null)->shouldBeCalled();

        $this->addItem($item3);

        // stub
        $item4->getCartId()->willReturn('BOUND2');
        $item4->getCartType()->willReturn('bound item 2');
        $item4->getCartQuantity()->willReturn(1);
        $item4->getUnitPrice()->willReturn(1);
        $item4->getTaxRate()->willReturn(0);
        $item4->getBoundItemCartId()->willReturn('A');
        $item4->updateCartQuantityAutomatically()->willReturn(false);

        $item4->setCartQuantity(1)->shouldBeCalled();
        $item4->setCartContext(null)->shouldBeCalled();

        $this->addItem($item4);

        $this->removeItem('A');

        $this->hasItem('BOUND')->shouldReturn(false);
        $this->hasItem('BOUND2')->shouldReturn(false);
    }

    public function it_updates_bound_item_quantity_automatically($item, BoundCartItemInterface $item3, BoundCartItemInterface $item4)
    {
        // stub
        $item3->getCartId()->willReturn('BOUND');
        $item3->getCartType()->willReturn('bound item');
        $item3->getCartQuantity()->willReturn(2);
        $item3->getUnitPrice()->willReturn(1);
        $item3->getTaxRate()->willReturn(0);
        $item3->getBoundItemCartId()->willReturn('A');
        $item3->updateCartQuantityAutomatically()->willReturn(true);

        $item3->setCartQuantity(2)->shouldBeCalled();
        $item3->setCartContext(null)->shouldBeCalled();

        $this->addItem($item3);

        // stub
        $item4->getCartId()->willReturn('BOUND2');
        $item4->getCartType()->willReturn('bound item 2');
        $item4->getCartQuantity()->willReturn(1);
        $item4->getUnitPrice()->willReturn(1);
        $item4->getTaxRate()->willReturn(0);
        $item4->getBoundItemCartId()->willReturn('A');
        $item4->updateCartQuantityAutomatically()->willReturn(false);

        $item4->setCartQuantity(1)->shouldBeCalled();
        $item4->setCartContext(null)->shouldBeCalled();

        $this->addItem($item4);

        $item->setCartQuantity(7)->shouldBeCalled();
        $item3->setCartQuantity(7)->shouldBeCalled();

        $this->setItemQuantity('A', 7);
    }

    public function it_removes_multiple_bound_item($item, MultipleBoundCartItemInterface $item3)
    {
        // stub
        $item3->getCartId()->willReturn('BOUND');
        $item3->getCartType()->willReturn('bound item');
        $item3->getCartQuantity()->willReturn(1);
        $item3->getUnitPrice()->willReturn(1);
        $item3->getTaxRate()->willReturn(0);
        $item3->getBoundItemCartIds()->willReturn(['A', 'B']);

        $item3->setCartQuantity(1)->shouldBeCalled();
        $item3->setCartContext(null)->shouldBeCalled();

        $this->addItem($item3);

        // check that updateCartQuantityAutomatically will not be called
        $item->setCartQuantity(7)->shouldBeCalled();
        $this->setItemQuantity('A', 7);

        $this->removeItem('B');

        $this->hasItem('BOUND')->shouldReturn(false);
    }

    public function it_sorts_items_correctly($item, $item2, CartItemInterface $item3, CartItemInterface $item4)
    {
        // stub
        $item3->getCartId()->willReturn('L');
        $item3->getCartType()->willReturn('last');
        $item3->getCartQuantity()->willReturn(1);
        $item3->getUnitPrice()->willReturn(1);
        $item3->getTaxRate()->willReturn(0);

        $item3->setCartQuantity(1)->shouldBeCalled();
        $item3->setCartContext(null)->shouldBeCalled();

        $this->addItem($item3);

        // stub
        $item4->getCartId()->willReturn('F');
        $item4->getCartType()->willReturn('first');
        $item4->getCartQuantity()->willReturn(3);
        $item4->getUnitPrice()->willReturn(1);
        $item4->getTaxRate()->willReturn(0);

        $item4->setCartQuantity(3)->shouldBeCalled();
        $item4->setCartContext(null)->shouldBeCalled();

        $this->addItem($item4, 3);

        $this->getItems()->shouldReturn(['A' => $item, 'B' => $item2, 'L' => $item3, 'F' => $item4]);

        $this->sortByType(['first', 'product', 'last']);

        $this->getItems()->shouldReturn(['F' => $item4, 'A' => $item, 'B' => $item2, 'L' => $item3]);
    }
}
