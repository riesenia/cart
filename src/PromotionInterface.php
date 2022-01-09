<?php
/**
 * This file is part of riesenia/cart package.
 *
 * Licensed under the MIT License
 * (c) RIESENIA.com
 */

declare(strict_types=1);

namespace Riesenia\Cart;

/**
 * Iterface for promotions.
 *
 * @author Tomas Saghy <segy@riesenia.com>
 */
interface PromotionInterface
{
    /**
     * If this promotion is eligible.
     */
    public function isEligible(Cart $cart): bool;

    /**
     * Before apply callback. Called before any promotion is applied.
     */
    public function beforeApply(Cart $cart): void;

    /**
     * After apply callback. Called after all promotions have been applied.
     */
    public function afterApply(Cart $cart): void;

    /**
     * Apply promotion. Called only if promotion is eligible.
     */
    public function apply(Cart $cart): void;
}
