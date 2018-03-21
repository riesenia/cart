<?php
namespace Riesenia\Cart;

/**
 * Iterface for promotions.
 *
 * @author Tomas Saghy <segy@riesenia.com>
 */
interface PromotionInterface
{
    /**
     * Is this promotion eligible.
     *
     * @param Cart $cart
     *
     * @return bool
     */
    public function isEligible(Cart $cart);

    /**
     * Before apply callback.
     *
     * @param Cart $cart
     */
    public function beforeApply(Cart $cart);

    /**
     * After apply callback.
     *
     * @param Cart $cart
     */
    public function afterApply(Cart $cart);

    /**
     * Apply promotion.
     *
     * @param Cart $cart
     */
    public function apply(Cart $cart);
}
